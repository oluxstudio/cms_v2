<?php

namespace App\Services\Email;

use App\Models\EmailDomain;
use App\Services\Openprovider\OpenproviderClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The DNS side of business email: which records a domain needs, merging SPF
 * into ONE record, checking what currently resolves, and writing records into
 * zones on Openprovider's nameservers (add/update/remove — never a full
 * replace, so the site's own A/CNAME records survive).
 */
class EmailDns
{
    public function __construct(private EmailProvider $provider) {}

    // ── What the domain needs ──────────────────────────────────────

    /**
     * @return list<array{purpose:string, type:string, name:string, value:string, prio:?int}>
     *                                                                                        name is relative to the domain ('' = the domain itself)
     */
    public function desiredRecords(EmailDomain $d, array $existingSpf = []): array
    {
        $cfg = config('email.records');
        $domain = $d->domain;
        $out = [];

        foreach ((array) ($cfg['mx'] ?? []) as $mx) {
            $out[] = ['purpose' => 'Receive email (MX)', 'type' => 'MX', 'name' => '', 'value' => rtrim((string) $mx['host'], '.'), 'prio' => (int) ($mx['prio'] ?? 10)];
        }

        $out[] = ['purpose' => 'Allowed senders (SPF)', 'type' => 'TXT', 'name' => '', 'value' => self::mergeSpf($existingSpf, $this->spfIncludes($d)), 'prio' => null];

        if ($dkim = $this->dkim($d)) {
            $out[] = ['purpose' => 'Signature key (DKIM)', 'type' => 'TXT', 'name' => $this->relative($dkim['name'], $domain), 'value' => $dkim['value'], 'prio' => null];
        }

        $out[] = ['purpose' => 'Reporting policy (DMARC)', 'type' => 'TXT', 'name' => '_dmarc', 'value' => str_replace('{domain}', $domain, (string) $cfg['dmarc']), 'prio' => null];

        foreach ((array) ($cfg['cname'] ?? []) as $c) {
            $out[] = ['purpose' => 'Mail app setup ('.$c['name'].')', 'type' => 'CNAME', 'name' => $c['name'], 'value' => rtrim((string) $c['value'], '.'), 'prio' => null];
        }
        foreach ((array) ($cfg['srv'] ?? []) as $s) {
            $out[] = ['purpose' => 'Mail app setup (SRV)', 'type' => 'SRV', 'name' => $s['name'], 'value' => (string) $s['value'], 'prio' => (int) ($s['prio'] ?? 0)];
        }

        return $out;
    }

    /** Includes our SPF must carry: the mail provider's, plus Brevo's only when the tenant sends platform mail from their domain. */
    public function spfIncludes(EmailDomain $d): array
    {
        $includes = array_filter([(string) config('email.records.spf_include')]);
        if ($d->site?->getAttr('email.send_from_own_domain') === '1' && $d->site?->getAttr('email.brevo_authenticated') === '1') {
            $includes[] = (string) config('email.brevo.spf_include');
        }

        return array_values(array_unique(array_map(fn ($i) => str_starts_with($i, 'include:') ? $i : 'include:'.$i, $includes)));
    }

