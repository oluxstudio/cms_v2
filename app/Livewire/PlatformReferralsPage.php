<?php

namespace App\Livewire;

use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralPayout;
use App\Modules\Network\NetworkException;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Platform admin › Referrals: the Olux Referral Network at a glance — dispute
 * resolution, referrer payouts (retry failed / ready), every referral and the
 * member directory.
 */
class PlatformReferralsPage extends Component
{
    use WithPagination;

    public const TABS = ['disputes', 'payouts', 'referrals', 'members'];

    /** Statuses that count as "converted" for the conversion rate. */
    public const CONVERTED = ['converted', 'disputed', 'billed', 'collected'];

    /** Statuses that reached the receiver (shared or later). */
    public const REACHED = ['shared', 'accepted', 'declined', 'converted', 'disputed', 'void', 'billed', 'collected', 'expired'];

    #[Url(as: 'tab')]
    public string $tab = 'disputes';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'q')]
    public string $q = '';

    /** Optional resolution notes, keyed by referral id. */
    public array $notes = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'disputes';
        }
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['q', 'status', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'disputes';
        $this->resetPage();
    }

    /** Super admin decides a dispute: 'upheld' voids the fee, 'rejected' lets it stand. */
    public function resolve(string $referralId, string $outcome): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($outcome, ['upheld', 'rejected'], true)) {
            return;
        }
        $referral = Referral::findOrFail($referralId);
        $note = trim((string) ($this->notes[$referralId] ?? '')) ?: null;

        try {
            app(ReferralService::class)->resolveDispute($referral, Auth::user(), $outcome, $note);
        } catch (NetworkException $e) {
            $this->dispatch('toast', level: 'error', title: 'Could not resolve', message: $e->getMessage());

            return;
        }

        unset($this->notes[$referralId]);
        $this->dispatch('toast', level: 'success', title: 'Dispute resolved', message: $outcome === 'upheld'
            ? "Referral {$referral->reference} voided — no fee is charged."
            : "Referral {$referral->reference}: the fee stands.");
    }

    public function retry(string $payoutId): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        $payout = ReferralPayout::findOrFail($payoutId);

        try {
            $payout = app(ReferralBillingService::class)->payout($payout);
        } catch (NetworkException $e) {
            $this->dispatch('toast', level: 'error', title: 'Payout failed', message: $e->getMessage());

            return;
        }

        in_array($payout->status, ['transferred', 'credited'], true)
            ? $this->dispatch('toast', level: 'success', title: 'Paid out', message: Money::format($payout->net_cents, $payout->currency).' '.($payout->status === 'credited' ? 'credited.' : 'transferred.'))
            : $this->dispatch('toast', level: 'error', title: 'Not paid', message: $payout->failure ?: 'The payout is still '.$payout->status.'.');
    }

    public function retryAll(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);

        try {
            $paid = app(ReferralBillingService::class)->retryPayouts();
        } catch (NetworkException $e) {
            $this->dispatch('toast', level: 'error', title: 'Retry failed', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', level: $paid ? 'success' : 'info', title: 'Retry finished', message: $paid.' '.str('payout')->plural($paid).' paid.');
    }

    public function render()
    {
        $monthStart = now()->startOfMonth();
        $reached = Referral::whereIn('status', self::REACHED)->count();
        $converted = Referral::whereIn('status', ['converted', 'billed', 'collected'])->count();

        $stats = [
            'members' => NetworkProfile::whereNotNull('terms_accepted_at')->count(),
            'accepting' => NetworkProfile::whereNotNull('terms_accepted_at')->where('accepting', true)->count(),
            'month' => Referral::where('created_at', '>=', $monthStart)->count(),
            'all' => Referral::count(),
            'conversion' => $reached ? round($converted / $reached * 100, 1) : 0.0,
            'converted' => $converted,
            'billed' => (int) Referral::whereIn('status', ['billed', 'collected'])->sum('fee_cents'),
            // A payout past 'pending' means the receiver's fee was collected.
            'revenue' => (int) ReferralPayout::whereIn('status', ['ready', 'transferred', 'credited', 'failed'])->sum('olux_cents'),
            'paid_out' => (int) ReferralPayout::whereIn('status', ['transferred', 'credited'])->sum('net_cents'),
            'disputes' => Referral::where('status', 'disputed')->count(),
            'failed' => ReferralPayout::where('status', 'failed')->count(),
            'ready' => ReferralPayout::where('status', 'ready')->count(),
        ];

        $disputes = $payouts = $referrals = $members = null;
        if ($this->tab === 'disputes') {
            $disputes = Referral::with('fromSite', 'toSite', 'events')
                ->where('status', 'disputed')->oldest('disputed_at')->paginate(15);
        } elseif ($this->tab === 'payouts') {
            $payouts = ReferralPayout::with('site', 'referral.toSite')
                ->whereIn('status', ['failed', 'ready'])
                ->orderByRaw("case when status = 'failed' then 0 else 1 end")->oldest('updated_at')->paginate(20);
        } elseif ($this->tab === 'referrals') {
            $term = trim($this->q);
            $referrals = Referral::with('fromSite', 'toSite')
                ->when($this->status !== '' && in_array($this->status, Referral::STATUSES, true), fn ($q) => $q->where('status', $this->status))
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%")
                    ->orWhereHas('fromSite', fn ($s) => $s->where('name', 'like', "%{$term}%")->orWhere('domain', 'like', "%{$term}%"))
                    ->orWhereHas('toSite', fn ($s) => $s->where('name', 'like', "%{$term}%")->orWhere('domain', 'like', "%{$term}%"))))
                ->latest()->paginate(20);
        } else {
            $members = NetworkProfile::with('site')->whereNotNull('terms_accepted_at')->latest('terms_accepted_at')->paginate(20);
        }

        return view('livewire.platform-referrals-page', [
            'stats' => $stats,
            'disputes' => $disputes,
            'payouts' => $payouts,
            'referrals' => $referrals,
            'members' => $members,
            'recentDisputes' => $this->tab === 'disputes' ? collect() : Referral::with('toSite')->where('status', 'disputed')->oldest('disputed_at')->take(4)->get(),
        ]);
    }
}
