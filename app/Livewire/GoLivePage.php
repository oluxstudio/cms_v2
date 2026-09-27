<?php

namespace App\Livewire;

use App\Jobs\BuildTemplateShell;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Services\TaskLogger;
use App\Support\GoLiveChecklist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Go live — one choice, then one path. The client either BUYS a domain
 * through us (registered + connected automatically) or CONNECTS one they
 * already own (save → point DNS → verified checks → switch on). The centre
 * pane is a small state machine (choose | buy | connect); the stepper and
 * the rails reflect the tenant's real state at all times.
 */
class GoLivePage extends Component
{
    public Site $site;

    public string $domain = '';

    public string $errorMessage = '';

    /** choose | buy | connect — null = derive from the tenant's state. */
    public ?string $flow = null;

    /** Domain input locked after a successful save (Change unlocks it). */
    public bool $domainLocked = false;

    /** null = not checked this request; array of found A/CNAME values after a check. */
    public ?array $dnsFound = null;

    /**
     * The three connect checks: records | ownership | ssl →
     * done | working | waiting (+ optional hint).
     *
     * @var array<string, array{state: string, hint: ?string}>
     */
    public array $checks = [];

    public ?string $lastCheckedAt = null;

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'publish.manage'), 403);
        $this->domain = (string) $site->domain;
        $this->domainLocked = filled($site->domain);
        $this->resetChecks();
    }

    private function guard(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'publish.manage'), 403);
    }

    // ─────────────────────────────────────────────────────────────
    // Flow state machine
    // ─────────────────────────────────────────────────────────────

    /** The pane to show: explicit choice wins, else derived from tenant state. */
    public function currentFlow(): string
    {
        if ($this->flow !== null) {
            return $this->flow;
        }
        if ($this->ourOrder() !== null) {
            return 'buy';
        }
        if (filled($this->site->domain)) {
            return 'connect';
        }

        return 'choose';
    }

    /** The latest domain order WE handled for this site (registered or in flight). */
    public function ourOrder(): ?DomainOrder
    {
        return DomainOrder::where('site_id', $this->site->id)
            ->whereIn('status', ['registered', 'paid', 'pending'])
            ->where('type', 'register')
            ->latest()->first();
    }

    public function chooseBuy(): void
    {
        $this->guard();
        // One active path per tenant: switching to Buy discards a saved manual
        // domain (the blade confirms via data-confirm before calling this).
        if (filled($this->site->domain) && $this->ourOrder()?->domain !== $this->site->domain) {
            $this->discardDomain();
        }
        $this->flow = 'buy';
    }

    public function chooseConnect(): void
    {
        $this->guard();
        $this->flow = 'connect';
        $this->ensureVerifyToken();
    }

    public function backToOptions(): void
    {
        $this->guard();
        $this->errorMessage = '';
        $this->flow = 'choose';
    }

    /** Remove the saved manual domain and start again (confirmed in the blade). */
    public function discardDomain(): void
    {
        $this->guard();
        $this->site->update(['domain' => '', 'domain_verified_at' => null, 'live' => false]);
        $this->domain = '';
        $this->domainLocked = false;
        $this->resetChecks();
    }

    public function unlockDomain(): void
    {
        $this->domainLocked = false;
    }

    // ─────────────────────────────────────────────────────────────
    // Connect path
    // ─────────────────────────────────────────────────────────────

    public function saveDomain(): void
    {
        $this->guard();
        $this->errorMessage = '';
        $this->dnsFound = null;

        $normalized = Site::normalizeDomain($this->domain);
        if (! $normalized) {
            $this->errorMessage = 'Enter the full address, e.g. janes-salon.co.uk';

            return;
        }
        if (Site::where('id', '!=', $this->site->id)->where('domain', $normalized)->exists()) {
            $this->errorMessage = 'That domain is already connected to another site.';

            return;
        }

        // The domain must actually EXIST on the internet (be registered) —
        // a typo like "fod.com" would leave the client stuck at the checks.
        if (! $this->domainExists($normalized)) {
            $this->errorMessage = "We couldn't find {$normalized} on the internet — it doesn't look registered. Check the spelling, or buy it brand new from the Buy option.";

            return;
        }

        // Changing the domain invalidates any previous verification.
        $changed = $normalized !== $this->site->domain;
        $this->site->update([
            'domain' => $normalized,
            'domain_verified_at' => $changed ? null : $this->site->domain_verified_at,
            'live' => $changed ? false : $this->site->live,
        ]);
        $this->domain = $normalized;
        $this->domainLocked = true;
        $this->ensureVerifyToken();
        $this->resetChecks();
        $this->dispatch('toast', level: 'success', title: 'Domain saved', message: $normalized.' is connected to this site.');
    }

    /** Is the domain registered at all? (NS/SOA/A presence, cached briefly.) */
    public function domainExists(string $domain): bool
    {
        return Cache::remember('domain-exists:'.$domain, now()->addMinutes(10), function () use ($domain) {
            foreach (['NS', 'SOA', 'A'] as $type) {
                if (@checkdnsrr($domain, $type)) {
                    return true;
                }
            }

            return false;
        });
    }

    /** Per-site TXT ownership token, generated once and kept. */
    public function verifyToken(): string
    {
        $token = (string) $this->site->getAttr('domain.verify_token', '');
        if ($token === '') {
            $token = 'olux-'.Str::random(24);
            $this->site->setAttr('domain.verify_token', $token);
        }

        return $token;
    }

    private function ensureVerifyToken(): void
    {
        $this->verifyToken();
    }

    private function resetChecks(): void
    {
        $verified = $this->site->domain_verified_at !== null;
        $this->checks = [
            'records' => ['state' => $verified ? 'done' : 'waiting', 'hint' => null],
            'ownership' => ['state' => $verified ? 'done' : 'waiting', 'hint' => null],
            'ssl' => ['state' => 'waiting', 'hint' => null],
        ];
    }

    /** Legacy name kept for callers/tests — runs the full check set. */
    public function verifyDns(): void
    {
        $this->runChecks();
    }

    /**
     * The three connect checks: records point here, TXT proves ownership,
     * HTTPS answers. Verification (records + ownership) sets domain_verified_at.
     */
    public function runChecks(): void
    {
        $this->guard();
        $this->errorMessage = '';
        $domain = $this->site->domain;
        if (! $domain) {
            return;
        }

        if (($target = $this->usableDnsTarget()) === null) {
            return; // client-safe copy shown by the blade; admins alerted
        }

        // 1 · Records found — A/CNAME pointing at the platform.
        $found = [];
        foreach ((array) @dns_get_record($domain, DNS_A) as $r) {
            $found[] = $r['ip'] ?? '';
        }
        foreach ((array) @dns_get_record($domain, DNS_CNAME) as $r) {
            $found[] = rtrim($r['target'] ?? '', '.');
        }
        $this->dnsFound = array_values(array_filter($found));

        $accept = [strtolower($target)];
        if (! filter_var($target, FILTER_VALIDATE_IP)) {
            $accept = array_merge($accept, array_map('strtolower', (array) @gethostbynamel($target) ?: []));
        }
        $recordsOk = (bool) array_intersect(array_map('strtolower', $this->dnsFound), $accept);
        $this->checks['records'] = $recordsOk
            ? ['state' => 'done', 'hint' => null]
            : ['state' => 'working', 'hint' => $this->dnsFound
                ? 'Pointing elsewhere right now — changes can take up to 24h'
                : 'No records seen yet — they can take a while to appear'];

        // 2 · Confirming you own it — TXT _olux-verify carries our token.
        $token = $this->verifyToken();
        $txts = collect((array) @dns_get_record('_olux-verify.'.$domain, DNS_TXT))
            ->pluck('txt')->filter()->all();
        $ownershipOk = in_array($token, $txts, true);
        $this->checks['ownership'] = $ownershipOk
            ? ['state' => 'done', 'hint' => null]
            : ['state' => $recordsOk ? 'working' : 'waiting', 'hint' => $recordsOk ? 'Add the TXT record from step 2' : null];

        // 3 · Secure padlock — HTTPS answers on the domain.
        $sslOk = false;
        if ($recordsOk) {
            try {
                $sslOk = Http::timeout(4)->get('https://'.$domain)->successful();
            } catch (\Throwable) {
                $sslOk = false;
            }
        }
        $this->checks['ssl'] = $sslOk
            ? ['state' => 'done', 'hint' => null]
            : ['state' => $recordsOk ? 'working' : 'waiting', 'hint' => $recordsOk ? 'Certificates are issued automatically — usually minutes' : null];

        $this->lastCheckedAt = now()->toIso8601String();

        if ($recordsOk && $ownershipOk && ! $this->site->domain_verified_at) {
            $this->site->update(['domain_verified_at' => now()]);
            $this->dispatch('toast', level: 'success', title: 'Domain verified', message: $domain.' points at this platform.');
        }
    }

    /** All three checks green? (drives the Go live button) */
    public function checksPassed(): bool
    {
        return collect($this->checks)->every(fn ($c) => ($c['state'] ?? '') === 'done');
    }

    /**
     * The platform DNS target — or null when unconfigured. Missing config is
     * an ADMIN problem: clients get calm copy, admins get an alert + log.
     */
    public function usableDnsTarget(): ?string
    {
        $target = (string) config('publishing.dns_target');
        if ($target !== '') {
            return $target;
        }

        // Notify once per day, never in the client's face.
        if (Cache::add('dns-target-missing-flagged:'.now()->toDateString(), true, now()->addDay())) {
            report(new \RuntimeException('PLATFORM_DNS_TARGET is not configured — domain connection is disabled for clients.'));
        }
        try {
            app(TaskLogger::class)->alert(
                $this->site,
                'Platform DNS target missing',
                type: 'config', level: 'error',
                body: 'PLATFORM_DNS_TARGET is unset — clients cannot connect custom domains.',
                audience: 'admins',
                dedupeKey: 'config:dns-target:'.now()->toDateString(),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Registrar detection (NS → provider name + help link)
    // ─────────────────────────────────────────────────────────────

    private const REGISTRARS = [
        'domaincontrol' => ['GoDaddy', 'https://www.godaddy.com/help/manage-dns-records-680'],
        'godaddy' => ['GoDaddy', 'https://www.godaddy.com/help/manage-dns-records-680'],
        'ui-dns' => ['IONOS', 'https://www.ionos.co.uk/help/domains/configuring-your-ip-address/'],
        'ionos' => ['IONOS', 'https://www.ionos.co.uk/help/domains/configuring-your-ip-address/'],
        '123-reg' => ['123-Reg', 'https://www.123-reg.co.uk/support/domains/how-do-i-set-up-a-records-on-my-domain-name/'],
        'registrar-servers' => ['Namecheap', 'https://www.namecheap.com/support/knowledgebase/article.aspx/319/2237/'],
        'cloudflare' => ['Cloudflare', 'https://developers.cloudflare.com/dns/manage-dns-records/how-to/create-dns-records/'],
        'livedns' => ['Fasthosts', 'https://help.fasthosts.co.uk/app/answers/detail/a_id/1556'],
        'fasthosts' => ['Fasthosts', 'https://help.fasthosts.co.uk/app/answers/detail/a_id/1556'],
        'googledomains' => ['Squarespace', 'https://support.squarespace.com/hc/en-us/articles/360002101888'],
        'squarespace' => ['Squarespace', 'https://support.squarespace.com/hc/en-us/articles/360002101888'],
    ];

    /** @return array{name: ?string, help: ?string} */
    public function registrarInfo(): array
    {
        $domain = $this->site->domain;
        if (! $domain) {
            return ['name' => null, 'help' => null];
        }

        return Cache::remember('registrar-of:'.$domain, now()->addHour(), function () use ($domain) {
            $ns = collect((array) @dns_get_record($domain, DNS_NS))->pluck('target')->implode(' ');
            foreach (self::REGISTRARS as $needle => [$name, $help]) {
                if ($ns !== '' && str_contains(strtolower($ns), $needle)) {
                    return ['name' => $name, 'help' => $help];
                }
            }

            return ['name' => null, 'help' => null];
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Go live
    // ─────────────────────────────────────────────────────────────

    public function toggleLive(): void
    {
        $this->guard();
        $this->errorMessage = '';

        if (! $this->site->live) {
            if (! $this->site->domain) {
                $this->errorMessage = 'Connect a domain before going live.';

                return;
            }
            // HARD guard: never live before the domain is verified.
            if (! $this->site->domain_verified_at) {
                $this->errorMessage = 'Your domain has to pass the checks before the site can go live.';

                return;
            }
        }

        $this->site->update(['live' => ! $this->site->live]);
        $this->dispatch('toast', level: 'success',
            title: $this->site->live ? 'Site is LIVE' : 'Site taken offline',
            message: $this->site->live
                ? 'Your site is now live at '.$this->site->domain.'.'
                : 'The domain shows nothing until you go live again.');
    }

    public function render()
    {
        $orders = DomainOrder::where('site_id', $this->site->id)
            ->latest()->limit(6)->get();

        return view('livewire.go-live-page', [
            'pane' => $this->currentFlow(),
            'ourOrder' => $this->ourOrder(),
            'dnsTarget' => (string) config('publishing.dns_target'),
            'dnsAvailable' => (string) config('publishing.dns_target') !== '',
            'registrar' => $this->currentFlow() === 'connect' ? $this->registrarInfo() : ['name' => null, 'help' => null],
            'hasBuild' => $this->site->liveShell() !== null,
            // Hosting space: auto-queue the template shell build when missing.
            'hostingState' => BuildTemplateShell::ensure($this->site),
            'checklist' => GoLiveChecklist::steps($this->site),
            'checklistProgress' => GoLiveChecklist::progress($this->site),
            'orders' => $orders,
            'nextExpiry' => $orders->where('status', 'registered')->where('type', 'register')
                ->pluck('expires_at')->filter()->min(),
            'supportMail' => (string) config('mail.from.address'), // TODO: real support route
        ]);
    }
}
