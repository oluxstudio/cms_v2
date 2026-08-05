<?php

namespace App\Livewire;

use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Go live — put the site on the client's own domain, served by this platform.
 * Collects the data needed (domain), shows the exact DNS records to create,
 * verifies them with a real lookup, and flips the site Live/Offline.
 */
class GoLivePage extends Component
{
    public Site $site;

    public string $domain = '';

    public string $errorMessage = '';

    /** null = not checked this request; array of found A/CNAME values after a check. */
    public ?array $dnsFound = null;

    public function mount(Site $site): void
    {
        $this->site = $site;
        abort_unless($site->allows(Auth::user(), 'publish.manage'), 403);
        $this->domain = (string) $site->domain;
    }

    private function guard(): void
    {
        abort_unless($this->site->allows(Auth::user(), 'publish.manage'), 403);
    }

    public function saveDomain(): void
    {
        $this->guard();
        $this->errorMessage = '';
        $this->dnsFound = null;

        $normalized = Site::normalizeDomain($this->domain);
        if (! $normalized) {
            $this->errorMessage = 'That does not look like a valid domain — enter it like example.com.';

            return;
        }
        if (Site::where('id', '!=', $this->site->id)->where('domain', $normalized)->exists()) {
            $this->errorMessage = 'That domain is already connected to another site.';

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
        $this->dispatch('toast', level: 'success', title: 'Domain saved', message: $normalized.' is connected to this site.');
    }

    /** Real DNS lookup: does the domain point at the platform's DNS target? */
    public function verifyDns(): void
    {
        $this->guard();
        $this->errorMessage = '';
        $target = (string) config('publishing.dns_target');
        if ($target === '') {
            $this->errorMessage = 'The platform DNS target is not configured yet (set PLATFORM_DNS_TARGET in .env).';

            return;
        }

        $found = [];
        foreach ((array) @dns_get_record($this->site->domain, DNS_A) as $r) {
            $found[] = $r['ip'] ?? '';
        }
        foreach ((array) @dns_get_record($this->site->domain, DNS_CNAME) as $r) {
            $found[] = rtrim($r['target'] ?? '', '.');
        }
        $this->dnsFound = array_values(array_filter($found));

        // Match directly, or resolve a hostname target to its IPs and match those.
        $accept = [strtolower($target)];
        if (! filter_var($target, FILTER_VALIDATE_IP)) {
            $accept = array_merge($accept, array_map('strtolower', (array) @gethostbynamel($target) ?: []));
        }

        if (array_intersect(array_map('strtolower', $this->dnsFound), $accept)) {
            $this->site->update(['domain_verified_at' => now()]);
            $this->dispatch('toast', level: 'success', title: 'Domain verified', message: $this->site->domain.' points at this platform.');
        } else {
            $this->errorMessage = $this->dnsFound
                ? 'The domain currently points elsewhere ('.implode(', ', $this->dnsFound).'). DNS changes can take up to 24h to propagate.'
                : 'No DNS records found yet for '.$this->site->domain.'. Create the records below, then verify again.';
        }
    }

    public function toggleLive(): void
    {
        $this->guard();
        $this->errorMessage = '';

        if (! $this->site->live && ! $this->site->domain) {
            $this->errorMessage = 'Connect a domain before going live.';

            return;
        }

        $this->site->update(['live' => ! $this->site->live]);
        $this->dispatch('toast', level: 'success',
            title: $this->site->live ? 'Site is LIVE' : 'Site taken offline',
            message: $this->site->live
                ? 'Requests to '.$this->site->domain.' now serve this site.'
                : 'The domain shows nothing until you go live again.');
    }

    public function render()
    {
        return view('livewire.go-live-page', [
            'dnsTarget' => (string) config('publishing.dns_target'),
            'hasBuild' => $this->site->liveShell() !== null,
        ]);
    }
}
