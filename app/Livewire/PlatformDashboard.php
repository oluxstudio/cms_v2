<?php

namespace App\Livewire;

use App\Models\AccountActivityLog;
use App\Models\AccountSubscription;
use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Component as ComponentModel;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Platform dashboard (super admins): account + site totals, "active in the
 * last 10 days", CMS-wide content/commerce counts, plan mix, newest signups
 * and the latest account activity across the whole platform.
 */
class PlatformDashboard extends Component
{
    public const ACTIVE_DAYS = 10;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
    }

    /** Unique users who acted in the window: audit-log actors ∪ API-token users. */
    protected function activeAccountIds(): array
    {
        $since = now()->subDays(self::ACTIVE_DAYS);

        return AccountActivityLog::where('created_at', '>=', $since)
            ->whereNotNull('actor_id')->distinct()->pluck('actor_id')
            ->merge(ApiToken::where('last_used_at', '>=', $since)->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->unique()->values()->all();
    }

    public function render()
    {
        $monthStart = now()->startOfMonth();

        $stats = [
            'accounts' => User::count(),
            'accounts_new' => User::where('created_at', '>=', $monthStart)->count(),
            'accounts_verified' => User::whereNotNull('email_verified_at')->count(),
            'active' => count($this->activeAccountIds()),
            'sites' => Site::count(),
            'sites_live' => Site::where('live', true)->count(),
            'sites_new' => Site::where('created_at', '>=', $monthStart)->count(),
            'storage_bytes' => (int) Media::sum('bytes'),
            'pages' => Page::count(),
            'components' => ComponentModel::count(),
            'posts' => Post::count(),
            'media' => Media::count(),
            'forms' => Form::count(),
            'responses' => FormResponse::count(),
            'contacts' => Contact::count(),
            'bookings' => Booking::count(),
            'orders' => Order::count(),
            'donations' => Donation::count(),
            'visits_30d' => Visit::humans()->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Plan mix: subscription rows per tier (accounts without one = trial).
        $planCounts = AccountSubscription::query()
            ->selectRaw('plan, count(*) as n')->groupBy('plan')->pluck('n', 'plan');
        $plans = collect(config('plans.tiers'))->map(fn ($tier, $key) => [
            'name' => $tier['name'],
            'color' => $tier['color'],
            'count' => (int) ($planCounts[$key] ?? 0),
        ])->values();
        $planMax = max(1, $plans->max('count'));

        // ── Money: estimated MRR (active subs, honouring per-client overrides),
        // GMV and invoice collections across every tenant store.
        $since30 = now()->subDays(30);
        $activeSubs = AccountSubscription::where('status', 'active')->get();
        $money = [
            'mrr_cents' => (int) $activeSubs->sum(fn ($s) => $s->priceFor($s->plan)),
            'paying' => $activeSubs->count(),
            'trialing' => AccountSubscription::where('status', 'trialing')->count(),
            'gmv_30d_cents' => (int) Order::whereIn('status', ['paid', 'shipped', 'delivered', 'fulfilled'])
                ->where('paid_at', '>=', $since30)->sum('total_cents'),
            'invoices_30d_cents' => (int) Invoice::where('status', 'paid')
                ->where('updated_at', '>=', $since30)->sum('total_cents'),
        ];

        // ── Charts: signups/day + cumulative storage growth (30 days).
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $signupRows = User::where('created_at', '>=', $since30->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
        $baseBytes = (int) Media::where('created_at', '<', $since30->copy()->startOfDay())->sum('bytes');
        $storageRows = Media::where('created_at', '>=', $since30->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as d, sum(bytes) as b')->groupBy('d')->pluck('b', 'd');
        $running = $baseBytes;
        $charts = [
            'labels' => $days->map(fn ($d) => date('j M', strtotime($d)))->all(),
            'signups' => $days->map(fn ($d) => (int) ($signupRows[$d] ?? 0))->all(),
            'storage_mb' => $days->map(function ($d) use (&$running, $storageRows) {
                $running += (int) ($storageRows[$d] ?? 0);

                return round($running / 1048576, 1);
            })->all(),
            'plan_labels' => $plans->pluck('name')->all(),
            'plan_series' => $plans->pluck('count')->all(),
            'plan_colors' => $plans->pluck('color')->all(),
        ];

        // ── Lists: heaviest accounts by storage (upsell radar), trials about
        // to expire, and the busiest accounts by traffic.
        $storageByAccount = Media::join('sites', 'sites.id', '=', 'media.site_id')
            ->selectRaw('sites.user_id as uid, sum(media.bytes) as b')
            ->groupBy('uid')->orderByDesc('b')->limit(8)->pluck('b', 'uid');
        $topStorage = User::with('subscription')->findMany($storageByAccount->keys())
            ->map(function ($u) use ($storageByAccount) {
                $limit = $u->currentSubscription()->storageLimitBytes();

                return [
                    'user' => $u,
                    'bytes' => (int) $storageByAccount[$u->id],
                    'limit' => $limit,
                    'pct' => $limit ? min(100, (int) round($storageByAccount[$u->id] / $limit * 100)) : null,
                ];
            })->sortByDesc('bytes')->values();

        $expiringTrials = AccountSubscription::with('user')
            ->where('status', 'trialing')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->orderBy('trial_ends_at')->take(8)->get();

        $visitsByAccount = Visit::humans()->where('visits.created_at', '>=', $since30)
            ->join('sites', 'sites.id', '=', 'visits.site_id')
            ->selectRaw('sites.user_id as uid, count(*) as n')
            ->groupBy('uid')->orderByDesc('n')->limit(6)->pluck('n', 'uid');
        $topVisits = User::findMany($visitsByAccount->keys())
            ->mapWithKeys(fn ($u) => [$u->name => (int) $visitsByAccount[$u->id]])
            ->sortDesc()->all();

        return view('livewire.platform-dashboard', [
            'stats' => $stats,
            'plans' => $plans,
            'planMax' => $planMax,
            'money' => $money,
            'charts' => $charts,
            'topStorage' => $topStorage,
            'expiringTrials' => $expiringTrials,
            'topVisits' => $topVisits,
            'signups' => User::latest()->take(8)->withCount('sites')->get(),
            'feed' => AccountActivityLog::with('actor')->latest()->take(15)->get(),
        ]);
    }
}
