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

        return view('livewire.platform-dashboard', [
            'stats' => $stats,
            'plans' => $plans,
            'planMax' => $planMax,
            'signups' => User::latest()->take(8)->withCount('sites')->get(),
            'feed' => AccountActivityLog::with('actor')->latest()->take(15)->get(),
        ]);
    }
}
