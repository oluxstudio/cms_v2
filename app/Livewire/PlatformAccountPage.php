<?php

namespace App\Livewire;

use App\Models\AccountActivityLog;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Media;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteActivityLog;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Super-admin account detail: profile + subscription summary, the account's
 * sites, and a day-grouped diary/timeline merging the account audit trail
 * (logins, security, team, API) with content activity across their sites.
 */
class PlatformAccountPage extends Component
{
    public string $userId = '';

    public int $limit = 30;

    /** all | account | content | api */
    public string $filter = 'all';

    public function mount(string $userId): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $this->userId = User::findOrFail($userId)->id;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'account', 'content', 'api']) ? $filter : 'all';
        $this->limit = 30;
    }

    public function loadMore(): void
    {
        $this->limit += 30;
    }

    public function render()
    {
        $user = User::with('subscription')->findOrFail($this->userId);
        $sub = $user->currentSubscription();
        $siteIds = $user->sites()->pluck('id');

        // Two sources, one stream: over-fetch $limit from each, merge, sort,
        // slice. "+1" tells us whether a Load-more button is warranted.
        $account = collect();
        if ($this->filter !== 'content') {
            $account = AccountActivityLog::where('account_id', $user->id)
                ->when($this->filter === 'api', fn ($q) => $q->where('category', 'API'))
                ->when($this->filter === 'account', fn ($q) => $q->where('category', '!=', 'API'))
                ->latest()->take($this->limit + 1)->get();
        }
        $content = collect();
        if (in_array($this->filter, ['all', 'content']) && $siteIds->isNotEmpty()) {
            $content = SiteActivityLog::whereIn('site_id', $siteIds)
                ->with('site')->latest()->take($this->limit + 1)->get();
        }

        $entries = $account->map(fn ($log) => ['kind' => 'account', 'log' => $log, 'at' => $log->created_at])
            ->concat($content->map(fn ($log) => ['kind' => 'site', 'log' => $log, 'at' => $log->created_at]))
            ->sortByDesc('at')->values();

        $hasMore = $entries->count() > $this->limit;
        $days = $entries->take($this->limit)
            ->groupBy(fn ($e) => $e['at']->toDateString());

        $lastSeen = AccountActivityLog::where('account_id', $user->id)->latest()->value('created_at');

        // ── Per-site usage: one grouped query per metric (never per-site loops).
        $per = fn ($query, string $col = '*') => $siteIds->isEmpty() ? collect() : $query
            ->whereIn('site_id', $siteIds)
            ->selectRaw('site_id, '.($col === '*' ? 'count(*)' : $col).' as v')
            ->groupBy('site_id')->pluck('v', 'site_id');

        $since30 = now()->subDays(30);
        $usage = [
            'media_bytes' => $per(Media::query(), 'coalesce(sum(bytes),0)'),
            'products' => $per(Product::query()),
            'bookings' => $per(Booking::query()),
            'orders' => $per(Order::query()),
            'revenue_cents' => $per(Order::whereIn('status', ['paid', 'shipped', 'delivered', 'fulfilled']), 'coalesce(sum(total_cents),0)'),
            'contacts' => $per(Contact::query()),
            'messages' => $per(Message::query()),
            'visits_30d' => $per(Visit::humans()->where('created_at', '>=', $since30)),
        ];

        // Account traffic sparkline (30 days) for the Apex chart.
        $chartDays = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $visitRows = $siteIds->isEmpty() ? collect() : Visit::humans()
            ->whereIn('site_id', $siteIds)->where('created_at', '>=', $since30->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
        $charts = [
            'labels' => $chartDays->map(fn ($d) => date('j M', strtotime($d)))->all(),
            'visits' => $chartDays->map(fn ($d) => (int) ($visitRows[$d] ?? 0))->all(),
        ];

        return view('livewire.platform-account-page', [
            'usage' => $usage,
            'charts' => $charts,
            'user' => $user,
            'sub' => $sub,
            'sites' => $user->sites()->withCount(['pages', 'media'])->get(),
            'memberSites' => $user->memberSites()->get(),
            'tokens' => $user->apiTokens()->latest('last_used_at')->get(),
            'lastSeen' => $lastSeen,
            'days' => $days,
            'hasMore' => $hasMore,
        ]);
    }
}
