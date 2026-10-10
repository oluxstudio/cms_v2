<?php

namespace App\Modules\Network;

use App\Models\AccountSubscription;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralEvent;
use App\Modules\Network\Models\ReferralPayout;
use Illuminate\Support\Facades\DB;

/**
 * Money side of the Referral Network — see Contracts\ReferralBillingService.
 * All Stripe calls go through NetworkStripe (platform account) so tests fake it.
 */
class ReferralBilling implements ReferralBillingService
{
    public const METHODS = ['connect_transfer', 'olux_credit'];

    public const METHOD_ATTR = 'network.payout_method';

    public function __construct(private NetworkStripe $stripe) {}

    public function bill(Referral $referral): Referral
    {
        // Already billed (or further along) — never bill twice.
        if (in_array($referral->status, ['billed', 'collected'], true) || $referral->stripe_invoice_item_id) {
            return $referral;
        }
        if ($referral->status !== 'converted' || ! $referral->converted_at) {
            throw new NetworkException('Only converted referrals can be billed.');
        }
        if ($referral->converted_at->copy()->addDays((int) config('network.dispute_days', 7))->isFuture()) {
            throw new NetworkException('The dispute window for this referral is still open.');
        }

        $customer = $this->subscriptionFor($referral->toSite?->user);
        if (! $customer?->stripe_customer_id) {
            $this->logOnce($referral, 'bill_skipped', ['reason' => 'Receiver has no Olux billing customer (no card on file).']);

            return $referral;
        }

        return DB::transaction(function () use ($referral, $customer) {
            /** @var Referral $locked */
            $locked = Referral::whereKey($referral->id)->lockForUpdate()->first();
            if ($locked->status !== 'converted' || $locked->stripe_invoice_item_id) {
                return $locked;
            }

            $from = $locked->fromSite;
            $itemId = $this->stripe->createInvoiceItem(array_filter([
                'customer' => $customer->stripe_customer_id,
                'amount' => $locked->fee_cents,
                'currency' => $locked->currency,
                'description' => "Referral {$locked->reference} — {$locked->customer_name} (from ".($from?->name ?? 'a network member').')',
                'metadata' => ['kind' => 'referral', 'referral_id' => $locked->id, 'reference' => $locked->reference],
                'subscription' => $customer->stripe_subscription_id ?: null,
            ]), 'referral-bill-'.$locked->id);

            $locked->update(['status' => 'billed', 'billed_at' => now(), 'stripe_invoice_item_id' => $itemId]);

            ReferralPayout::firstOrCreate(['referral_id' => $locked->id], [
                'site_id' => $locked->from_site_id,
                'gross_cents' => $locked->fee_cents,
                'olux_cents' => $locked->oluxCents(),
                'net_cents' => $locked->netCents(),
                'currency' => $locked->currency,
                'status' => 'pending',
                'method' => $from ? $this->payoutMethod($from) : 'connect_transfer',
            ]);

            $this->log($locked, 'billed', ['stripe_invoice_item_id' => $itemId, 'fee_cents' => $locked->fee_cents]);

            return $locked;
        });
    }

