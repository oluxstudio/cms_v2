<?php

namespace App\Console\Commands;

use App\Modules\Network\Contracts\ReferralService;
use Illuminate\Console\Command;

/**
 * Referral Network housekeeping: unanswered consent requests and referrals
 * whose conversion window has passed become "expired".
 *
 *   php artisan network:expire
 */
class NetworkExpire extends Command
{
    protected $signature = 'network:expire';

    protected $description = 'Expire stale Referral Network referrals (unanswered consent, conversion window over)';

    public function handle(ReferralService $network): int
    {
        $count = $network->expireStale();
        $this->info("network:expire — {$count} referral(s) expired.");

        return self::SUCCESS;
    }
}
