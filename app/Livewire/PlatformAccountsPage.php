<?php

namespace App\Livewire;

use App\Models\AccountSubscription;
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\AccountActivity;
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

    /** Per-account mailbox limit ('' = plan default; Enterprise sets it here). */
    public string $mailboxOverride = '';

    /** Extra mailboxes on top of the plan (add-ons / goodwill). */
    public string $extraMailboxes = '0';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    public function edit(string $userId): void
    {
        $user = User::findOrFail($userId);
        $sub = $user->currentSubscription();
        $this->editingId = $userId;
        $this->mailboxOverride = $sub->mailbox_limit_override === null ? '' : (string) $sub->mailbox_limit_override;
        $this->extraMailboxes = (string) (int) $sub->extra_mailboxes;
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
        $this->reset(['editingId', 'prices', 'mailboxOverride', 'extraMailboxes']);
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
        $this->validate([
            'mailboxOverride' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'extraMailboxes' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], ['mailboxOverride.integer' => 'Whole number, or blank for the plan default.']);
        $user->currentSubscription()->update([
            'price_overrides' => $overrides ?: null,
            'mailbox_limit_override' => trim((string) $this->mailboxOverride) === '' ? null : (int) $this->mailboxOverride,
            'extra_mailboxes' => (int) ($this->extraMailboxes ?: 0),
        ]);
        AccountActivity::record($user->id, 'email.limits_set', 'Mailbox allowance updated by the platform',
            ['actor_id' => Auth::id(), 'category' => 'email', 'icon' => 'envelope', 'meta' => ['override' => $this->mailboxOverride, 'extra' => $this->extraMailboxes]]);
        $this->dispatch('toast', level: 'success', title: 'Saved', message: 'Pricing and mailbox allowance updated for '.$user->name.'.');
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
            if ($blocker = app(PlatformBilling::class)->downgradeBlocker($user, $plan)) {
                $this->dispatch('toast', level: 'error', title: 'Can\'t switch plan yet', message: $blocker);

                return;
            }
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

        $subs = AccountSubscription::query();
        $summary = [
            'accounts' => User::count(),
            'new_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'paying' => (clone $subs)->where('status', 'active')->count(),
            'trialing' => (clone $subs)->where('status', 'trialing')->count(),
            'custom' => (clone $subs)->whereNotNull('price_overrides')->count(),
            'plans' => (clone $subs)->selectRaw('plan, count(*) as n')->groupBy('plan')->pluck('n', 'plan'),
        ];

        return view('livewire.platform-accounts-page', ['accounts' => $accounts, 'summary' => $summary]);
    }
}
