<?php

namespace App\Services\Domains;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The three custom-domain checks: records point at the platform, the TXT
 * record proves ownership, HTTPS answers. Used by the Go live page and the
 * admin Domains page. Verification (records + ownership) stamps the site.
 */
class DomainVerifier
{
    /** Per-site TXT ownership token, generated once and kept. */
    public function verifyToken(Site $site): string
    {
        $token = (string) $site->getAttr('domain.verify_token', '');
        if ($token === '') {
            $token = 'olux-'.Str::random(24);
            $site->setAttr('domain.verify_token', $token);
        }

        return $token;
    }

    /**
     * @return array{found: list<string>, records: bool, ownership: bool, ssl: bool, verified_now: bool}
     */
    public function check(Site $site, string $target): array
    {
        $domain = (string) $site->domain;

        $found = [];
        foreach ((array) @dns_get_record($domain, DNS_A) as $r) {
            $found[] = $r['ip'] ?? '';
        }
        foreach ((array) @dns_get_record($domain, DNS_CNAME) as $r) {
            $found[] = rtrim($r['target'] ?? '', '.');
        }
        $found = array_values(array_filter($found));

        $accept = [strtolower($target)];
        if (! filter_var($target, FILTER_VALIDATE_IP)) {
            $accept = array_merge($accept, array_map('strtolower', (array) @gethostbynamel($target) ?: []));
        }
        $records = (bool) array_intersect(array_map('strtolower', $found), $accept);

        $txts = collect((array) @dns_get_record('_olux-verify.'.$domain, DNS_TXT))->pluck('txt')->filter()->all();
        $ownership = in_array($this->verifyToken($site), $txts, true);

        $ssl = false;
        if ($records) {
            try {
                $ssl = Http::timeout(4)->get('https://'.$domain)->successful();
            } catch (\Throwable) {
                $ssl = false;
            }
        }

        $verifiedNow = false;
        if ($records && $ownership && ! $site->domain_verified_at) {
            $site->update(['domain_verified_at' => now()]);
            $verifiedNow = true;
        }

        return compact('found', 'records', 'ownership', 'ssl') + ['verified_now' => $verifiedNow];
    }
}
