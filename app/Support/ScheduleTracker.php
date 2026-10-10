<?php

namespace App\Support;

use App\Models\ScheduleRun;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The platform's scheduled tasks, in ONE list: routes/console.php registers
 * them from here, and the admin Operations page reads the same list (the
 * schedule itself only exists inside the console process). Every run is
 * recorded in schedule_runs; a minutely heartbeat shows the scheduler is alive.
 */
class ScheduleTracker
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    /** command => [cron expression, what it does] */
    public const TASKS = [
        'db:backup' => ['0 */6 * * *', 'Database backup (keeps the newest 40)'],
        'invoices:sweep' => ['0 * * * *', 'Overdue invoices, recurring invoices, payment reminders'],
        'bookings:automate reminders' => ['0 * * * *', 'Booking reminders about 24 hours ahead'],
        'bookings:automate reviews' => ['0 * * * *', 'Review requests the day after a booking'],
        'bookings:automate rebook' => ['0 10 * * *', '"Time for your next visit" prompts'],
        'site:digest' => ['0 8 * * 1', 'Weekly site digest emails'],
        'signup:nudge' => ['30 9 * * *', 'Nudge people who started signing up'],
        'domains:renewal-sweep' => ['30 8 * * *', 'Warn site teams before domains expire'],
        'email:purge-suspended' => ['15 4 * * *', 'Delete business email whose export window has ended'],
        'events:remind' => ['5 * * * *', 'Events add-on: attendee reminders + release expired ticket holds'],
        'reviews:remind' => ['15 10 * * *', 'Reviews add-on: one reminder 5 days after a review request'],
        'network:expire' => ['20 2 * * *', 'Referral Network: expire unanswered consents and lapsed conversion windows'],
        'network:bill-due' => ['0 6 * * *', 'Referral Network: bill converted referrals after the dispute window'],
        'network:payouts-retry' => ['30 6 * * *', 'Referral Network: retry ready/failed referrer payouts'],
    ];

    public static function register(Schedule $schedule): void
    {
        foreach (self::TASKS as $command => [$cron]) {
            self::track($schedule->command($command)->cron($cron)->withoutOverlapping(), $command);
        }
        $schedule->call(fn () => Cache::forever(self::HEARTBEAT_KEY, now()->toIso8601String()))
            ->everyMinute()->name('scheduler-heartbeat');
        $schedule->call(fn () => ScheduleRun::where('started_at', '<', now()->subDays(30))->delete())
            ->dailyAt('03:15')->name('prune-schedule-runs');
    }

    public static function track(Event $event, string $command): Event
    {
        $runId = null;

        // Output goes to a per-user temp file: a shared log file created by
        // another user (root scheduler vs app user) can't be overwritten, and
        // the shell's failed redirect would then mark a good run as failed.
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();

        return $event->sendOutputTo(sys_get_temp_dir().'/olux-schedule-'.$uid.'-'.md5($command).'.log')
            ->before(function () use (&$runId, $command) {
                $runId = ScheduleRun::create(['command' => $command, 'started_at' => now()])->id;
            })
            // By reference: $runId is only known once before() has run.
            ->onSuccess(function () use (&$runId, $event) {
                self::finish($runId, 'ok', $event->output);
            })
            ->onFailure(function () use (&$runId, $event) {
                self::finish($runId, 'failed', $event->output);
            });
    }

    private static function finish(?int $runId, string $status, ?string $outputFile): void
    {
        if (! $runId) {
            return;
        }
        $output = $outputFile && is_file($outputFile) ? (string) file_get_contents($outputFile) : '';
        ScheduleRun::whereKey($runId)->update([
            'status' => $status, 'finished_at' => now(), 'output' => mb_substr(trim($output), -4000) ?: null,
        ]);
    }

    /** Run a task now (called from a queued job), recording it like a scheduled run. */
    public static function runNow(string $command): ScheduleRun
    {
        abort_unless(array_key_exists($command, self::TASKS), 404);
        $run = ScheduleRun::create(['command' => $command, 'trigger' => 'manual', 'started_at' => now()]);
        try {
            $code = Artisan::call($command);
            $run->update(['status' => $code === 0 ? 'ok' : 'failed', 'finished_at' => now(), 'output' => mb_substr(trim(Artisan::output()), -4000) ?: null]);
        } catch (Throwable $e) {
            report($e);
            $run->update(['status' => 'failed', 'finished_at' => now(), 'output' => $e->getMessage()]);
        }

        return $run;
    }

    public static function nextDue(string $cron): \DateTimeInterface
    {
        return (new CronExpression($cron))->getNextRunDate(now(), 0, false, config('app.timezone'));
    }

    /** Human frequency for the common expressions used above. */
    public static function describe(string $cron): string
    {
        return match (true) {
            $cron === '0 * * * *' => 'Every hour',
            $cron === '0 */6 * * *' => 'Every 6 hours',
            (bool) preg_match('/^(\d+) (\d+) \* \* \*$/', $cron, $m) => sprintf('Daily at %02d:%02d', $m[2], $m[1]),
            (bool) preg_match('/^(\d+) (\d+) \* \* 1$/', $cron, $m) => sprintf('Mondays at %02d:%02d', $m[2], $m[1]),
            default => $cron,
        };
    }
}
