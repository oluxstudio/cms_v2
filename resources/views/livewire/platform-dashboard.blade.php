@php
    $fmtBytes = function (int $b): string {
        if ($b >= 1073741824) return number_format($b / 1073741824, 1).' GB';
        if ($b >= 1048576) return number_format($b / 1048576, 1).' MB';
        return number_format($b / 1024, 1).' KB';
    };
@endphp
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Platform dashboard</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Everything running on the CMS, at a glance.</p>
        </div>
        <a href="{{ route('admin.accounts') }}"
           class="px-4 py-2 rounded-xl text-sm font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
            Client accounts →
        </a>
    </div>

    {{-- Headline tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-stat-tile label="Accounts" :value="number_format($stats['accounts'])"
                     :sub="'+'.$stats['accounts_new'].' this month'" color="#6366f1"
                     icon="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-6.9M15 7a4 4 0 11-8 0 4 4 0 018 0z" />
        <x-stat-tile label="Active accounts (last {{ \App\Livewire\PlatformDashboard::ACTIVE_DAYS }} days)"
                     :value="number_format($stats['active'])"
                     :sub="$stats['accounts'] ? round($stats['active'] / $stats['accounts'] * 100).'% of all' : null"
                     :bar="$stats['accounts'] ? (int) round($stats['active'] / $stats['accounts'] * 100) : 0"
                     color="#10b981"
                     icon="M13 10V3L4 14h7v7l9-11h-7z" />
        <x-stat-tile label="Sites" :value="number_format($stats['sites'])"
                     :sub="$stats['sites_live'].' live · +'.$stats['sites_new'].' this month'" color="#3b82f6"
                     icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
        <x-stat-tile label="Media storage" :value="$fmtBytes($stats['storage_bytes'])"
                     :sub="number_format($stats['media']).' files'" color="#f59e0b"
                     icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
    </div>

    {{-- Money row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
        <x-stat-tile label="Estimated MRR" :value="\App\Support\Money::format($money['mrr_cents'], 'gbp')"
                     :sub="$money['paying'].' paying · '.$money['trialing'].' trialing'" color="#10b981"
                     icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-stat-tile label="Store sales · 30d (GMV)" :value="\App\Support\Money::format($money['gmv_30d_cents'], 'gbp')"
                     sub="paid orders across all tenants" color="#6366f1"
                     icon="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        <x-stat-tile label="Invoices collected · 30d" :value="\App\Support\Money::format($money['invoices_30d_cents'], 'gbp')"
                     sub="tenant invoicing" color="#3b82f6"
                     icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V19a2 2 0 01-2 2z" />
        <x-stat-tile label="Trials expiring ≤7d" :value="$expiringTrials->count()"
                     sub="reach out before they lapse" color="#f59e0b"
                     icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </div>

    {{-- Charts row --}}
    <div class="grid lg:grid-cols-3 gap-4 mt-4">
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">Signups — 30 days</h2>
            <div id="pd-signups-chart" wire:ignore></div>
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">Storage growth (MB)</h2>
            <div id="pd-storage-chart" wire:ignore></div>
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">Plan mix</h2>
            <div id="pd-plans-chart" wire:ignore class="flex justify-center"></div>
        </div>
    </div>

    {{-- Ops lists: storage hogs, expiring trials, traffic leaders --}}
    <div class="grid lg:grid-cols-3 gap-4 mt-4">
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Top accounts by storage</h2>
            <div class="space-y-2.5">
                @forelse($topStorage as $row)
                <a href="{{ route('admin.account', $row['user']->id) }}" wire:navigate class="block group">
                    <span class="flex items-center justify-between text-xs">
                        <b class="text-gray-800 dark:text-gray-100 truncate group-hover:text-indigo-500">{{ $row['user']->name }}</b>
                        <span class="tabular-nums {{ ($row['pct'] ?? 0) > 85 ? 'text-rose-500 font-bold' : 'text-gray-400' }}">
                            {{ $fmtBytes($row['bytes']) }}@if($row['pct'] !== null) · {{ $row['pct'] }}%@endif
                        </span>
                    </span>
                    <span class="block mt-1 h-1.5 rounded-full bg-black/[0.05] dark:bg-white/[0.07] overflow-hidden">
                        <span class="block h-full rounded-full {{ ($row['pct'] ?? 0) > 85 ? 'bg-rose-500' : 'bg-indigo-500' }}"
                              style="width: {{ $row['pct'] ?? 4 }}%"></span>
                    </span>
                </a>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">No media stored yet.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Trials expiring soon</h2>
            @forelse($expiringTrials as $trial)
                <a href="{{ route('admin.account', $trial->user_id) }}" wire:navigate
                   class="flex items-center justify-between gap-2 py-2 border-b border-gray-50 dark:border-white/[0.04] last:border-0 group">
                    <span class="min-w-0">
                        <b class="block text-xs text-gray-800 dark:text-gray-100 truncate group-hover:text-indigo-500">{{ $trial->user?->name }}</b>
                        <span class="block text-[11px] text-gray-400 truncate">{{ $trial->user?->email }}</span>
                    </span>
                    <span class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-full {{ $trial->trialDaysLeft() <= 2 ? 'bg-rose-100 text-rose-600' : 'bg-amber-100 text-amber-700' }}">
                        {{ $trial->trialDaysLeft() }}d left
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">No trials ending this week.</p>
            @endforelse
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Busiest accounts — visits 30d</h2>
            @if(count($topVisits)) <x-analytics.bar-list :items="$topVisits" />
            @else <p class="py-6 text-center text-sm text-gray-400">No traffic yet.</p> @endif
        </div>
    </div>

    {{-- CMS totals --}}
    <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-10 gap-2 mt-3">
        @foreach ([
            ['Pages', $stats['pages']], ['Components', $stats['components']], ['Posts', $stats['posts']],
            ['Forms', $stats['forms']], ['Responses', $stats['responses']], ['Contacts', $stats['contacts']],
            ['Bookings', $stats['bookings']], ['Orders', $stats['orders']], ['Donations', $stats['donations']],
            ['Visits · 30d', $stats['visits_30d']],
        ] as [$label, $n])
            <div class="bg-white dark:bg-[#1d1e2a] rounded-xl border border-gray-100 dark:border-white/[0.05] px-3 py-2.5 text-center">
                <p class="text-lg font-extrabold tabular-nums text-gray-900 dark:text-white leading-none">{{ number_format($n) }}</p>
                <p class="text-[10px] text-gray-400 mt-1 truncate">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-[1fr_380px] gap-4 mt-5">
        <div class="space-y-4 min-w-0">
            {{-- Plan mix --}}
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Plan mix</h2>
                <div class="space-y-2.5">
                    @foreach ($plans as $plan)
                        <div class="flex items-center gap-3">
                            <span class="w-24 text-xs font-bold shrink-0" style="color: {{ $plan['color'] }}">{{ $plan['name'] }}</span>
                            <div class="flex-1 h-2 rounded-full bg-black/[0.05] dark:bg-white/[0.07] overflow-hidden">
                                <div class="h-full rounded-full" style="width: {{ round($plan['count'] / $planMax * 100) }}%; background: {{ $plan['color'] }}"></div>
                            </div>
                            <span class="w-8 text-right text-xs font-bold tabular-nums text-gray-600 dark:text-gray-300">{{ $plan['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Recent signups --}}
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] overflow-hidden">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white px-5 pt-4 pb-2">Recent signups</h2>
                @forelse ($signups as $u)
                    <a href="{{ route('admin.account', $u->id) }}" wire:navigate
                       class="flex items-center gap-3 px-5 py-2.5 border-t border-gray-50 dark:border-white/[0.04] hover:bg-gray-50/60 dark:hover:bg-white/[0.03]">
                        <x-avatar :src="$u->avatar" :initials="strtoupper(substr($u->name, 0, 1))" size="w-8 h-8" textSize="text-xs font-bold" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $u->name }}</span>
                            <span class="block text-[11px] text-gray-400 truncate">{{ $u->email }} · {{ $u->sites_count }} {{ Str::plural('site', $u->sites_count) }}</span>
                        </span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $u->created_at->diffForHumans(short: true) }}</span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-400">No accounts yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Platform activity feed --}}
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 min-w-0">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Latest activity</h2>
            @forelse ($feed as $log)
                <div class="flex gap-3 pb-4 last:pb-0 relative">
                    @unless ($loop->last)
                        <span class="absolute left-[5px] top-4 bottom-0 w-px bg-gray-100 dark:bg-white/[0.06]"></span>
                    @endunless
                    <span class="w-[11px] h-[11px] rounded-full mt-1 shrink-0 ring-4 ring-white dark:ring-[#1d1e2a]"
                          style="background: {{ $log->accent() }}"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ $log->title }}</p>
                        <p class="text-[11px] text-gray-400 truncate">
                            @if ($log->actor)
                                <a href="{{ route('admin.account', $log->actor->id) }}" wire:navigate class="font-semibold hover:text-indigo-500">{{ $log->actor->name }}</a> ·
                            @endif
                            {{ $log->created_at->diffForHumans(short: true) }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-gray-400">No activity recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>

@script
<script>
(function () {
    if (typeof ApexCharts === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    const sub = isDark ? '#9ca3af' : '#6b7280';
    const c = @js($charts);
    const ax = { categories: c.labels, tickAmount: 6, labels: { style: { colors: sub, fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } };

    new ApexCharts(document.querySelector('#pd-signups-chart'), {
        chart: { type: 'bar', height: 180, background: 'transparent', toolbar: { show: false } },
        series: [{ name: 'Signups', data: c.signups }],
        xaxis: ax, yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        plotOptions: { bar: { borderRadius: 2, columnWidth: '55%' } },
        colors: ['#6366f1'], dataLabels: { enabled: false },
        grid: { borderColor: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data', style: { color: sub } },
    }).render();

    new ApexCharts(document.querySelector('#pd-storage-chart'), {
        chart: { type: 'area', height: 180, background: 'transparent', toolbar: { show: false } },
        series: [{ name: 'MB stored', data: c.storage_mb }],
        xaxis: ax, yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        stroke: { curve: 'smooth', width: 2 }, colors: ['#f59e0b'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } },
        dataLabels: { enabled: false },
        grid: { borderColor: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data', style: { color: sub } },
    }).render();

    new ApexCharts(document.querySelector('#pd-plans-chart'), {
        chart: { type: 'donut', height: 190, background: 'transparent' },
        series: c.plan_series, labels: c.plan_labels, colors: c.plan_colors,
        dataLabels: { enabled: false }, stroke: { show: false },
        legend: { position: 'bottom', labels: { colors: sub }, fontSize: '11px' },
        plotOptions: { pie: { donut: { size: '68%' } } },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data', style: { color: sub } },
    }).render();
})();
</script>
@endscript
