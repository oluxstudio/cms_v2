<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Which platform announcements a signed-in user should see right now. */
class Announcements
{
    public function activeFor(User $user): Collection
    {
        try {
            $all = Cache::remember('announcements:active', 60, fn () => Announcement::active()->latest()->get());
        } catch (Throwable) {
            return collect(); // no table yet
        }
        if ($all->isEmpty()) {
            return $all;
        }
        $dismissed = DB::table('announcement_dismissals')->where('user_id', $user->id)
            ->whereIn('announcement_id', $all->pluck('id'))->pluck('announcement_id')->all();

        return $all->filter(fn (Announcement $a) => $a->targets($user) && ! in_array($a->id, $dismissed, true))->values();
    }

    public function dismiss(Announcement $a, User $user): void
    {
        if (! $a->dismissible) {
            return;
        }
        DB::table('announcement_dismissals')->insertOrIgnore([
            'announcement_id' => $a->id, 'user_id' => $user->id, 'created_at' => now(),
        ]);
    }

    public static function flush(): void
    {
        Cache::forget('announcements:active');
    }

    /** Accounts the announcement would reach. */
    public function audienceQuery(Announcement $a)
    {
        $q = User::query();

        return match ($a->audience) {
            'plans' => in_array('trial', (array) $a->plans, true)
                ? $q->where(fn ($w) => $w->whereHas('subscription', fn ($s) => $s->whereIn('plan', (array) $a->plans))->orWhereDoesntHave('subscription'))
                : $q->whereHas('subscription', fn ($s) => $s->whereIn('plan', (array) $a->plans)),
            'accounts' => $q->whereIn('id', (array) $a->account_ids),
            default => $q,
        };
    }

    /** Copy the announcement into the notifications of every targeted account's sites. */
    public function pushToAlerts(Announcement $a): int
    {
        $n = 0;
        $logger = app(TaskLogger::class);
        $this->audienceQuery($a)->select('id')->chunkById(200, function ($users) use ($a, $logger, &$n) {
            foreach (Site::whereIn('user_id', $users->pluck('id'))->get() as $site) {
                $logger->alert($site, $a->title, type: 'system',
                    level: match ($a->level) {
                        'critical' => 'error', 'warning' => 'warning', default => 'info'
                    },
                    body: $a->body, link: $a->link_url, dedupeKey: 'announce:'.$a->id);
                $n++;
            }
        });
        $a->update(['alerts_sent_at' => now()]);

        return $n;
    }
}
