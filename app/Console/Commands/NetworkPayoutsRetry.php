<?php

namespace App\Console\Commands;

use App\Modules\Network\Contracts\ReferralBillingService;
use Illuminate\Console\Command;

/**
 * Referral Network: retry referrer payouts that are ready (no payout
 * destination yet) or failed (Stripe error).
 *
 *   php artisan network:payouts-retry
 */
class NetworkPayoutsRetry extends Command
{
    protected $signature = 'network:payouts-retry';

    protected $description = 'Referral Network: retry ready/failed referrer payouts';

    public function handle(ReferralBillingService $billing): int
    {
        $paid = $billing->retryPayouts();
        $this->info("network:payouts-retry — {$paid} payout(s) paid.");

        return self::SUCCESS;
    }
}
