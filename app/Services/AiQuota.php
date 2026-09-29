<?php

namespace App\Services;

use App\Models\AiUsage;
use App\Models\Site;
use App\Models\User;

/**
 * Monthly AI allowance per account, from the plan's limits.ai_tokens_month
 * (null = unlimited). An account's usage is every assistant turn on the
 * sites it owns, this calendar month.
 */
class AiQuota
{
    public function limitFor(User $owner): ?int
    {
        $limit = $owner->currentSubscription()->tier()['limits']['ai_tokens_month'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function usedThisMonth(User $owner): int
    {
        return (int) AiUsage::whereIn('site_id', Site::where('user_id', $owner->id)->select('id'))
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum(\DB::raw('input_tokens + output_tokens'));
    }

    /** Has the site's owning account used up this month's allowance? */
    public function exceeded(Site $site): bool
    {
        $owner = $site->user;
        if (! $owner || ($limit = $this->limitFor($owner)) === null) {
            return false;
        }

        return $this->usedThisMonth($owner) >= $limit;
    }

    public function message(): string
    {
        return 'This account has used its AI allowance for '.now()->format('F').'. It resets on 1 '
            .now()->addMonthNoOverflow()->format('F').' — or upgrade your plan for a bigger allowance.';
    }
}
