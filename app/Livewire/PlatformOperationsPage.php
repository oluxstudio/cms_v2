<?php

namespace App\Livewire;

use App\Models\ScheduleRun;
use App\Support\LogTail;
use App\Support\ScheduleTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Platform admin › Operations: background jobs (retry failed ones),
 * scheduled tasks (last run, next due, run now), backups and recent errors.
 */
class PlatformOperationsPage extends Component
{
    use WithPagination;

    public const TABS = ['jobs', 'schedule', 'backups', 'errors'];

    #[Url(as: 'tab')]
    public string $tab = 'jobs';

    public ?string $openError = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuper(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'jobs';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'jobs';
        $this->resetPage();
    }

    public function retryJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->dispatch('toast', level: 'success', title: 'Queued again', message: 'The job was put back on the queue.');
    }

    public function forgetJob(string $uuid): void
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        $this->dispatch('toast', level: 'success', title: 'Deleted', message: 'The failed job was removed.');
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        $this->dispatch('toast', level: 'success', title: 'Queued again', message: 'Every failed job was put back on the queue.');
    }

    public function flushFailed(): void
    {
        Artisan::call('queue:flush');
        $this->dispatch('toast', level: 'success', title: 'Cleared', message: 'All failed jobs were deleted.');
    }

    public function runTask(string $command): void
    {
        abort_unless(array_key_exists($command, ScheduleTracker::TASKS), 404);
        dispatch(fn () => ScheduleTracker::runNow($command));
        $this->dispatch('toast', level: 'success', title: 'Started', message: $command.' is running in the background. Its result appears here shortly.');
    }

    public function toggleError(string $key): void
    {
        $this->openError = $this->openError === $key ? null : $key;
    }

    /** @return list<array{name:string, bytes:int, at:Carbon}> */
    public static function backups(): array
    {
        $files = glob(storage_path('app/backups/*.sql.gz')) ?: [];
        rsort($files);

        return array_map(fn ($f) => ['name' => basename($f), 'bytes' => (int) filesize($f), 'at' => Carbon::createFromTimestamp(filemtime($f))], $files);
    }

    public function render()
    {
        $failedCount = DB::table('failed_jobs')->count();
        $pending = DB::table('jobs')->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue');
        $heartbeat = ($h = Cache::get(ScheduleTracker::HEARTBEAT_KEY)) ? Carbon::parse($h) : null;
        $backups = self::backups();
        $disk = @disk_free_space(storage_path());
        $diskTotal = @disk_total_space(storage_path());

        $lastRuns = ScheduleRun::whereIn('id', ScheduleRun::selectRaw('max(id)')->groupBy('command'))->get()->keyBy('command');
        $tasks = collect(ScheduleTracker::TASKS)->map(fn ($t, $cmd) => [
            'command' => $cmd,
            'cron' => $t[0],
            'what' => $t[1],
            'when' => ScheduleTracker::describe($t[0]),
            'next' => Carbon::instance(ScheduleTracker::nextDue($t[0])),
            'last' => $lastRuns[$cmd] ?? null,
        ])->values();

        $failed = $this->tab === 'jobs'
            ? DB::table('failed_jobs')->orderByDesc('failed_at')->paginate(15)->through(function ($j) {
                $payload = json_decode((string) $j->payload, true) ?: [];

                return (object) [
                    'uuid' => $j->uuid,
                    'job' => class_basename((string) ($payload['displayName'] ?? 'Job')),
                    'queue' => $j->queue,
                    'error' => mb_substr(strtok((string) $j->exception, "\n") ?: '', 0, 300),
                    'failed_at' => Carbon::parse($j->failed_at),
                ];
            })
            : null;

        return view('livewire.platform-operations-page', [
            'stats' => [
                'failed' => $failedCount,
                'pending' => (int) $pending->sum(),
                'heartbeat' => $heartbeat,
                'last_backup' => $backups[0]['at'] ?? null,
                'errors_24h' => LogTail::typesSince(now()->subDay()),
                'disk_free' => $disk !== false ? (int) $disk : null,
                'disk_pct' => $disk && $diskTotal ? (int) round($disk / $diskTotal * 100) : null,
            ],
            'pending' => $pending,
            'failed' => $failed,
            'tasks' => $tasks,
            'recentRuns' => $this->tab === 'schedule' ? ScheduleRun::latest('id')->limit(15)->get() : collect(),
            'backups' => $backups,
            'errors' => $this->tab === 'errors' ? LogTail::errors() : [],
            'facts' => [
                'Environment' => app()->environment(),
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Queue' => (string) config('queue.default'),
                'Cache' => (string) config('cache.default'),
                'Time zone' => (string) config('app.timezone'),
            ],
        ]);
    }
}
