<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\PlatformBilling;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Platform admin — client accounts: see every account's subscription, set
 * INDIVIDUAL per-tier prices (in whole currency units, stored as cents), or
 * assign a plan directly (comp accounts). Super admins only.
 */
class PlatformAccountsPage extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** account being edited in the drawer */
    public ?string $editingId = null;

    /** plan => price string (whole units, '' = no override) */
    public array $prices = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    public function edit(string $userId): void
    {
        $user = User::findOrFail($userId);
        $sub = $user->currentSubscription();
        $this->editingId = $userId;
        $this->prices = [];
        foreach (array_keys(config('plans.tiers')) as $plan) {
            if ($plan === 'trial') {
                continue;
            }
            $this->prices[$plan] = $sub->hasOverride($plan)
                ? number_format($sub->priceFor($plan) / 100, 2, '.', '')
                : '';
        }
    }

    public function close(): void
    {
        $this->reset(['editingId', 'prices']);
    }

    /** Persist the per-tier overrides (blank clears back to list price). */
    public function savePrices(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $user = User::findOrFail($this->editingId);
        $overrides = [];
        foreach ($this->prices as $plan => $value) {
            $value = trim((string) $value);
            if ($value === '' || ! is_numeric($value) || (float) $value < 0) {
                continue;
            }
            $overrides[$plan] = (int) round(((float) $value) * 100);
        }
        $user->currentSubscription()->update(['price_overrides' => $overrides ?: null]);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Custom pricing updated for '.$user->name.'.');
        $this->close();
    }

    /** Comp/assign a plan directly, bypassing payment. */
    public function assignPlan(string $userId, string $plan): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! config("plans.tiers.{$plan}")) {
            return;
        }
        $user = User::findOrFail($userId);
        if ($plan === 'trial') {
            $user->currentSubscription()->update([
                'plan' => 'trial', 'status' => 'trialing',
                'trial_ends_at' => now()->addDays((int) config('plans.trial_days', 14)),
            ]);
        } else {
            app(PlatformBilling::class)->activate($user, $plan);
        }
    }

    public function render()
    {
        $accounts = User::query()
            ->with('subscription')
            ->withCount('sites')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.platform-accounts-page', ['accounts' => $accounts]);
    }
}
