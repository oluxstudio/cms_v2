<?php

namespace App\Livewire;

use App\Models\AiUsage;
use App\Models\User;
use App\Services\AccountActivity;
use App\Services\AiQuota;
use App\Services\SiteAgent;
use App\Support\ConfigOverlay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Platform admin › AI usage: tokens and estimated cost by account, site and
 * model; the master on/off switch; the price table behind the estimates.
 * Monthly allowances per plan are set on the Plans page.
 */
class PlatformAiPage extends Component
{
    public const TABS = ['accounts', 'sites', 'models'];

    #[Url(as: 'tab')]
    public string $tab = 'accounts';

    /** Price editor rows: [['model' => …, 'in' => …, 'out' => …]] */
    public array $prices = [];

    public ?bool $reachable = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'accounts';
        }
        $this->loadPrices();
    }

    private function loadPrices(): void
    {
        $this->prices = collect((array) config('services.llm.prices'))
            ->map(fn ($p, $model) => ['model' => (string) $model, 'in' => (string) ($p[0] ?? 0), 'out' => (string) ($p[1] ?? 0)])
            ->values()->all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'accounts';
    }

    public function toggleEnabled(): void
    {
        $on = ! config('services.llm.enabled', true);
        ConfigOverlay::set('services.llm.enabled', $on);
        AccountActivity::record(Auth::id(), $on ? 'ai.enabled' : 'ai.disabled', $on ? 'Turned the AI assistant on' : 'Turned the AI assistant off', ['category' => 'Security']);
        $this->dispatch('toast', level: 'success', title: $on ? 'AI on' : 'AI off',
            message: $on ? 'The assistant is available to clients again.' : 'The assistant now shows as unavailable everywhere.');
    }

    public function checkReachable(): void
    {
        $this->reachable = SiteAgent::reachable();
    }

    public function addPrice(): void
    {
        $this->prices[] = ['model' => '', 'in' => '', 'out' => ''];
    }

    public function removePrice(int $i): void
    {
        unset($this->prices[$i]);
        $this->prices = array_values($this->prices);
    }

    public function savePrices(): void
    {
        $this->prices = array_values(array_filter($this->prices, fn ($r) => trim((string) $r['model']) !== ''));
        $this->validate([
            'prices.*.model' => ['required', 'string', 'max:80', 'distinct'],
            'prices.*.in' => ['required', 'numeric', 'min:0', 'max:1000'],
            'prices.*.out' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);
        $map = [];
        foreach ($this->prices as $r) {
            $map[trim($r['model'])] = [(float) $r['in'], (float) $r['out']];
        }
        ConfigOverlay::set('services.llm.prices', $map);
        $this->loadPrices();
        $this->dispatch('toast', level: 'success', title: 'Prices saved', message: 'Cost estimates now use these prices.');
    }

    /** Estimated USD cost of token counts for a model. */
    public static function cost(?string $model, int $in, int $out): float
    {
        [$pIn, $pOut] = (array) (config('services.llm.prices')[(string) $model] ?? [0, 0]) + [0, 0];

        return ($in * (float) $pIn + $out * (float) $pOut) / 1_000_000;
    }

    public function render(AiQuota $quota)
    {
        $monthStart = now()->startOfMonth();
        $lastMonth = [now()->subMonthNoOverflow()->startOfMonth(), $monthStart];

        $byModel = AiUsage::where('created_at', '>=', $monthStart)
            ->selectRaw('model, count(*) as turns, sum(input_tokens) as tin, sum(output_tokens) as tout')
            ->groupBy('model')->get()
            ->map(fn ($r) => ['model' => $r->model ?: 'unknown', 'turns' => (int) $r->turns, 'in' => (int) $r->tin, 'out' => (int) $r->tout,
                'cost' => self::cost($r->model, (int) $r->tin, (int) $r->tout)]);
        $lastCost = AiUsage::whereBetween('created_at', $lastMonth)
            ->selectRaw('model, sum(input_tokens) as tin, sum(output_tokens) as tout')->groupBy('model')->get()
            ->sum(fn ($r) => self::cost($r->model, (int) $r->tin, (int) $r->tout));

        // Per account (site owner) this month, with their plan allowance.
        $accounts = AiUsage::where('ai_usage.created_at', '>=', $monthStart)
            ->join('sites', 'sites.id', '=', 'ai_usage.site_id')
            ->selectRaw('sites.user_id as uid, count(*) as turns, sum(input_tokens) as tin, sum(output_tokens) as tout')
            ->groupBy('uid')->orderByDesc(DB::raw('sum(input_tokens) + sum(output_tokens)'))->limit(50)->get();
        $users = User::with('subscription')->findMany($accounts->pluck('uid'))->keyBy('id');
        $accountRows = $accounts->map(function ($r) use ($users, $quota) {
            $u = $users[$r->uid] ?? null;
            $limit = $u ? $quota->limitFor($u) : null;
            $used = (int) $r->tin + (int) $r->tout;

            return ['user' => $u, 'turns' => (int) $r->turns, 'tokens' => $used, 'limit' => $limit,
                'pct' => $limit ? min(100, (int) round($used / max(1, $limit) * 100)) : null];
        })->filter(fn ($r) => $r['user'])->values();

        $siteRows = $this->tab === 'sites'
            ? AiUsage::where('ai_usage.created_at', '>=', $monthStart)
                ->join('sites', 'sites.id', '=', 'ai_usage.site_id')
                ->selectRaw('sites.name as site, sites.user_id as uid, count(*) as turns, sum(input_tokens) + sum(output_tokens) as tokens')
                ->groupBy('sites.name', 'sites.user_id')->orderByDesc('tokens')->limit(50)->get()
            : collect();

        $daily = AiUsage::where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as d, sum(input_tokens) + sum(output_tokens) as t')->groupBy('d')->pluck('t', 'd');
        $days = collect(range(29, 0))->map(fn ($i) => ['d' => now()->subDays($i), 't' => (int) ($daily[now()->subDays($i)->toDateString()] ?? 0)]);

        $driver = (string) config('services.llm.driver', 'anthropic');

        return view('livewire.platform-ai-page', [
            'stats' => [
                'tokens' => (int) $byModel->sum('in') + (int) $byModel->sum('out'),
                'turns' => (int) $byModel->sum('turns'),
                'cost' => (float) $byModel->sum('cost'),
                'last_cost' => (float) $lastCost,
                'at_cap' => $accountRows->filter(fn ($r) => $r['limit'] !== null && $r['tokens'] >= $r['limit'])->count(),
            ],
            'byModel' => $byModel->sortByDesc('cost')->values(),
            'accountRows' => $accountRows,
            'siteRows' => $siteRows,
            'days' => $days,
            'dayMax' => max(1, $days->max('t')),
            'enabled' => (bool) config('services.llm.enabled', true),
            'configured' => SiteAgent::hasCredentials(),
            'driver' => $driver,
            'model' => (string) config("services.{$driver}.model"),
            'perHour' => (int) config('services.llm.per_hour', 30),
            'planCaps' => collect(config('plans.tiers'))->map(fn ($t) => ['name' => $t['name'] ?? '', 'cap' => $t['limits']['ai_tokens_month'] ?? null])->values(),
        ]);
    }
}
