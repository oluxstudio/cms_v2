@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btn = 'fx inline-flex items-center justify-center min-h-[36px] px-3.5 rounded-xl text-[12.5px] font-bold';
    $btnOutline = $btn.' border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
    $fmtBytes = fn (int $b) => $b >= 1073741824 ? number_format($b / 1073741824, 1).' GB' : ($b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format($b / 1024, 1).' KB');
    $beatAge = $stats['heartbeat'] ? (int) $stats['heartbeat']->diffInMinutes(now()) : null;
    $schedulerOk = $beatAge !== null && $beatAge <= 5;
    $backupOk = $stats['last_backup'] && $stats['last_backup']->gt(now()->subHours(7));
    $runPill = fn (?string $s) => match ($s) {
        'ok' => ['#dcfce7', '#15803d', 'OK'],
        'failed' => ['#ffe4e6', '#be123c', 'Failed'],
        'running' => ['#e0f2fe', '#0369a1', 'Running'],
        default => ['#f3f4f6', '#374151', 'No runs yet'],
    };
@endphp

<x-tri-layout title="Operations" subtitle="Background jobs, scheduled tasks, backups and errors."
    :labels="['📊 Health', '⚙️ Operations', 'ℹ️ System']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm overflow-x-auto no-scrollbar">
            @foreach (['jobs' => 'Jobs'.($stats['failed'] ? ' ('.$stats['failed'].')' : ''), 'schedule' => 'Schedule', 'backups' => 'Backups', 'errors' => 'Errors'] as $tk => $tl)
                <button wire:click="setTab('{{ $tk }}')"
                        class="shrink-0 px-4 py-1.5 rounded-full text-sm font-semibold transition-colors {{ $tab === $tk ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300' }}"
                        @if ($tab === $tk) style="background:var(--foreground);color:var(--background)" @endif>{{ $tl }}</button>
            @endforeach
        </div>
    </x-slot:header>

    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$schedulerOk ? 'Running' : ($beatAge === null ? 'Never seen' : 'Stopped')" label="Scheduler"
                :sub="$beatAge === null ? 'no heartbeat recorded' : 'last beat '.$stats['heartbeat']->diffForHumans()"
                :style="$schedulerOk ? 'background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)' : 'background:#be123c;color:#fff;--tile-icon:#fff'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile :accent="$stats['failed'] ? 'rose' : 'lime'" :value="$stats['failed']" label="Failed jobs" :sub="$stats['failed'] ? 'need a look' : 'all clear'"
                icon="M12 9v2m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
        <x-tile accent="sky" :value="$stats['pending']" label="Jobs waiting" sub="in the queue"
                icon="M4 6h16M4 10h16M4 14h16M4 18h16" />
        <x-tile :accent="$backupOk ? 'lavender' : 'rose'" :value="$stats['last_backup'] ? $stats['last_backup']->diffForHumans(short: true) : 'None'" label="Last backup"
                :sub="$backupOk ? 'on schedule' : 'overdue'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z M8 11h8" />
        <x-tile :accent="$stats['errors_24h'] ? 'rose' : 'lime'" :value="$stats['errors_24h']" label="Error types" sub="last 24 hours"
                icon="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
        <x-tile :accent="($stats['disk_pct'] ?? 100) < 15 ? 'rose' : 'cocoa'" :value="$stats['disk_free'] !== null ? $fmtBytes($stats['disk_free']) : '—'" label="Disk free"
                :sub="$stats['disk_pct'] !== null ? $stats['disk_pct'].'% of disk' : 'unknown'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
    </div>
    </x-slot:rail>

    <div class="@container max-w-[52rem] mx-auto">
        @if ($tab === 'jobs')
            <div class="{{ $panel }} p-5 mb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-[15px] font-bold text-gray-900 dark:text-white mr-auto">Queues</h2>
                    @forelse ($pending as $queue => $n)
                        <span class="px-3 py-1 rounded-full text-[12px] font-bold bg-gray-100 dark:bg-white/[0.08] text-gray-700 dark:text-gray-200">{{ $queue }}: {{ $n }} waiting</span>
                    @empty
                        <span class="text-[12.5px] text-gray-500">Nothing waiting.</span>
                    @endforelse
                </div>
            </div>

            <div class="{{ $panel }} overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                    <h2 class="text-[15px] font-bold text-gray-900 dark:text-white mr-auto">Failed jobs</h2>
                    @if ($stats['failed'])
                        <button wire:click="retryAll" data-confirm="Put all {{ $stats['failed'] }} failed jobs back on the queue?" class="{{ $btnOutline }}">Retry all</button>
                        <button wire:click="flushFailed" data-confirm="Delete all failed jobs? This can't be undone." class="{{ $btnOutline }}">Delete all</button>
                    @endif
                </div>
                @forelse ($failed as $j)
                    <div class="px-5 py-3.5 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[14px] font-bold text-gray-900 dark:text-white">{{ $j->job }}</span>
                            <span class="text-[11.5px] text-gray-500">queue {{ $j->queue }} · failed {{ $j->failed_at->diffForHumans() }}</span>
                            <span class="ml-auto flex items-center gap-2">
                                <button wire:click="retryJob('{{ $j->uuid }}')" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Retry</button>
                                <button wire:click="forgetJob('{{ $j->uuid }}')" data-confirm="Delete this failed job?" class="{{ $btnOutline }}">Delete</button>
                            </span>
                        </div>
                        <p class="mt-1 text-[12.5px] text-rose-700 dark:text-rose-400 break-words">{{ $j->error }}</p>
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No failed jobs. 🎉</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $failed->links() }}</div>

        @elseif ($tab === 'schedule')
            @unless ($schedulerOk)
                <div class="mb-4 rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 px-4 py-3 text-[13px] text-rose-800 dark:text-rose-300">
                    <b>The scheduler isn't running.</b> Nothing below will run on time until the <code>scheduler</code> container is up
                    ({{ $beatAge === null ? 'no heartbeat has ever been recorded' : 'last heartbeat '.$stats['heartbeat']->diffForHumans() }}).
                </div>
            @endunless
            <div class="space-y-3">
                @foreach ($tasks as $t)
                    @php [$pb, $pf, $pl] = $runPill($t['last']?->status); @endphp
                    <div class="{{ $panel }} p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-[13px] font-bold text-gray-900 dark:text-white">{{ $t['command'] }}</span>
                            <span class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                            <span class="ml-auto">
                                <button wire:click="runTask('{{ $t['command'] }}')" data-confirm="Run {{ $t['command'] }} now?" class="{{ $btnOutline }}">Run now</button>
                            </span>
                        </div>
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300 mt-0.5">{{ $t['what'] }}</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400 mt-1">
                            {{ $t['when'] }} · next {{ $t['next']->diffForHumans() }}
                            @if ($t['last']) · last {{ $t['last']->started_at->diffForHumans() }}{{ $t['last']->seconds() !== null ? ' ('.$t['last']->seconds().'s)' : '' }}{{ $t['last']->trigger === 'manual' ? ' · run by hand' : '' }}@endif
                        </p>
                        @if ($t['last']?->output)
                            <p class="mt-1.5 font-mono text-[11.5px] text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-white/[0.04] rounded-lg px-2.5 py-1.5 whitespace-pre-line break-words line-clamp-3">{{ $t['last']->output }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

        @elseif ($tab === 'backups')
            <div class="{{ $panel }} overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                    <div class="mr-auto">
                        <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Database backups</h2>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Every 6 hours; the newest 40 are kept (about 10 days).</p>
                    </div>
                    <button wire:click="runTask('db:backup')" data-confirm="Take a database backup now?" class="{{ $btn }}" style="background:var(--primary);color:var(--on-primary)">Back up now</button>
                </div>
                @forelse ($backups as $b)
                    <div class="px-5 py-3 flex flex-wrap items-center gap-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <span class="font-mono text-[12.5px] text-gray-800 dark:text-gray-100 truncate flex-1 min-w-0">{{ $b['name'] }}</span>
                        <span class="text-[12px] text-gray-500 dark:text-gray-400">{{ $fmtBytes($b['bytes']) }} · {{ $b['at']->format('j M H:i') }}</span>
                        <a href="{{ route('admin.backups.download', $b['name']) }}" class="{{ $btnOutline }}">Download</a>
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No backups found in storage/app/backups.</p>
                @endforelse
            </div>

        @else
            <div class="{{ $panel }} overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                    <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Recent errors</h2>
                    <p class="text-[12px] text-gray-500 dark:text-gray-400">From the end of the application log, newest first; repeats are grouped.</p>
                </div>
                @forelse ($errors as $i => $e)
                    @php $key = md5($e['message']); @endphp
                    <div class="px-5 py-3 {{ $loop->last ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
                        <button wire:click="toggleError('{{ $key }}')" class="w-full text-left flex items-start gap-3">
                            <span class="shrink-0 mt-0.5 text-[10.5px] font-extrabold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">{{ $e['count'] }}×</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-semibold text-gray-900 dark:text-white break-words">{{ $e['message'] }}</span>
                                <span class="block text-[11.5px] text-gray-500 dark:text-gray-400">last {{ \Illuminate\Support\Carbon::parse($e['last_at'])->diffForHumans() }}</span>
                            </span>
                        </button>
                        @if ($openError === $key)
                            <pre class="mt-2 text-[11px] leading-snug text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-white/[0.04] rounded-xl p-3 overflow-x-auto">{{ $e['trace'] }}</pre>
                        @endif
                    </div>
                @empty
                    <p class="p-10 text-center text-sm text-gray-500">No recent errors in the log.</p>
                @endforelse
            </div>
        @endif
    </div>

    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">System</h3>
            @foreach ($facts as $k => $v)
                <div class="flex items-center justify-between gap-3 py-1.5 text-[12.5px] {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="text-gray-500 dark:text-gray-400">{{ $k }}</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $v }}</span>
                </div>
            @endforeach
        </div>

        @if ($recentRuns->isNotEmpty())
            <div class="{{ $panel }} p-5">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Latest runs</h3>
                @foreach ($recentRuns as $r)
                    @php [$pb, $pf, $pl] = $runPill($r->status); @endphp
                    <div class="flex items-center gap-2 py-1.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <span class="flex-1 min-w-0 font-mono text-[11.5px] text-gray-700 dark:text-gray-200 truncate">{{ $r->command }}</span>
                        <span class="text-[10px] text-gray-500">{{ $r->started_at->diffForHumans(short: true) }}</span>
                        <span class="text-[9.5px] font-extrabold px-1.5 py-0.5 rounded-full" style="background:{{ $pb }};color:{{ $pf }}">{{ $pl }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="rounded-[1.75rem] p-5 shadow-sm" style="background:var(--foreground);color:var(--background)">
            <h3 class="font-display text-[16px] font-bold">When something's red</h3>
            <ul class="mt-2 space-y-1.5 text-[12.5px] opacity-85 list-disc ml-4">
                <li>Scheduler stopped: check the <code>scheduler</code> container is running.</li>
                <li>Failed jobs: read the error, fix the cause, then Retry.</li>
                <li>Backup overdue: run "Back up now" and check disk space.</li>
            </ul>
        </div>
    </x-slot:quick>
</x-tri-layout>