    public function billDue(): int
    {
        $cutoff = now()->subDays((int) config('network.dispute_days', 7));
        $count = 0;

        Referral::where('status', 'converted')
            ->whereNotNull('converted_at')
            ->where('converted_at', '<=', $cutoff)
            ->whereNull('stripe_invoice_item_id')
            ->orderBy('converted_at')
            ->get()
            ->each(function (Referral $referral) use (&$count) {
                try {
                    if ($this->bill($referral)->status === 'billed') {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $count;
    }

    public function collectForStripeInvoice(string $stripeInvoiceId, array $invoiceItemIds): int
    {
        $ids = array_values(array_filter(array_map('strval', $invoiceItemIds)));
        if ($ids === []) {
            return 0;
        }
        $count = 0;
        Referral::where('status', 'billed')->whereIn('stripe_invoice_item_id', $ids)->get()
            ->each(function (Referral $referral) use (&$count, $stripeInvoiceId) {
                if ($this->collect($referral, $stripeInvoiceId)->status === 'collected') {
                    $count++;
                }
            });

        return $count;
    }

    public function markCollected(Referral $referral): Referral
    {
        return $this->collect($referral, null);
    }

    /** Billed → collected (the receiver's Olux invoice was paid); payout ready → paid out. */
    private function collect(Referral $referral, ?string $stripeInvoiceId): Referral
    {
        $payout = DB::transaction(function () use ($referral, $stripeInvoiceId) {
            $locked = Referral::whereKey($referral->id)->lockForUpdate()->first();
            if ($locked->status !== 'billed') {
                return null;
            }
            $locked->update(['status' => 'collected', 'collected_at' => now()]);
            $this->log($locked, 'collected', array_filter(['stripe_invoice_id' => $stripeInvoiceId]));
            $payout = $locked->payout()->first();
            if ($payout && $payout->status === 'pending') {
                $payout->update(['status' => 'ready']);
            }

            return $payout;
        });

        if ($payout && $payout->status === 'ready') {
            $this->payout($payout);
        }

        return $referral->refresh();
    }

    public function payout(ReferralPayout $payout): ReferralPayout
    {
        if (! in_array($payout->status, ['ready', 'failed'], true)) {
            return $payout;
        }
        $referral = $payout->referral;
        $site = $payout->site;
        $method = $site ? $this->payoutMethod($site) : ($payout->method ?: 'connect_transfer');
        $attempt = ReferralEvent::where('referral_id', $payout->referral_id)->where('type', 'payout_failed')->count();

        try {
            if ($method === 'connect_transfer' && ($acct = $this->connectAccount($site))) {
                $id = $this->stripe->createTransfer([
                    'amount' => $payout->net_cents,
                    'currency' => $payout->currency,
                    'destination' => $acct,
                    'transfer_group' => $referral?->reference,
                    'description' => 'Olux referral fee '.$referral?->reference,
                    'metadata' => ['kind' => 'referral_payout', 'referral_id' => $payout->referral_id, 'payout_id' => $payout->id],
                ], "referral-transfer-{$payout->id}-{$attempt}");
                $payout->update(['status' => 'transferred', 'method' => $method, 'stripe_transfer_id' => $id, 'paid_at' => now(), 'failure' => null]);
                $this->log($referral, 'payout_transferred', ['payout_id' => $payout->id, 'net_cents' => $payout->net_cents, 'stripe_transfer_id' => $id]);
            } elseif ($method === 'olux_credit' && ($customer = $this->subscriptionFor($site?->user)?->stripe_customer_id)) {
                $id = $this->stripe->createBalanceTransaction($customer, [
                    'amount' => -$payout->net_cents,
                    'currency' => $payout->currency,
                    'description' => 'Olux referral credit '.$referral?->reference,
                    'metadata' => ['kind' => 'referral_payout', 'referral_id' => $payout->referral_id, 'payout_id' => $payout->id],
                ], "referral-credit-{$payout->id}-{$attempt}");
                $payout->update(['status' => 'credited', 'method' => $method, 'stripe_balance_txn_id' => $id, 'paid_at' => now(), 'failure' => null]);
                $this->log($referral, 'payout_credited', ['payout_id' => $payout->id, 'net_cents' => $payout->net_cents, 'stripe_balance_txn_id' => $id]);
            } elseif ($payout->method !== $method) {
                // Nowhere to send it yet (no Connect account / no Olux customer) — wait for a retry.
                $payout->update(['method' => $method]);
            }
        } catch (\Throwable $e) {
            report($e);
            $payout->update(['status' => 'failed', 'method' => $method, 'failure' => mb_substr($e->getMessage(), 0, 500)]);
            $this->log($referral, 'payout_failed', ['payout_id' => $payout->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
        }

        return $payout->refresh();
    }

    public function retryPayouts(): int
    {
        $paid = 0;
        ReferralPayout::whereIn('status', ['ready', 'failed'])->orderBy('created_at')->get()
            ->each(function (ReferralPayout $payout) use (&$paid) {
                if (in_array($this->payout($payout)->status, ['transferred', 'credited'], true)) {
                    $paid++;
                }
            });

        return $paid;
    }

    public function payoutMethod(Site $site): string
    {
        $method = (string) $site->getAttr(self::METHOD_ATTR, 'connect_transfer');

        return in_array($method, self::METHODS, true) ? $method : 'connect_transfer';
    }

    public function setPayoutMethod(Site $site, string $method): void
    {
        if (! in_array($method, self::METHODS, true)) {
            throw new NetworkException('Choose how you want to be paid: a bank transfer or Olux credit.');
        }
        $site->setAttr(self::METHOD_ATTR, $method);
        // Not-yet-paid payouts follow the new choice.
        ReferralPayout::where('site_id', $site->id)->whereIn('status', ['pending', 'ready', 'failed'])->update(['method' => $method]);
    }

    public function earningsSummary(Site $site): array
    {
        $sums = ReferralPayout::where('site_id', $site->id)
            ->selectRaw('status, SUM(net_cents) as total')->groupBy('status')->pluck('total', 'status')
            ->map(fn ($v) => (int) $v);

        $paid = $sums['transferred'] ?? 0;
        $credited = $sums['credited'] ?? 0;

        return [
            'pending_cents' => $sums['pending'] ?? 0,                            // billed to the receiver, not yet collected
            'ready_cents' => ($sums['ready'] ?? 0) + ($sums['failed'] ?? 0),     // collected, awaiting payout (incl. failed retries)
            'paid_cents' => $paid,                                              // sent by Stripe Connect transfer
            'credited_cents' => $credited,                                      // credited to the Olux bill
            'lifetime_cents' => $sums->sum(),                                   // everything earned (all statuses)
            'currency' => ReferralPayout::where('site_id', $site->id)->latest()->value('currency') ?? 'gbp',
            'connect_ready' => $this->connectAccount($site) !== null,
            'method' => $this->payoutMethod($site),
        ];
    }

    /* ── helpers ───────────────────────────────────────────────────────── */

    /** The referrer's Connect Express account id, when it can receive money. */
    private function connectAccount(?Site $site): ?string
    {
        if (! $site) {
            return null;
        }
        $s = SitePaymentSettings::where('site_id', $site->id)->first();

        return $s && filled($s->connect_account_id) && $s->connect_charges_enabled ? $s->connect_account_id : null;
    }

    /** The owner's Olux subscription row (never created here). */
    private function subscriptionFor(?User $user): ?AccountSubscription
    {
        return $user ? AccountSubscription::where('user_id', $user->id)->first() : null;
    }

    private function log(?Referral $referral, string $type, array $data = []): void
    {
        if ($referral) {
            app(ReferralService::class)->log($referral, $type, null, $data);
        }
    }

    private function logOnce(Referral $referral, string $type, array $data): void
    {
        if (! ReferralEvent::where('referral_id', $referral->id)->where('type', $type)->exists()) {
            $this->log($referral, $type, $data);
        }
    }
}
