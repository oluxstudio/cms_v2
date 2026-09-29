<?php

namespace App\Livewire;

use App\Models\CreatorPayout;
use App\Models\TemplatePurchase;
use App\Models\User;
use App\Services\AccountActivity;
use App\Services\CreatorPayouts;
use App\Support\ConfigOverlay;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Platform admin › Sales & payouts: every template sale, what each creator
 * is owed, and paying them (Stripe transfer or recorded manually).
 */
class PlatformSalesPage extends Component
{
    use WithPagination;

    public const TABS = ['sales', 'creators', 'payouts'];

    #[Url(as: 'tab')]
    public string $tab = 'sales';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'q')]
    public string $q = '';

    /** Pay-out drawer. */
    public ?string $payingId = null;

    public string $payMethod = 'stripe';

    public string $payNote = '';

    public string $feePercent = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'sales';
        }
        $this->feePercent = (string) config('services.stripe_platform.fee_percent', 20);
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['q', 'status', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'sales';
        $this->resetPage();
    }

    public function startPayout(string $userId): void
    {
        $creator = User::findOrFail($userId);
        $this->payingId = $creator->id;
        $this->payMethod = $creator->stripe_account_id && $creator->stripe_charges_enabled ? 'stripe' : 'manual';
        $this->payNote = '';
        $this->resetErrorBag();
    }

    public function closePayout(): void
    {
        $this->payingId = null;
    }

    public function payOut(CreatorPayouts $payouts): void
    {
        $this->validate([
            'payMethod' => ['required', 'in:stripe,manual'],
            'payNote' => [$this->payMethod === 'manual' ? 'required' : 'nullable', 'string', 'max:500'],
        ], ['payNote.required' => 'Say how it was paid (e.g. bank transfer reference).']);
        $creator = User::findOrFail($this->payingId);

        try {
            $payout = $payouts->payOut($creator, Auth::user(), $this->payMethod, trim($this->payNote) ?: null);
        } catch (Throwable $e) {
            $this->addError('payMethod', $e->getMessage());

            return;
        }

        AccountActivity::record($creator->id, 'payout.'.$payout->status, 'Template earnings paid out', [
            'category' => 'Billing', 'meta' => ['payout_id' => $payout->id, 'amount_cents' => $payout->amount_cents, 'method' => $payout->method],
        ]);
        $this->payingId = null;
        $payout->status === 'paid'
            ? $this->dispatch('toast', level: 'success', title: 'Paid out', message: Money::format($payout->amount_cents, $payout->currency).' to '.$creator->name.'.')
            : $this->dispatch('toast', level: 'error', title: 'Payout failed', message: 'Stripe refused the transfer. Details are on the Payouts tab; nothing was marked paid.');
    }

    public function saveFee(): void
    {
        $this->validate(['feePercent' => ['required', 'numeric', 'min:0', 'max:100']]);
        ConfigOverlay::set('services.stripe_platform.fee_percent', (float) $this->feePercent);
        $this->dispatch('toast', level: 'success', title: 'Fee saved', message: "The platform keeps {$this->feePercent}% of new creator sales.");
    }

    public function render(CreatorPayouts $payouts)
    {
        $since30 = now()->subDays(30);
        $paid = fn () => TemplatePurchase::where('status', 'paid');
        $creators = $payouts->creators();

        $stats = [
            'gross_30d' => (int) $paid()->where('purchased_at', '>=', $since30)->sum('price_cents'),
            'fees_30d' => (int) $paid()->where('purchased_at', '>=', $since30)->sum('platform_fee_cents'),
            'sales_30d' => $paid()->where('purchased_at', '>=', $since30)->count(),
            'owed' => (int) $creators->sum(fn ($c) => max(0, $c['owed'])),
            'held' => (int) $creators->sum('held'),
            'paid_out' => (int) CreatorPayout::where('status', 'paid')->sum('amount_cents'),
            'refunds_30d' => TemplatePurchase::where('status', 'refunded')->where('refunded_at', '>=', $since30)->count(),
        ];

        $sales = null;
        $history = null;
        if ($this->tab === 'sales') {
            $sales = TemplatePurchase::with('template', 'buyer', 'creator')
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->whereHas('template', fn ($t) => $t->where('name', 'like', "%{$this->q}%"))
                    ->orWhereHas('buyer', fn ($u) => $u->where('email', 'like', "%{$this->q}%")->orWhere('name', 'like', "%{$this->q}%"))))
                ->latest('purchased_at')->paginate(20);
        } elseif ($this->tab === 'payouts') {
            $history = CreatorPayout::with('creator', 'admin')->withCount('purchases')->latest()->paginate(20);
        }

        return view('livewire.platform-sales-page', [
            'stats' => $stats,
            'creators' => $creators,
            'sales' => $sales,
            'history' => $history,
            'paying' => $this->payingId ? $creators->firstWhere('user.id', $this->payingId) : null,
            'holdDays' => $payouts->holdDays(),
        ]);
    }
}
