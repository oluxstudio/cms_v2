<?php

namespace App\Modules\Network\Contracts;

use App\Models\Site;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralPayout;

/**
 * Money side of the Referral Network.
 *  converted (dispute window over) → bill(): Stripe invoice item on the receiver's
 *    Olux subscription customer (lands on their next Olux bill), status billed,
 *    ReferralPayout created as pending (gross=fee, olux=cut, net=fee−cut).
 *  Olux invoice paid → markCollected(): referral collected, payout ready → payout().
 *  payout(): Stripe Connect transfer to the referrer's connect account when
 *    connect_charges_enabled and method is connect_transfer; else Olux credit
 *    (customer balance) when method is olux_credit; else stays ready.
 */
interface ReferralBillingService
{
    public function bill(Referral $referral): Referral;

    /** Bill every converted referral whose dispute window has passed. Returns count. */
    public function billDue(): int;

    /** Mark every billed referral on this paid Stripe (platform) invoice as collected and pay out. */
    public function collectForStripeInvoice(string $stripeInvoiceId, array $invoiceItemIds): int;

    public function markCollected(Referral $referral): Referral;

    public function payout(ReferralPayout $payout): ReferralPayout;

    /** Retry ready/failed payouts. Returns count paid. */
    public function retryPayouts(): int;

    /** 'connect_transfer' | 'olux_credit' — stored as site attr network.payout_method. */
    public function payoutMethod(Site $site): string;

    public function setPayoutMethod(Site $site, string $method): void;

    /**
     * Earnings summary for a referrer site:
     * ['pending_cents','ready_cents','paid_cents','credited_cents','lifetime_cents','currency','connect_ready'=>bool,'method'=>string]
     */
    public function earningsSummary(Site $site): array;
}
