@php
    $fmtBytes = function (int $b): string {
        if ($b >= 1073741824) return number_format($b / 1073741824, 1).' GB';
        if ($b >= 1048576) return number_format($b / 1048576, 1).' MB';
        return number_format($b / 1024, 1).' KB';
    };
    $gbp = fn (int $c) => \App\Support\Money::format($c, 'gbp');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $growthMax = max(1, $growth->max('count'));
    $planTotal = max(1, $plans->sum('count'));
    $attention = $templateStats['in_review'] + $templateStats['failed_7d'];
@endphp

<x-tri-layout :labels="['📊 Numbers', '🏠 Overview', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    {{-- ── Greeting + newest accounts (sample header + avatar strip) ── --}}
    <x-slot:header>
        <div class="w-full min-w-0 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white leading-tight">
                    Hello, <span style="color:var(--primary)">{{ $firstName }}</span>
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Everything running on the platform, at a glance.</p>
            </div>
            <div class="{{ $panel }} flex items-center gap-2 px-3 py-2.5 max-w-full overflow-x-auto no-scrollbar">
                @foreach ($signups->take(7) as $u)
                    <a href="{{ route('admin.account', $u->id) }}" wire:navigate title="{{ $u->name }} · joined {{ $u->created_at->diffForHumans() }}"
                       class="rounded-full ring-2 ring-white dark:ring-[#1d1e2a] hover:-translate-y-0.5 transition-transform">
                        <x-avatar :src="$u->avatar" :initials="strtoupper(substr($u->name, 0, 1))" size="w-10 h-10" textSize="text-sm font-bold" />
                    </a>
                @endforeach
                <a href="{{ route('admin.accounts', ['sort' => 'newest']) }}" wire:navigate title="All accounts"
                   class="w-10 h-10 rounded-full grid place-items-center text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06]">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: the platform's numbers ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$gbp($money['mrr_cents'])" label="Estimated MRR"
                :sub="$money['paying'].' paying · '.$money['trialing'].' on trial'"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="number_format($stats['accounts'])" label="Accounts" :sub="'+'.$stats['accounts_new'].' this month'"
                :href="route('admin.accounts')"
                icon="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-6.9M15 7a4 4 0 11-8 0 4 4 0 018 0z" />
        <x-tile accent="lime" :value="number_format($stats['active'])" label="Active accounts"
                :sub="'last '.\App\Livewire\PlatformDashboard::ACTIVE_DAYS.' days'"
                icon="M13 10V3L4 14h7v7l9-11h-7z" />
        <x-tile accent="sky" :value="number_format($stats['sites'])" label="Sites" :sub="$stats['sites_live'].' live'"
                icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
        <x-tile accent="rose" :value="$expiringTrials->count()" label="Trials ending" sub="next 7 days"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="cocoa" :value="$gbp($money['gmv_30d_cents'])" label="Store sales" sub="30 days, all shops"
                icon="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        <x-tile accent="sky" :value="$gbp($money['invoices_30d_cents'])" label="Invoices paid" sub="30 days"
                icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V19a2 2 0 01-2 2z" />
        <x-tile accent="lavender" :value="$fmtBytes($stats['storage_bytes'])" label="Storage" :sub="number_format($stats['media']).' files'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
    </div>
    </x-slot:rail>

    {{-- ══ CENTER ══ --}}
    <div class="@container max-w-[52rem] mx-auto space-y-4">

        <div class="grid @xl:grid-cols-[1.25fr_1fr] gap-4">
            {{-- Growth statistics (sample "Balance statistics") --}}
            <div class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Growth statistics</h2>
                <p class="mt-1 flex items-baseline gap-2">
                    <span class="font-display text-[2.4rem] leading-none font-extrabold text-gray-900 dark:text-white tabular-nums">{{ number_format($stats['accounts']) }}</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400">accounts</span>
                </p>
                <div class="mt-5 flex items-end justify-between gap-4">
                    <div class="shrink-0">
                        <p class="flex items-center gap-1.5 text-[13px] font-bold text-gray-900 dark:text-white">
                            <span class="w-7 h-7 rounded-full border-2 border-current grid place-items-center">
                                <svg class="w-3.5 h-3.5 {{ ($growthPct ?? 0) < 0 ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6"/></svg>
                            </span>
                            {{ $growthPct === null ? 'new' : ($growthPct >= 0 ? '+' : '').$growthPct.'%' }}
                        </p>
                        <p class="mt-1.5 text-[11.5px] leading-snug text-gray-500 dark:text-gray-400">Signups this month<br>vs last month</p>
                    </div>
                    <div class="flex items-end gap-2.5 h-24">
                        @foreach ($growth as $m)
                            <div class="flex flex-col items-center gap-1.5" title="{{ $m['count'] }} signups">
                                <span class="w-7 rounded-full transition-all"
                                      style="height: {{ max(14, (int) round($m['count'] / $growthMax * 76)) }}px; background: {{ $loop->last ? 'var(--primary)' : 'color-mix(in srgb, var(--primary) 45%, transparent)' }}"></span>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $m['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Featured primary card (sample bank card): the platform in one card --}}
            <div class="relative overflow-hidden rounded-[1.75rem] p-5 shadow-sm flex flex-col justify-between min-h-[13rem]"
                 style="background:var(--primary);color:var(--on-primary)">
                <span class="absolute -right-10 -bottom-16 w-56 h-56 rounded-full" style="background:color-mix(in srgb, var(--on-primary) 12%, transparent)"></span>
                <p class="relative text-[12px] font-bold uppercase tracking-[.14em] opacity-90">Olux platform</p>
                <div class="relative">
                    <p class="font-display text-[2.1rem] leading-none font-extrabold tabular-nums">{{ number_format($stats['visits_30d']) }}</p>
                    <p class="text-[12px] opacity-85 mt-1">site visits in the last 30 days</p>
                </div>
                <div class="relative flex items-end justify-between gap-3 text-[12px]">
                    <span><b class="text-base">{{ $siteHealth['live'] }}</b> live · <b class="text-base">{{ $siteHealth['domains'] }}</b> own domains</span>
                    <span class="flex -space-x-2" aria-hidden="true">
                        <span class="w-7 h-7 rounded-full" style="background:color-mix(in srgb, var(--on-primary) 80%, transparent)"></span>
                        <span class="w-7 h-7 rounded-full" style="background:color-mix(in srgb, var(--on-primary) 45%, transparent)"></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="grid @xl:grid-cols-2 gap-4">
            {{-- Site health gauge (sample "Analytics") --}}
            <div class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Site health</h2>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-gray-600 dark:text-gray-300">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background:var(--primary)"></span>Live {{ $siteHealth['live'] }}</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span>Built, offline {{ $siteHealth['offline'] }}</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-300"></span>No pages {{ $siteHealth['empty'] }}</span>
                </div>
                <x-admin-gauge class="mt-4"
                    :segments="[[$siteHealth['live'], 'var(--primary)'], [$siteHealth['offline'], '#fcd34d'], [$siteHealth['empty'], '#fda4af']]"
                    :value="$siteHealth['pct'].'%'" caption="of sites are live" />
            </div>

            {{-- Latest signups (sample "Last transactions") --}}
            <div class="{{ $panel }} p-5">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-[15px] font-bold text-gray-900 dark:text-white">Latest signups</h2>
                    <a href="{{ route('admin.accounts', ['sort' => 'newest']) }}" wire:navigate class="text-[12px] font-bold hover:underline" style="color:var(--primary)">All →</a>
                </div>
                @forelse ($signups->take(5) as $u)
                    <a href="{{ route('admin.account', $u->id) }}" wire:navigate
                       class="flex items-center gap-3 py-2.5 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <x-avatar :src="$u->avatar" :initials="strtoupper(substr($u->name, 0, 1))" size="w-10 h-10" textSize="text-sm font-bold" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-[14px] font-bold text-gray-900 dark:text-white truncate">{{ $u->name }}</span>
                            <span class="block text-[11.5px] text-gray-500 dark:text-gray-400">{{ $u->created_at->format('j M Y') }} · {{ $u->sites_count }} {{ Str::plural('site', $u->sites_count) }}</span>
                        </span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @empty
                    <p class="py-8 text-center text-sm text-gray-400">No accounts yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Trend charts --}}
        <div class="grid @xl:grid-cols-2 gap-4">
            <div class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Signups · 30 days</h2>
                <div id="pd-signups-chart" wire:ignore></div>
            </div>
            <div class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Storage growth (MB)</h2>
                <div id="pd-storage-chart" wire:ignore></div>
            </div>
        </div>

        <div class="grid @xl:grid-cols-2 gap-4">
            {{-- Storage hogs (upsell radar) --}}
            <div class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white mb-3">Top accounts by storage</h2>
                <div class="space-y-3">
                    @forelse ($topStorage as $row)
                        <a href="{{ route('admin.account', $row['user']->id) }}" wire:navigate class="block group">
                            <span class="flex items-center justify-between gap-2 text-[12.5px]">
                                <b class="text-gray-800 dark:text-gray-100 truncate">{{ $row['user']->name }}</b>
                                <span class="tabular-nums shrink-0 {{ ($row['pct'] ?? 0) > 85 ? 'text-rose-600 font-bold' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $fmtBytes($row['bytes']) }}@if ($row['pct'] !== null) · {{ $row['pct'] }}%@endif
                                </span>
                            </span>
                            <span class="block mt-1.5 h-2 rounded-full bg-gray-100 dark:bg-white/[0.07] overflow-hidden">
                                <span class="block h-full rounded-full" style="width: {{ $row['pct'] ?? 4 }}%; background: {{ ($row['pct'] ?? 0) > 85 ? '#f43f5e' : 'var(--primary)' }}"></span>
                            </span>
                        </a>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">No media stored yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Everything stored across the CMS --}}
            <div class="{{ $panel }} p-5">
                <h2 class="text-[15px] font-bold text-gray-900 dark:text-white mb-3">Content across all sites</h2>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ([
                        ['Pages', $stats['pages']], ['Components', $stats['components']], ['Posts', $stats['posts']],
                        ['Forms', $stats['forms']], ['Responses', $stats['responses']], ['Contacts', $stats['contacts']],
                        ['Bookings', $stats['bookings']], ['Orders', $stats['orders']], ['Donations', $stats['donations']],
                    ] as [$label, $n])
                        <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] px-2 py-3 text-center">
                            <p class="font-display text-lg font-extrabold tabular-nums text-gray-900 dark:text-white leading-none">{{ number_format($n) }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 truncate">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══ RIGHT rail: summary ══ --}}
    <x-slot:quick>
        {{-- Templates call-to-action (sample "More features?") --}}
        <a href="{{ route('admin.templates') }}" wire:navigate
           class="block rounded-[1.75rem] p-5 shadow-sm hover:-translate-y-0.5 transition-transform"
           style="background:var(--foreground);color:var(--background)">
            <span class="flex items-start gap-3">
                <svg class="w-7 h-7 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                <span class="min-w-0">
                    <span class="block font-display text-[17px] font-bold">{{ $attention ? 'Templates need you' : 'Templates' }}</span>
                    <span class="block mt-1 text-[12.5px] opacity-80">
                        {{ $templateStats['published'] }} published
                        @if ($templateStats['in_review']) · {{ $templateStats['in_review'] }} waiting for review @endif
                        @if ($templateStats['building']) · {{ $templateStats['building'] }} building @endif
                        @if ($templateStats['failed_7d']) · {{ $templateStats['failed_7d'] }} failed builds this week @endif
                    </span>
                </span>
            </span>
            <span class="mt-4 inline-flex items-center min-h-[40px] px-4 rounded-full text-[13px] font-bold"
                  style="background:var(--background);color:var(--foreground)">Manage templates</span>
        </a>

        {{-- Plan mix (sample "Expenses & income" split bars) --}}
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white">Plan mix</h3>
            <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                @foreach ($plans as $plan)
                    <div>
                        <p class="font-display text-2xl font-extrabold tabular-nums text-gray-900 dark:text-white leading-none">{{ round($plan['count'] / $planTotal * 100) }}%</p>
                        <p class="text-[11.5px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $plan['name'] }} · {{ $plan['count'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex gap-1.5 h-4">
                @foreach ($plans->where('count', '>', 0) as $plan)
                    <span class="rounded-full" style="flex: {{ $plan['count'] }} 1 0; background: {{ $plan['color'] }}" title="{{ $plan['name'] }}"></span>
                @endforeach
                @if ($plans->sum('count') === 0)
                    <span class="flex-1 rounded-full bg-gray-100 dark:bg-white/[0.07]"></span>
                @endif
            </div>
        </div>

        {{-- Trials ending --}}
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Trials ending soon</h3>
            @forelse ($expiringTrials as $trial)
                <a href="{{ route('admin.account', $trial->user_id) }}" wire:navigate
                   class="flex items-center justify-between gap-2 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0">
                        <b class="block text-[13px] text-gray-800 dark:text-gray-100 truncate">{{ $trial->user?->name }}</b>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $trial->user?->email }}</span>
                    </span>
                    <span class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-full {{ $trial->trialDaysLeft() <= 2 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $trial->trialDaysLeft() }}d left</span>
                </a>
            @empty
                <p class="py-4 text-center text-[12.5px] text-gray-400">No trials ending this week.</p>
            @endforelse
        </div>

        {{-- Busiest accounts --}}
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Busiest accounts · visits 30d</h3>
            @if (count($topVisits)) <x-analytics.bar-list :items="$topVisits" />
            @else <p class="py-4 text-center text-[12.5px] text-gray-400">No traffic yet.</p> @endif
        </div>

        {{-- Latest activity --}}
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-3">Latest activity</h3>
            @forelse ($feed->take(10) as $log)
                <div class="flex gap-3 pb-3.5 last:pb-0 relative">
                    @unless ($loop->last)
                        <span class="absolute left-[5px] top-4 bottom-0 w-px bg-gray-100 dark:bg-white/[0.06]"></span>
                    @endunless
                    <span class="w-[11px] h-[11px] rounded-full mt-1 shrink-0 ring-4 ring-white dark:ring-[#1d1e2a]" style="background: {{ $log->accent() }}"></span>
                    <div class="min-w-0">
                        <p class="text-[12.5px] font-semibold text-gray-900 dark:text-white truncate">{{ $log->title }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                            @if ($log->actor)
                                <a href="{{ route('admin.account', $log->actor->id) }}" wire:navigate class="font-semibold hover:underline">{{ $log->actor->name }}</a> ·
                            @endif
                            {{ $log->created_at->diffForHumans(short: true) }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="py-4 text-center text-[12.5px] text-gray-400">No activity recorded yet.</p>
            @endforelse
        </div>
    </x-slot:quick>
</x-tri-layout>

@script
<script>
(function () {
    if (typeof ApexCharts === 'undefined') return;
    const css = getComputedStyle(document.documentElement);
    const primary = css.getPropertyValue('--primary').trim() || '#f97316';
    const tertiary = css.getPropertyValue('--tertiary').trim() || primary;
    const isDark = document.documentElement.classList.contains('dark');
    const sub = isDark ? '#9ca3af' : '#4b5563';
    const c = @js($charts);
    const ax = { categories: c.labels, tickAmount: 6, labels: { style: { colors: sub, fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } };
    const grid = { borderColor: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' };

    new ApexCharts(document.querySelector('#pd-signups-chart'), {
        chart: { type: 'bar', height: 180, background: 'transparent', toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Signups', data: c.signups }],
        xaxis: ax, yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        colors: [primary], dataLabels: { enabled: false }, grid,
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data', style: { color: sub } },
    }).render();

    new ApexCharts(document.querySelector('#pd-storage-chart'), {
        chart: { type: 'area', height: 180, background: 'transparent', toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'MB stored', data: c.storage_mb }],
        xaxis: ax, yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        stroke: { curve: 'smooth', width: 2 }, colors: [tertiary],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } },
        dataLabels: { enabled: false }, grid,
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data', style: { color: sub } },
    }).render();
})();
</script>
@endscript
