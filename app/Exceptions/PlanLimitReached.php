<?php

namespace App\Exceptions;

use App\Models\AccountSubscription;
use RuntimeException;

/**
 * Something new would go over the account's plan (config/plans.php limits).
 * $reason is written for the site owner; $cta labels the upgrade button
 * (the existing `upgrade-required` prompt shows both).
 */
class PlanLimitReached extends RuntimeException
{
    public function __construct(string $reason, public readonly string $cta = 'See plans')
    {
        parent::__construct($reason);
    }

    public static function storage(AccountSubscription $sub): self
    {
        $mb = $sub->storageLimitMb();
        $size = $mb === null ? 'unlimited storage' : ($mb >= 1024 ? round($mb / 1024).' GB' : $mb.' MB').' of storage';

        return new self("Your plan includes {$size} and it's full. Upgrade for more space, or remove some assets.", 'Get more storage');
    }
}
