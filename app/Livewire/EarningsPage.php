<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Site;
use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Models\ReferralPayout;
use App\Modules\Network\NetworkException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Referral earnings — /{siteID}/earnings. What partners owe this site for
 * converted referrals, and how it's paid out (Stripe Connect transfer or Olux
 * credit). Viewing needs earnings.view (route); changing the payout method
 * needs network.manage.
 */
class EarningsPage extends Component
{
    use WithLayoutMode;

    public const METHODS = ['connect_transfer', 'olux_credit'];

    public Site $site;

    /** all | pending | ready | paid | failed */
    #[Url(except: 'all')]
    public string $filter = 'all';

    public int $limit = 30;

    public string $notice = '';

    public string $error = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('earnings', 'grid');
        if (! in_array($this->filter, ['all', 'pending', 'ready', 'paid', 'failed'], true)) {
            $this->filter = 'all';
        }
    }

    private function billing(): ReferralBillingService
    {
        return app(ReferralBillingService::class);
    }

    public function canManage(): bool
    {
        return $this->site->allows(Auth::user(), 'network.manage');
    }

    public function setFilter(string $f): void
    {
        $this->filter = in_array($f, ['all', 'pending', 'ready', 'paid', 'failed'], true) ? $f : 'all';
        $this->limit = 30;
    }

    public function loadMore(): void
    {
        $this->limit += 30;
    }

    public function setMethod(string $method): void
    {
        abort_unless($this->canManage(), 403);
        if (! in_array($method, self::METHODS, true)) {
            return;
        }
        $this->error = '';
        try {
            $this->billing()->setPayoutMethod($this->site, $method);
            $this->notice = $method === 'olux_credit'
                ? 'Earnings will now be taken off your Olux bill.'
                : 'Earnings will now be paid to your bank through Stripe.';
        } catch (NetworkException $e) {
            $this->notice = '';
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $summary = $this->billing()->earningsSummary($this->site) + [
            'pending_cents' => 0, 'ready_cents' => 0, 'paid_cents' => 0, 'credited_cents' => 0,
            'lifetime_cents' => 0, 'currency' => 'gbp', 'connect_ready' => false, 'method' => 'connect_transfer',
        ];

        $q = ReferralPayout::where('site_id', $this->site->id)->with('referral.toSite')->latest();
        match ($this->filter) {
            'pending', 'ready', 'failed' => $q->where('status', $this->filter),
            'paid' => $q->whereIn('status', ['transferred', 'credited']),
            default => null,
        };
        $payouts = $q->limit($this->limit + 1)->get();

        $counts = ReferralPayout::where('site_id', $this->site->id)
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return view('livewire.earnings-page', [
            'summary' => $summary,
            'payouts' => $payouts->take($this->limit),
            'hasMore' => $payouts->count() > $this->limit,
            'counts' => $counts,
            'canManage' => $this->canManage(),
            'cutPct' => (float) config('network.olux_cut_pct'),
        ]);
    }
}
