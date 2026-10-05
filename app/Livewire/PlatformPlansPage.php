<?php

namespace App\Livewire;

use App\Models\AccountSubscription;
use App\Models\MembershipPlan;
use App\Support\PlanCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Platform admin › Membership plans: edit prices, limits, features and copy
 * for every tier, add new ones, hide or reorder them. Saved plans overlay
 * config/plans.php (see PlanCatalog), so billing, the subscription page and
 * the landing page all pick the changes up.
 */
class PlatformPlansPage extends Component
{
    public const ACCENTS = ['primary', 'lime', 'lavender', 'cocoa', 'sky'];

    /** Plan key being edited; '' = a new plan. null = drawer closed. */
    public ?string $editing = null;

    public array $form = [];

    public int $trialDays = 14;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $this->trialDays = (int) config('plans.trial_days', 14);
    }

    public function edit(string $key): void
    {
        $this->guard();
        $t = config("plans.tiers.{$key}");
        abort_unless($t, 404);
        $this->editing = $key;
        $this->form = [
            'key' => $key,
            'name' => (string) ($t['name'] ?? ''),
            'tagline' => (string) ($t['tagline'] ?? ''),
            'price' => number_format(((int) ($t['price_cents'] ?? 0)) / 100, 2, '.', ''),
            'color' => (string) ($t['color'] ?? '#6366f1'),
            'accent' => (string) ($t['accent'] ?? 'primary'),
            'highlight' => (bool) ($t['highlight'] ?? false),
            'hidden' => (bool) ($t['hidden'] ?? false),
            'domain_included' => (bool) ($t['domain_included'] ?? false),
            'sites' => $t['limits']['sites'] ?? null,
            'storage_mb' => $t['limits']['storage_mb'] ?? null,
            'premium' => (bool) ($t['limits']['premium'] ?? false),
            'marketplace' => (bool) ($t['limits']['marketplace'] ?? false),
            'ai_tokens' => $t['limits']['ai_tokens_month'] ?? null,
            'mailboxes' => array_key_exists('mailboxes', $t['limits'] ?? []) ? $t['limits']['mailboxes'] : 0,
            'custom_domain' => (bool) ($t['limits']['custom_domain'] ?? true),
            'annual_price' => isset($t['annual_price_cents']) ? number_format(((int) $t['annual_price_cents']) / 100, 2, '.', '') : '',
            'price_prefix' => (string) ($t['price_prefix'] ?? ''),
            'free_domain' => (string) ($t['limits']['free_domain'] ?? ''),
            'bookings_month' => $t['limits']['bookings_month'] ?? null,
            'staff_calendars' => $t['limits']['staff_calendars'] ?? null,
            'invoices_month' => $t['limits']['invoices_month'] ?? null,
            'deposits' => (bool) ($t['limits']['deposits'] ?? true),
            'recurring_invoices' => (bool) ($t['limits']['recurring_invoices'] ?? true),
            'payment_fee_pct' => $t['limits']['payment_fee_pct'] ?? null,
            'badge' => (bool) ($t['limits']['badge'] ?? false),
            'features' => implode("\n", (array) ($t['features'] ?? [])),
            'description' => (string) ($t['description'] ?? ''),
        ];
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->guard();
        $this->editing = '';
        $this->form = [
            'key' => '', 'name' => '', 'tagline' => '', 'price' => '', 'color' => '#6366f1', 'accent' => 'primary',
            'highlight' => false, 'hidden' => false, 'domain_included' => false,
            'sites' => 1, 'storage_mb' => 1024, 'premium' => false, 'marketplace' => false, 'ai_tokens' => null, 'mailboxes' => 0, 'custom_domain' => true,
            'annual_price' => '', 'price_prefix' => '', 'free_domain' => '', 'bookings_month' => null, 'staff_calendars' => 1,
            'invoices_month' => null, 'deposits' => false, 'recurring_invoices' => false, 'payment_fee_pct' => 1, 'badge' => false,
            'features' => '', 'description' => '',
        ];
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->editing = null;
    }

    public function save(): void
    {
        $this->guard();
        $isNew = $this->editing === '';
        $isTrial = $this->editing === 'trial';
        if ($isNew) {
            $this->form['key'] = Str::slug((string) ($this->form['key'] ?: $this->form['name']), '_');
        }
        $this->validate([
            'form.key' => $isNew
                ? ['required', 'alpha_dash', 'max:40', 'not_in:'.MembershipPlan::SETTINGS_KEY, fn ($a, $v, $fail) => config("plans.tiers.{$v}") ? $fail('A plan with this key already exists.') : null]
                : ['required'],
            'form.name' => ['required', 'string', 'max:40'],
            'form.tagline' => ['nullable', 'string', 'max:80'],
            'form.price' => $isTrial ? ['nullable'] : ['required', 'numeric', 'min:0', 'max:100000'],
            'form.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.accent' => ['required', 'in:'.implode(',', self::ACCENTS)],
            'form.sites' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'form.storage_mb' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'form.ai_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            'form.mailboxes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'form.annual_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'form.price_prefix' => ['nullable', 'string', 'max:12'],
            'form.free_domain' => ['nullable', 'in:,co.uk,uk,com,any'],
            'form.bookings_month' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'form.staff_calendars' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'form.invoices_month' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'form.payment_fee_pct' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'form.features' => ['nullable', 'string', 'max:2000'],
            'form.description' => ['nullable', 'string', 'max:3000'],
        ], [
            'form.name.required' => 'Give the plan a name.',
            'form.price.required' => 'Set a monthly price (0 for free).',
            'form.color.regex' => 'Use a hex colour like #6366f1.',
        ]);

        $key = $this->form['key'];
        $current = config("plans.tiers.{$key}", []);
        $order = $current['order'] ?? (collect(config('plans.tiers'))->max('order') + 1);
        $data = [
            'name' => trim($this->form['name']),
            'tagline' => trim((string) $this->form['tagline']),
            'price_cents' => $isTrial ? 0 : (int) round(((float) $this->form['price']) * 100),
            'annual_price_cents' => $isTrial || ($this->form['annual_price'] ?? '') === '' ? null : (int) round(((float) $this->form['annual_price']) * 100),
            'price_prefix' => trim((string) ($this->form['price_prefix'] ?? '')) ?: null,
            'domain_included' => (bool) $this->form['domain_included'],
            'order' => (int) $order,
            'color' => strtolower($this->form['color']),
            'accent' => $this->form['accent'],
            'highlight' => (bool) $this->form['highlight'],
            'hidden' => $isTrial ? false : (bool) $this->form['hidden'],
            'limits' => [
                'sites' => $this->form['sites'] === null || $this->form['sites'] === '' ? null : (int) $this->form['sites'],
                'premium' => (bool) $this->form['premium'],
                'storage_mb' => $this->form['storage_mb'] === null || $this->form['storage_mb'] === '' ? null : (int) $this->form['storage_mb'],
                'marketplace' => (bool) $this->form['marketplace'],
                'ai_tokens_month' => $this->form['ai_tokens'] === null || $this->form['ai_tokens'] === '' ? null : (int) $this->form['ai_tokens'],
                // null = set per account by platform admins (Enterprise).
                'mailboxes' => $isTrial ? 0 : ($this->form['mailboxes'] === null || $this->form['mailboxes'] === '' ? null : (int) $this->form['mailboxes']),
                'custom_domain' => (bool) ($this->form['custom_domain'] ?? true),
                'free_domain' => ($this->form['free_domain'] ?? '') ?: null,
                'bookings_month' => $this->blankToNull('bookings_month'),
                'staff_calendars' => $this->blankToNull('staff_calendars'),
                'invoices_month' => $this->blankToNull('invoices_month'),
                'deposits' => (bool) ($this->form['deposits'] ?? true),
                'recurring_invoices' => (bool) ($this->form['recurring_invoices'] ?? true),
                'payment_fee_pct' => ($this->form['payment_fee_pct'] ?? '') === '' || $this->form['payment_fee_pct'] === null ? null : round((float) $this->form['payment_fee_pct'], 2),
                'badge' => (bool) ($this->form['badge'] ?? false),
            ],
            'features' => collect(preg_split('/\r?\n/', (string) $this->form['features']))->map(fn ($l) => trim($l))->filter()->values()->all(),
            'description' => trim((string) $this->form['description']),
        ];

        // Only one recommended plan at a time.
        if ($data['highlight']) {
            foreach (config('plans.tiers') as $k => $t) {
                if ($k !== $key && ! empty($t['highlight'])) {
                    PlanCatalog::save($k, array_merge($t, ['highlight' => false]));
                }
            }
        }
        PlanCatalog::save($key, $data);

        $this->editing = null;
        $this->dispatch('toast', level: 'success', title: $isNew ? 'Plan created' : 'Plan saved',
            message: $data['name'].' is updated everywhere. New checkouts use the new price; current subscribers keep theirs.');
    }

    /** A number field where blank means unlimited. */
    private function blankToNull(string $field): ?int
    {
        $v = $this->form[$field] ?? null;

        return $v === null || $v === '' ? null : (int) $v;
    }

    public function toggleHidden(string $key): void
    {
        $this->guard();
        abort_if($key === 'trial', 422);
        $t = config("plans.tiers.{$key}");
        abort_unless($t, 404);
        PlanCatalog::save($key, array_merge($t, ['hidden' => empty($t['hidden'])]));
    }

    /** Swap display order with the neighbour above/below. */
    public function move(string $key, int $dir): void
    {
        $this->guard();
        $keys = collect(config('plans.tiers'))->sortBy('order')->keys()->values();
        $i = $keys->search($key);
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= $keys->count()) {
            return;
        }
        $keys = $keys->all();
        [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
        foreach ($keys as $n => $k) {
            PlanCatalog::save($k, array_merge(config("plans.tiers.{$k}"), ['order' => $n + 1]));
        }
    }

    /** Only plans added here, with nobody on them, can be deleted. */
    /** Delete dialog: the plan being deleted, and where its accounts move to. */
    public ?string $deleting = null;

    public string $moveTo = '';

    public function startDelete(string $key): void
    {
        $this->guard();
        abort_unless(config("plans.tiers.{$key}") && $key !== 'trial', 422);
        $this->deleting = $key;
        $this->moveTo = '';
        $this->resetErrorBag();
    }

    public function cancelDelete(): void
    {
        $this->reset('deleting', 'moveTo');
    }

    /**
     * Delete a plan — any plan except the signup trial. Accounts on it are
     * moved to another plan first (never left on a plan that no longer
     * exists). A built-in plan keeps a "deleted" marker so it can be restored.
     */
    public function deletePlan(): void
    {
        $this->guard();
        $this->resetErrorBag();
        $key = (string) $this->deleting;
        $tier = config("plans.tiers.{$key}");
        abort_unless($tier && $key !== 'trial', 422);

        $accounts = AccountSubscription::where('plan', $key)->count();
        if ($accounts > 0) {
            if ($this->moveTo === '' || $this->moveTo === $key || ! config("plans.tiers.{$this->moveTo}")) {
                $this->addError('moveTo', 'Pick the plan '.($accounts === 1 ? 'its account moves' : "its {$accounts} accounts move").' to.');

                return;
            }
            AccountSubscription::where('plan', $key)->update(['plan' => $this->moveTo]);
        }

        PlanCatalog::isBuiltIn($key)
            ? PlanCatalog::save($key, array_merge((array) (MembershipPlan::where('key', $key)->value('data') ?? []), ['deleted' => true, 'hidden' => true, 'highlight' => false]))
            : PlanCatalog::delete($key);

        $name = $tier['name'] ?? $key;
        $moved = $accounts > 0 ? ' Its '.$accounts.' '.Str::plural('account', $accounts).' moved to '.(config("plans.tiers.{$this->moveTo}.name") ?? $this->moveTo).'.' : '';
        $this->reset('deleting', 'moveTo', 'editing');
        $this->dispatch('toast', level: 'success', title: 'Plan deleted', message: $name.' is gone from every pricing page.'.$moved);
    }

    /** Bring back a deleted built-in plan (with the edits it had). */
    public function restorePlan(string $key): void
    {
        $this->guard();
        abort_unless(PlanCatalog::isBuiltIn($key), 422);
        $data = (array) (MembershipPlan::where('key', $key)->value('data') ?? []);
        unset($data['deleted']);
        $data['hidden'] = true; // comes back hidden — show it when ready
        PlanCatalog::save($key, $data);
        $this->dispatch('toast', level: 'success', title: 'Plan restored', message: (config("plans.tiers.{$key}.name") ?? $key).' is back (hidden — click Show to offer it again).');
    }

    /** Every action re-checks: only super admins manage plans. */
    private function guard(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    public function saveTrialDays(): void
    {
        $this->guard();
        $this->validate(['trialDays' => ['required', 'integer', 'min:0', 'max:90']]);
        $settings = MembershipPlan::where('key', MembershipPlan::SETTINGS_KEY)->value('data') ?? [];
        PlanCatalog::save(MembershipPlan::SETTINGS_KEY, array_merge((array) $settings, ['trial_days' => $this->trialDays]));
        $this->dispatch('toast', level: 'success', title: 'Saved', message: "New trials last {$this->trialDays} days.");
    }

    public function render()
    {
        $tiers = collect(config('plans.tiers'))->sortBy('order');
        $counts = AccountSubscription::selectRaw('plan, status, count(*) as n')->groupBy('plan', 'status')->get()
            ->groupBy('plan');
        $active = AccountSubscription::where('status', 'active')->get();
        $mrrByPlan = $active->groupBy('plan')->map(fn ($subs) => (int) $subs->sum(fn ($s) => $s->priceFor($s->plan)));

        $rows = $tiers->map(fn ($t, $key) => [
            'key' => $key,
            'tier' => $t,
            'subscribers' => (int) ($counts[$key] ?? collect())->sum('n'),
            'paying' => (int) (($counts[$key] ?? collect())->firstWhere('status', 'active')->n ?? 0),
            'mrr' => (int) ($mrrByPlan[$key] ?? 0),
            'builtIn' => PlanCatalog::isBuiltIn($key),
            'edited' => MembershipPlan::where('key', $key)->exists(),
        ]);

        return view('livewire.platform-plans-page', [
            'rows' => $rows,
            'stats' => [
                'plans' => $tiers->count(),
                'visible' => $tiers->filter(fn ($t) => empty($t['hidden']))->count(),
                'paying' => $active->count(),
                'mrr' => (int) $mrrByPlan->sum(),
                'trialing' => AccountSubscription::where('status', 'trialing')->count(),
            ],
            'accents' => self::ACCENTS,
            'deletedPlans' => PlanCatalog::deletedBuiltIns(),
        ]);
    }
}