    /** DKIM from the provider, remembered on the domain so checks don't refetch it every time. */
    public function dkim(EmailDomain $d): ?array
    {
        $report = (array) $d->dns_report;
        if (! empty($report['dkim']['value'])) {
            return $report['dkim'];
        }
        try {
            $rec = $this->provider->dkimRecord($d->domain, ['account_id' => $d->account_id]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        if ($rec) {
            $d->update(['dns_report' => ['dkim' => $rec] + $report]);
        }

        return $rec;
    }

    // ── SPF ────────────────────────────────────────────────────────

    /**
     * One SPF record from any existing ones plus the includes we need: every
     * existing mechanism and include is kept, ours are added if missing, and
     * the strictest existing "all" (default ~all) closes it. A domain may only
     * have ONE v=spf1 record.
     *
     * @param  list<string>  $existing  existing TXT values that start with v=spf1
     * @param  list<string>  $includes  e.g. ['include:_spf.provider.net']
     */
    public static function mergeSpf(array $existing, array $includes): string
    {
        $terms = [];
        $all = null;
        $rank = ['+all' => 0, 'all' => 0, '?all' => 1, '~all' => 2, '-all' => 3];
        foreach ($existing as $record) {
            $record = trim(trim((string) $record), '"');
            if (! preg_match('/^v=spf1\b/i', $record)) {
                continue;
            }
            foreach (preg_split('/\s+/', trim(substr($record, 6))) as $t) {
                if ($t === '') {
                    continue;
                }
                $lower = strtolower($t);
                if (isset($rank[$lower])) {
                    $all = ($all === null || $rank[$lower] > $rank[$all]) ? $lower : $all;
                } elseif (str_starts_with($lower, 'redirect=') && ($includes || count($existing) > 1)) {
                    $terms[] = 'include:'.substr($t, 9); // a redirect can't be combined — keep it as an include
                } elseif (! in_array($lower, array_map('strtolower', $terms), true)) {
                    $terms[] = $t;
                }
            }
        }
        foreach ($includes as $inc) {
            if ($inc !== '' && ! in_array(strtolower($inc), array_map('strtolower', $terms), true)) {
                $terms[] = $inc;
            }
        }
        $all ??= '~all';
        if ($all === 'all') {
            $all = '+all';
        }

        return trim('v=spf1 '.implode(' ', $terms).' '.$all);
    }

    // ── What resolves now ──────────────────────────────────────────

    /** MX hosts currently published for the domain (lower-case, no trailing dot). */
    public function currentMx(string $domain): array
    {
        return collect((array) @dns_get_record($domain, DNS_MX))
            ->sortBy('pri')->pluck('target')->map(fn ($h) => rtrim(strtolower((string) $h), '.'))->filter()->values()->all();
    }

    /** Would switching email here take mail away from another provider? */
    public function hasForeignMx(string $domain): bool
    {
        $ours = collect((array) config('email.records.mx'))->pluck('host')->map(fn ($h) => rtrim(strtolower((string) $h), '.'))->all();

        return collect($this->currentMx($domain))->contains(fn ($h) => ! in_array($h, $ours, true));
    }

    /** Current v=spf1 TXT values at the domain. */
    public function currentSpf(string $domain): array
    {
        return collect((array) @dns_get_record($domain, DNS_TXT))
            ->map(fn ($r) => (string) ($r['txt'] ?? implode('', (array) ($r['entries'] ?? []))))
            ->filter(fn ($t) => preg_match('/^v=spf1\b/i', $t))->values()->all();
    }

    /**
     * Check each needed record against live DNS.
     *
     * @return array{ok:bool, records:list<array>}
     */
    public function check(EmailDomain $d): array
    {
        $domain = $d->domain;
        $records = $this->desiredRecords($d, $this->currentSpf($domain));
        $results = [];
        foreach ($records as $r) {
            $host = $r['name'] === '' ? $domain : $r['name'].'.'.$domain;
            $ok = match ($r['type']) {
                'MX' => in_array(strtolower($r['value']), $this->currentMx($domain), true),
                'CNAME' => collect((array) @dns_get_record($host, DNS_CNAME))->contains(fn ($x) => rtrim(strtolower((string) ($x['target'] ?? '')), '.') === strtolower($r['value'])),
                'TXT' => $this->txtOk($d, $host, $r),
                default => true, // SRV: informational
            };
            $results[] = $r + ['host' => $host, 'ok' => $ok];
        }

        // Until the provider's MX hosts are configured (config/email.php TODO) a
        // domain can't really receive mail — never report it as ready.
        if (collect($records)->where('type', 'MX')->isEmpty()) {
            $results[] = ['purpose' => 'Receive email (MX)', 'type' => 'MX', 'name' => '', 'value' => 'Not configured on the platform yet', 'prio' => null, 'host' => $domain, 'ok' => false];
        }

        return ['ok' => collect($results)->every('ok'), 'records' => $results];
    }

    private function txtOk(EmailDomain $d, string $host, array $r): bool
    {
        $txts = collect((array) @dns_get_record($host, DNS_TXT))->map(fn ($x) => (string) ($x['txt'] ?? implode('', (array) ($x['entries'] ?? []))));
        if (str_starts_with($r['value'], 'v=spf1')) {
            $spf = $txts->filter(fn ($t) => preg_match('/^v=spf1\b/i', $t));

            // Exactly one SPF record, carrying every include we need.
            return $spf->count() === 1
                && collect($this->spfIncludes($d))->every(fn ($inc) => str_contains(strtolower($spf->first()), strtolower($inc)));
        }
        if (str_starts_with($r['value'], 'v=DMARC1')) {
            return $txts->contains(fn ($t) => str_starts_with(strtoupper($t), 'V=DMARC1'));
        }

        return $txts->contains(fn ($t) => str_replace(' ', '', $t) === str_replace(' ', '', $r['value']));
    }

    // ── Writing records on Openprovider nameservers ────────────────

    /**
     * Add/merge our records into the domain's Openprovider zone. Existing MX
     * records are replaced only when the tenant confirmed the switch; the SPF
     * record is merged into one; an existing DMARC policy is left alone.
     */
    public function applyToOpenproviderZone(EmailDomain $d, OpenproviderClient $client): void
    {
        $domain = $d->domain;
        $log = ['action' => 'dns.read', 'account_id' => $d->account_id];
        $current = collect((array) ($client->send('get', '/dns/zones/'.rawurlencode($domain).'/records', ['limit' => 500], $log)['results'] ?? []))
            ->map(fn ($r) => ['type' => strtoupper((string) $r['type']), 'name' => $this->relative((string) $r['name'], $domain), 'value' => trim((string) $r['value'], '"'), 'prio' => $r['prio'] ?? null, 'ttl' => $r['ttl'] ?? 900]);

        $spfExisting = $current->where('type', 'TXT')->where('name', '')->filter(fn ($r) => preg_match('/^v=spf1\b/i', $r['value']));
        $wanted = $this->desiredRecords($d, $spfExisting->pluck('value')->all());

        $add = [];
        $remove = [];
        $update = [];
        $zoneRecord = fn ($r) => array_filter(['name' => $r['name'], 'type' => $r['type'], 'value' => $r['value'], 'prio' => $r['prio'] ?? null, 'ttl' => 900], fn ($v) => $v !== null);

        // MX: ours replace the old ones (only with the tenant's explicit OK when others exist).
        $oldMx = $current->where('type', 'MX')->where('name', '');
        $ourMx = collect($wanted)->where('type', 'MX');
        if ($ourMx->isNotEmpty() && ($oldMx->isEmpty() || $d->mx_change_confirmed_at)) {
            foreach ($oldMx as $r) {
                if (! $ourMx->contains(fn ($w) => strtolower($w['value']) === strtolower(rtrim($r['value'], '.')))) {
                    $remove[] = $zoneRecord($r);
                }
            }
            foreach ($ourMx as $w) {
                if (! $oldMx->contains(fn ($r) => strtolower(rtrim($r['value'], '.')) === strtolower($w['value']))) {
                    $add[] = $zoneRecord($w);
                }
            }
        }

        foreach ($wanted as $w) {
            if ($w['type'] === 'MX') {
                continue;
            }
            if ($w['type'] === 'TXT' && str_starts_with($w['value'], 'v=spf1')) {
                // Exactly one SPF: update the first, drop any extras.
                $first = $spfExisting->first();
                if ($first) {
                    if ($first['value'] !== $w['value']) {
                        $update[] = ['original_record' => $zoneRecord($first), 'record' => $zoneRecord($w)];
                    }
                    foreach ($spfExisting->slice(1) as $extra) {
                        $remove[] = $zoneRecord($extra);
                    }
                } else {
                    $add[] = $zoneRecord($w);
                }

                continue;
            }
            $same = $current->where('type', $w['type'])->where('name', $w['name']);
            if ($w['name'] === '_dmarc' && $same->isNotEmpty()) {
                continue; // keep the tenant's own DMARC policy
            }
            if ($same->contains(fn ($r) => rtrim(strtolower($r['value']), '.') === rtrim(strtolower($w['value']), '.'))) {
                continue;
            }
            $same->isNotEmpty() && in_array($w['type'], ['CNAME'], true)
                ? $update[] = ['original_record' => $zoneRecord($same->first()), 'record' => $zoneRecord($w)]
                : $add[] = $zoneRecord($w);
        }

        if ($add || $remove || $update) {
            $client->send('put', '/dns/zones/'.rawurlencode($domain), [
                'name' => $domain,
                'records' => array_filter(['add' => $add, 'remove' => $remove, 'update' => $update]),
            ], ['action' => 'dns.write', 'account_id' => $d->account_id]);
        }
        Cache::forget('email-dns:'.$domain);
        Log::info('email dns applied', ['domain' => $domain, 'add' => count($add), 'remove' => count($remove), 'update' => count($update)]);
    }

    /** "dkim._domainkey.example.com" → "dkim._domainkey" for example.com; the domain itself → ''. */
    private function relative(string $name, string $domain): string
    {
        $name = rtrim(strtolower($name), '.');
        $domain = strtolower($domain);
        if ($name === $domain || $name === '@' || $name === '') {
            return '';
        }

        return str_ends_with($name, '.'.$domain) ? substr($name, 0, -strlen('.'.$domain)) : $name;
    }
}
