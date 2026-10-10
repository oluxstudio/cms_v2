<?php

namespace App\Modules\Newsletter\Services;

use App\Models\Site;
use App\Modules\Newsletter\Models\NewsletterSend;

/**
 * The plan's monthly newsletter allowance (`newsletter_sends_month` in
 * config/plans.php; null = unlimited), counted across every site the owning
 * account has. Queued, in-flight and delivered messages count; failed and
 * skipped ones don't. Test sends are free.
 */
class SendQuota
{
    public const LIMIT_KEY = 'newsletter_sends_month';

    /** @return array{limit: ?int, used: int, left: ?int, plan: string} */
    public static function forSite(Site $site): array
    {
        $owner = $site->user;
        $sub = $owner?->currentSubscription();
        $limits = $sub ? ($sub->tier()['limits'] ?? []) : (config('plans.tiers.trial.limits') ?? []);
        $limit = array_key_exists(self::LIMIT_KEY, $limits) ? $limits[self::LIMIT_KEY] : null;
        $limit = $limit === null ? null : (int) $limit;

        $siteIds = $owner ? Site::where('user_id', $owner->id)->select('id') : [$site->id];
        $used = NewsletterSend::whereIn('site_id', $siteIds)
            ->whereIn('status', NewsletterSend::COUNTED)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return [
            'limit' => $limit,
            'used' => $used,
            'left' => $limit === null ? null : max(0, $limit - $used),
            'plan' => (string) ($sub ? ($sub->tier()['name'] ?? ucfirst($sub->plan)) : 'Trial'),
        ];
    }

    /** null when `$count` messages fit in this month's allowance, else a human explanation. */
    public static function check(Site $site, int $count): ?string
    {
        $q = self::forSite($site);
        if ($q['left'] === null || $count <= $q['left']) {
            return null;
        }

        return sprintf(
            'This campaign goes to %s %s, but your %s plan has %s of its %s newsletter sends left this month. Upgrade your plan to send more.',
            number_format($count), $count === 1 ? 'subscriber' : 'subscribers', $q['plan'],
            number_format($q['left']), number_format((int) $q['limit']),
        );
    }
}
