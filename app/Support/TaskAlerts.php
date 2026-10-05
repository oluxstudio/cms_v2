<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Site;

/**
 * "Your background task finished" — one alert addressed to the person who
 * started it (or the site/account owner when nobody did). It shows in their
 * header bell, in the site's Alerts page when it belongs to a site, and pops
 * up as a toast on whatever page they have open (TaskWatcher).
 */
class TaskAlerts
{
    public const TYPES = ['task_complete', 'task_failed'];

    public static function done(?string $userId, ?string $siteId, string $title, ?string $body = null, ?string $link = null, array $meta = []): ?Alert
    {
        return self::raise('success', 'task_complete', $userId, $siteId, $title, $body, $link, $meta);
    }

    public static function failed(?string $userId, ?string $siteId, string $title, ?string $body = null, ?string $link = null, array $meta = []): ?Alert
    {
        return self::raise('error', 'task_failed', $userId, $siteId, $title, $body, $link, $meta);
    }

    private static function raise(string $level, string $type, ?string $userId, ?string $siteId, string $title, ?string $body, ?string $link, array $meta): ?Alert
    {
        $userId ??= $siteId ? Site::whereKey($siteId)->value('user_id') : null;
        if (! $userId) {
            return null; // nobody to tell
        }
        try {
            return Alert::create([
                'site_id' => $siteId, 'user_id' => $userId, 'level' => $level, 'type' => $type, 'audience' => 'all',
                'title' => mb_substr($title, 0, 250), 'body' => $body, 'link' => $link, 'meta' => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e); // a missed notice must never fail the task itself

            return null;
        }
    }
}
