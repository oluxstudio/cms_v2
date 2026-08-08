<?php

namespace App\Livewire;

use App\Models\ApiToken;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Per-site API keys — issue and audit Bearer tokens scoped to THIS site,
 * right where the site is managed. Every token created here is pinned to
 * the site (site_id) so it can never touch another; abilities and expiry
 * narrow it further. Gated by team.manage in the route.
 */
class SiteApiKeys extends Component
{
    public Site $site;

    public string $newName = '';

    public array $newAbilities = [];   // [] = all the creator's permissions on this site

    public ?string $newExpiry = null;  // '30'|'90'|'365' days, null = never

    public ?string $generatedToken = null;

    public string $successMessage = '';

    public function mount(Site $site): void
    {
        abort_unless($site->canManageTeam(Auth::user()), 403);
        $this->site = $site;
    }

    /** Permission abilities relevant to a single site (the token can be limited to these). */
    public function getAbilityOptionsProperty(): array
    {
        return collect(config('permissions.groups', []))
            ->flatMap(fn ($perms) => array_keys($perms))
            ->values()->all();
    }

    public function generate(): void
    {
        abort_unless($this->site->canManageTeam(Auth::user()), 403);
        $this->validate([
            'newName' => ['required', 'string', 'max:80'],
            'newExpiry' => ['nullable', 'in:30,90,365'],
            'newAbilities' => ['array'],
            'newAbilities.*' => ['string'],
        ]);

        $raw = Str::random(64);
        ApiToken::create([
            'user_id' => Auth::id(),
            'site_id' => $this->site->id,          // pinned to this site — always
            'name' => $this->newName,
            'token' => hash('sha256', $raw),
            'token_preview' => substr($raw, 0, 8),
            'abilities' => $this->newAbilities !== [] ? array_values($this->newAbilities) : null,
            'expires_at' => $this->newExpiry ? now()->addDays((int) $this->newExpiry) : null,
        ]);

        $this->generatedToken = $raw;
        $this->reset(['newName', 'newAbilities', 'newExpiry']);
        $this->successMessage = 'API key generated — copy it now, it will not be shown again.';
    }

    public function revoke(string $id): void
    {
        abort_unless($this->site->canManageTeam(Auth::user()), 403);
        // Only tokens belonging to THIS site can be revoked here.
        ApiToken::where('site_id', $this->site->id)->where('id', $id)->delete();
        $this->successMessage = 'API key revoked.';
    }

    public function getTokensProperty()
    {
        return ApiToken::where('site_id', $this->site->id)
            ->with('user:id,name')->latest()->get();
    }

    public function render()
    {
        return view('livewire.site-api-keys');
    }
}
