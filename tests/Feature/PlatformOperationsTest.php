<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformOperationsPage;
use App\Models\ScheduleRun;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\LogTail;
use App\Support\ScheduleTracker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

function opSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

function opFailedJob(): string
{
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\OpTestJob', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
        'exception' => "RuntimeException: Test failure\n#0 stack", 'failed_at' => now(),
    ]);

    return $uuid;
}

test('operations is super-only and shows failed jobs', function () {
    $this->actingAs(User::factory()->create())->get('/admin/operations')->assertForbidden();
    opFailedJob();
    $this->actingAs(opSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/operations')->assertOk()->assertSee('OpTestJob')->assertSee('Test failure');
});

test('a failed job can be deleted or put back on the queue', function () {
    $a = opFailedJob();
    $b = opFailedJob();

    Livewire::actingAs(opSuper())->test(PlatformOperationsPage::class)
        ->call('forgetJob', $a)
        ->call('retryJob', $b);

    expect(DB::table('failed_jobs')->whereIn('uuid', [$a, $b])->count())->toBe(0)
        ->and(DB::table('jobs')->where('payload', 'like', "%{$b}%")->exists())->toBeTrue();
    DB::table('jobs')->where('payload', 'like', "%{$b}%")->delete();
});

test('running a task by hand records the run and its output', function () {
    $run = ScheduleTracker::runNow('domains:renewal-sweep');

    expect($run->status)->toBe('ok')->and($run->trigger)->toBe('manual')->and($run->finished_at)->not->toBeNull();

    Livewire::actingAs(opSuper())->test(PlatformOperationsPage::class)->call('runTask', 'rm -rf /')->assertStatus(404);
    ScheduleRun::whereKey($run->id)->delete();
});

test('every scheduled task is registered from one list', function () {
    $schedule = new Schedule;
    ScheduleTracker::register($schedule);
    $commands = collect($schedule->events())->map(fn ($e) => (string) $e->command)->implode("\n");

    foreach (array_keys(ScheduleTracker::TASKS) as $cmd) {
        expect($commands)->toContain($cmd);
    }
    expect(ScheduleTracker::describe('30 8 * * *'))->toBe('Daily at 08:30')
        ->and(ScheduleTracker::describe('0 8 * * 1'))->toBe('Mondays at 08:00');
});

test('the log reader groups repeated errors and ignores other levels', function () {
    $path = sys_get_temp_dir().'/op-log-'.uniqid().'.log';
    File::put($path, implode("\n", [
        '[2026-09-28 10:00:00] local.ERROR: Boom happened {"exception":"[object]"}',
        '#0 /app/x.php(1)',
        '[2026-09-28 10:05:00] local.INFO: all good',
        '[2026-09-28 10:10:00] local.ERROR: Boom happened {"exception":"[object]"}',
        '[2026-09-28 10:11:00] local.CRITICAL: Disk full',
    ])."\n");

    $errors = LogTail::errors($path);
    File::delete($path);

    expect($errors)->toHaveCount(2)
        ->and($errors[0]['message'])->toBe('Disk full')
        ->and($errors[1]['message'])->toBe('Boom happened')
        ->and($errors[1]['count'])->toBe(2);
});

test('backups can be downloaded by a super admin, never outside the backups folder', function () {
    $name = 'optest-'.uniqid().'.sql.gz';
    File::ensureDirectoryExists(storage_path('app/backups'));
    File::put(storage_path('app/backups/'.$name), str_repeat('x', 2048));
    $admin = opSuper();

    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/backups/'.$name)->assertOk()->assertDownload($name);
    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/backups/..%2F..%2F.env')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/admin/backups/'.$name)->assertForbidden();

    File::delete(storage_path('app/backups/'.$name));
});
