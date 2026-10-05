<?php

namespace App\Livewire;

use App\Models\DomainOrder;
use App\Models\Site;
use App\Services\AccountActivity;
use App\Services\Domains\DomainPurchase;
use App\Services\Domains\DomainVerifier;
use App\Support\ConfigOverlay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Platform admin › Domains: every domain order (retry failed registrations),
 * every connected custom domain (re-check DNS, take offline), and the TLD
 * price list.
 */
class PlatformDomainsPage extends Component
{
    use WithPagination;

    public const TABS = ['orders', 'connected', 'prices'];

    #[Url(as: 'tab')]
    public string $tab = 'orders';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'q')]
    public string $q = '';

    /** Price editor rows: [['tld' => 'co.uk', 'price' => '9.99'], …] */
    public array $prices = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'orders';
        }
        $this->loadPrices();
    }

    private function loadPrices(): void
    {
        $this->prices = collect((array) config('domains.tlds'))
            ->map(fn ($cfg, $tld) => ['tld' => (string) $tld, 'price' => number_format(((int) ($cfg['price_cents'] ?? 0)) / 100, 2, '.', '')])
            ->values()->all();
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['q', 'status', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'orders';
        $this->resetPage();
    }

    /** Re-attempt a failed registration or renewal (payment was already taken). */
    public function retry(string $orderId, DomainPurchase $purchase): void
    {
        $order = DomainOrder::where('status', 'failed')->findOrFail($orderId);
        $order->update(['error' => null]);
        try {
            $purchase->fulfil($order);
        } catch (Throwable $e) {
            report($e);
            $order->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        }
        $order->refresh();
        AccountActivity::record($order->user_id, 'domain.retry', 'Domain order retried by support', [
            'category' => 'Sites', 'meta' => ['order_id' => $order->id, 'domain' => $order->domain, 'result' => $order->status],
        ]);
        $order->status === 'registered'
            ? $this->dispatch('toast', level: 'success', title: 'Registered', message: $order->domain.' is registered now.')
            : $this->dispatch('toast', level: 'error', title: 'Still failing', message: $order->error ?: 'The registrar refused it again.');
    }

    /** Record that the customer was refunded in Stripe for an order that can't be fulfilled. */
    public function markRefunded(string $orderId): void
    {
        $order = DomainOrder::whereIn('status', ['failed', 'paid', 'pending'])->findOrFail($orderId);
        $order->update(['status' => 'refunded', 'error' => trim(($order->error ? $order->error."\n" : '').'Refunded by '.Auth::user()->name.' on '.now()->format('j M Y'))]);
        AccountActivity::record($order->user_id, 'domain.refunded', 'Domain order refunded', [
            'category' => 'Sites', 'meta' => ['order_id' => $order->id, 'domain' => $order->domain],
        ]);
        $this->dispatch('toast', level: 'success', title: 'Marked refunded', message: 'Remember to refund the payment in Stripe if you haven\'t.');
    }

    public function recheck(string $siteId, DomainVerifier $verifier): void
    {
        $site = Site::findOrFail($siteId);
        $target = (string) config('publishing.dns_target');
        if ($target === '' || ! $site->domain) {
            $this->dispatch('toast', level: 'error', title: 'Can\'t check', message: $target === '' ? 'PLATFORM_DNS_TARGET is not set on this server.' : 'This site has no domain.');

            return;
        }
        $result = $verifier->check($site, $target);
        Cache::put('admin-domain-check:'.$site->id, $result + ['at' => now()->toIso8601String()], now()->addDay());
        $this->dispatch('toast', level: $result['records'] && $result['ownership'] ? 'success' : 'error', title: $site->domain,
            message: ($result['records'] ? 'Points here' : 'Not pointing here').' · '.($result['ownership'] ? 'ownership confirmed' : 'no ownership record').' · '.($result['ssl'] ? 'HTTPS works' : 'no HTTPS yet'));
    }

    public function takeOffline(string $siteId): void
    {
        $site = Site::findOrFail($siteId);
        $site->update(['live' => false]);
        AccountActivity::record($site->user_id, 'site.offline', 'Site taken offline by support', [
            'category' => 'Sites', 'meta' => ['site' => $site->name, 'domain' => $site->domain],
        ]);
        $this->dispatch('toast', level: 'success', title: 'Offline', message: $site->name.' is no longer served on its domain.');
    }

    public function addTld(): void
    {
        $this->prices[] = ['tld' => '', 'price' => ''];
    }

    public function removeTld(int $i): void
    {
        unset($this->prices[$i]);
        $this->prices = array_values($this->prices);
    }

    public function savePrices(): void
    {
        $this->prices = array_values(array_filter($this->prices, fn ($r) => trim((string) $r['tld']) !== '' || trim((string) $r['price']) !== ''));
        foreach ($this->prices as $i => $r) {
            $this->prices[$i]['tld'] = ltrim(strtolower(trim((string) $r['tld'])), '.');
        }
        $this->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.tld' => ['required', 'regex:/^[a-z0-9-]+(\.[a-z0-9-]+)?$/', 'distinct'],
            'prices.*.price' => ['required', 'numeric', 'min:0.5', 'max:10000'],
        ], [
            'prices.*.tld.regex' => 'Use an ending like co.uk or com.',
            'prices.*.tld.distinct' => 'Listed twice.',
            'prices.*.price.required' => 'Set a price.',
        ]);
        $map = [];
        foreach ($this->prices as $r) {
            $map[$r['tld']] = ['price_cents' => (int) round(((float) $r['price']) * 100)];
        }
        ConfigOverlay::set('domains.tlds', $map);
        $this->loadPrices();
        $this->dispatch('toast', level: 'success', title: 'Prices saved', message: count($map).' domain endings on sale. New searches use these prices.');
    }

    public function resetPrices(): void
    {
        ConfigOverlay::forget('domains.tlds');
        $this->loadPrices();
        $this->dispatch('toast', level: 'success', title: 'Reset', message: 'Back to the default price list.');
    }

    public function render()
    {
        $since30 = now()->subDays(30);
        $stats = [
            'registered' => DomainOrder::where('type', 'register')->where('status', 'registered')->count(),
            'failed' => DomainOrder::where('status', 'failed')->count(),
            'expiring' => DomainOrder::where('type', 'register')->where('status', 'registered')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])->count(),
            'revenue_30d' => (int) DomainOrder::where('status', 'registered')->where('created_at', '>=', $since30)->sum('price_cents'),
            'connected' => Site::whereNotNull('domain')->whereNotNull('domain_verified_at')->count(),
            'driver' => (string) config('domains.driver', 'fake'),
        ];

        $orders = null;
        $sites = null;
        if ($this->tab === 'orders') {
            $orders = DomainOrder::with('user', 'site')
                ->when($this->status !== 'checkout', fn ($q) => $q->where('status', '!=', 'checkout'))
                ->when($this->status !== '', fn ($q) => $this->status === 'expiring'
                    ? $q->where('status', 'registered')->whereBetween('expires_at', [now(), now()->addDays(30)])
                    : $q->where('status', $this->status))
                ->when($this->q !== '', fn ($q) => $q->where('domain', 'like', "%{$this->q}%"))
                ->latest()->paginate(20);
        } elseif ($this->tab === 'connected') {
            $sites = Site::with('user')->whereNotNull('domain')->where('domain', '!=', '')
                ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w->where('domain', 'like', "%{$this->q}%")->orWhere('name', 'like', "%{$this->q}%")))
                ->orderByDesc('live')->orderBy('domain')->paginate(20);
        }

        return view('livewire.platform-domains-page', [
            'stats' => $stats,
            'orders' => $orders,
            'sites' => $sites,
            'checks' => $sites ? $sites->getCollection()->mapWithKeys(fn ($s) => [$s->id => Cache::get('admin-domain-check:'.$s->id)]) : collect(),
            'edited' => ConfigOverlay::stored('domains.tlds') !== null,
            'dnsTarget' => (string) config('publishing.dns_target'),
            'failedList' => DomainOrder::with('user')->where('status', 'failed')->latest()->limit(5)->get(),
        ]);
    }
}
