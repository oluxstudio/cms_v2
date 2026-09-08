<?php

namespace App\Livewire;

use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
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

    /** newest | storage | visits | name */
    #[Url(as: 'sort')]
    public string $sort = 'name';

    /** '' = all, else a plan tier key. */
    #[Url(as: 'plan')]
    public string $planFilter = '';

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, ['name', 'newest', 'storage', 'visits'], true) ? $sort : 'name';
        $this->resetPage();
    }

    public function filterPlan(string $plan): void
    {
        $this->planFilter = $this->planFilter === $plan ? '' : $plan;
        $this->resetPage();
    }

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
            // Usage columns for the table + sorting: storage + 30-day traffic.
            ->addSelect([
                'storage_bytes' => Media::selectRaw('coalesce(sum(bytes), 0)')
                    ->whereIn('site_id', Site::select('id')->whereColumn('sites.user_id', 'users.id')),
                'visits_30d' => Visit::selectRaw('count(*)')
                    ->where('is_bot', false)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('site_id', Site::select('id')->whereColumn('sites.user_id', 'users.id')),
            ])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($this->planFilter !== '', function ($q) {
                if ($this->planFilter === 'trial') {
                    // Accounts without a subscription row are implicitly on trial.
                    $q->where(fn ($w) => $w
                        ->whereHas('subscription', fn ($s) => $s->where('plan', 'trial'))
                        ->orWhereDoesntHave('subscription'));
                } else {
                    $q->whereHas('subscription', fn ($s) => $s->where('plan', $this->planFilter));
                }
            })
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'storage', fn ($q) => $q->orderByDesc('storage_bytes'))
            ->when($this->sort === 'visits', fn ($q) => $q->orderByDesc('visits_30d'))
            ->paginate(15);

        return view('livewire.platform-accounts-page', ['accounts' => $accounts]);
    }
}
