<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Site;
use App\Models\TaskLog;
use App\Models\User;

/**
 * Central place to record "a task was performed" on a site.
 *
 * Every call persists a row in task_logs (the audit/feed) and returns the model,
 * so callers can also turn it into a UI toast. Optionally raises a team Alert for
 * milestone-worthy events (e.g. task complete, new member, feature enabled).
 */
class TaskLogger
{
    /**
     * Record a performed task.
     *
     * @param  string  $level  success | error | warning | info
     */
    public function log(
        Site $site,
        ?User $actor,
        string $title,
        string $level = 'success',
        ?string $type = null,
        ?string $message = null,
        array $meta = [],
    ): TaskLog {
        return $site->taskLogs()->create([
            'user_id' => $actor?->id,
            'level' => $level,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Raise a team-visible alert (notifications bell). audience: all | admins.
     */
    public function alert(
        Site $site,
        string $title,
        string $type = 'system',
        string $level = 'info',
        ?string $body = null,
        ?User $user = null,
        string $audience = 'all',
        ?string $link = null,
        array $meta = [],
    ): Alert {
        return $site->alerts()->create([
            'user_id' => $user?->id,
            'level' => $level,
            'type' => $type,
            'audience' => $audience,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Convenience: log a task AND, for notable types, drop a milestone alert.
     */
    public function record(
        Site $site,
        ?User $actor,
        string $title,
        string $level = 'success',
        ?string $type = null,
        ?string $message = null,
        array $meta = [],
        bool $alertTeam = false,
    ): TaskLog {
        $log = $this->log($site, $actor, $title, $level, $type, $message, $meta);

        if ($alertTeam) {
            $this->alert($site, $title, 'task_complete', $level, $message, null, 'all', null, $meta);
        }

        return $log;
    }
}
