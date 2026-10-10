<?php

namespace App\Console\Commands;

use App\Modules\Network\Contracts\ReferralBillingService;
use Illuminate\Console\Command;

/**
 * Referral Network: put every converted referral whose dispute window has
 * passed on the receiving business's next Olux bill.
 *
 *   php artisan network:bill-due
 */
class NetworkBillDue extends Command
{
    protected $signature = 'network:bill-due';

    protected $description = 'Referral Network: bill converted referrals once their dispute window has passed';

    public function handle(ReferralBillingService $billing): int
    {
        $billed = $billing->billDue();
        $this->info("network:bill-due — {$billed} referral(s) billed.");

        return self::SUCCESS;
    }
}
