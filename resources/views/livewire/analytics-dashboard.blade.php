<x-page-layout title="Analytics" subtitle="Traffic and engagement for this site.">
    @php
        $aSessions = array_sum(array_column($channelData, 'value'));
        $aDevices = array_sum(array_column($deviceData, 'value'));
        $topChannel = collect($channelData)->sortByDesc('value')->first();
        $topDevice = collect($deviceData)->sortByDesc('value')->first();
    @endphp

<div class="flex flex-col gap-5 h-full overflow-y-auto pr-1">

    {{-- ── Header ─────────────────────────────────────── --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">Analytics</h1>
            <nav class="flex items-center gap-1.5 text-sm mt-1">
                <a href="{{ url($site->name . '/dashboard') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">Dashboard</a>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <span class="text-gray-600 dark:text-gray-300">Analytics</span>
            </nav>
        </div>
        <span class="flex items-center gap-2 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 text-sm px-4 py-2.5 rounded-xl">
            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Today: {{ now()->format('M d') }}
        </span>
    </div>

    {{-- ── Stat tiles — app tile theme, real data ──────── --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-tile accent="ink" :value="number_format($aSessions)" label="sessions" sub="all channels" />
        <x-tile accent="lime" :value="($topChannel['value'] ?? 0).'%'" label="top traffic channel" :sub="$topChannel['name'] ?? '—'" />
        <x-tile accent="lavender" :value="number_format($topDevice['value'] ?? 0)" label="sessions on top device" :sub="$topDevice['name'] ?? '—'" />
        <x-tile accent="cocoa" :value="count($countryData)" label="countries reached" sub="heatmap below" />
    </div>

    {{-- ── World Traffic Heatmap ───────────────────────── --}}
    <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm">Traffic by Country</h2>
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-2.5 rounded-sm" style="background:#e6d6c6"></span> Low
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-2.5 rounded-sm" style="background:#a99df3"></span> Medium
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-2.5 rounded-sm" style="background:#7a7df2"></span> High
                </span>
            </div>
        </div>
        <div wire:ignore id="world-traffic-map" style="height:260px;"></div>
    </div>

    {{-- ── Bottom Charts Row ──────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Sessions By Channel --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-4">Sessions By Channel</h2>

            {{-- Custom colour-dot legend --}}
            <div class="flex justify-around mb-2">
                @foreach($channelData as $ch)
                    <div class="flex flex-col items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" style="background:{{ $ch['color'] }}"></span>
                        <span class="text-gray-500 dark:text-gray-400 text-xs">{{ $ch['name'] }}</span>
                    </div>
                @endforeach
            </div>

            <div wire:ignore id="sessions-bar-chart" style="height:180px;"></div>
        </div>

        {{-- Sessions Device --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-gray-900 dark:text-white font-bold text-sm">Sessions Device</h2>
                <span class="text-xs text-gray-400 border border-gray-200 dark:border-white/10 px-3 py-1.5 rounded-lg">last 7 days</span>
            </div>

            <div class="flex items-center gap-5">
                <div wire:ignore id="device-donut-chart" class="shrink-0" style="width:160px;height:160px;"></div>

                <div class="flex-1 min-w-0">
                    <div class="flex justify-end gap-5 text-xs text-gray-400 mb-3 pr-1">
                        <span>Day</span>
                        <span>Week</span>
                    </div>
                    <div class="space-y-3.5">
                        @foreach($deviceData as $dev)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $dev['color'] }}"></span>
                                    <span class="text-gray-700 dark:text-gray-300 text-sm truncate">{{ $dev['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-5 text-sm shrink-0 ml-2">
                                    <span class="text-gray-900 dark:text-white font-semibold">{{ number_format($dev['value']) }}</span>
                                    <span class="text-rose-500 dark:text-rose-400 w-5 text-right">{{ $dev['day'] }}</span>
                                    <span class="text-rose-500 dark:text-rose-400 w-5 text-right">{{ $dev['week'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</x-page-layout>

@script
<script>
(async function initAnalytics() {

    /* ── theme-aware chart colors ─────────────────── */
    const isDark = document.documentElement.classList.contains('dark');
    const txt = isDark ? '#e5e7eb' : '#374151';
    const sub = isDark ? '#9ca3af' : '#6b7280';

    /* ── helpers ─────────────────────────────────── */
    function loadScript(src) {
        if (document.querySelector(`script[src="${src}"]`)) return Promise.resolve();
        return new Promise((res, rej) => {
            const s = document.createElement('script');
            s.src = src; s.onload = res; s.onerror = rej;
            document.head.appendChild(s);
        });
    }

    /* ── Bar chart – Sessions by Channel ─────────── */
    const barChart = new ApexCharts(document.querySelector('#sessions-bar-chart'), {
        chart: {
            type: 'bar',
            height: 180,
            background: 'transparent',
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 500 },
        },
        plotOptions: {
            bar: {
                distributed: true,
                borderRadius: 8,
                borderRadiusApplication: 'end',
                columnWidth: '44%',
                dataLabels: { position: 'top' },
            },
        },
        series: [{ name: 'Sessions', data: @js(array_column($channelData, 'value')) }],
        xaxis: {
            categories: @js(array_column($channelData, 'name')),
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { show: false },
        colors: @js(array_column($channelData, 'color')),
        dataLabels: {
            enabled: true,
            formatter: v => v + '%',
            style: { fontSize: '11px', fontWeight: '600', colors: [txt] },
            offsetY: -6,
        },
        grid: { show: false },
        legend: { show: false },
        tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: v => v + '%' } },
    });
    barChart.render();

    /* ── Donut chart – Device breakdown ─────────── */
    const donutChart = new ApexCharts(document.querySelector('#device-donut-chart'), {
        chart: {
            type: 'donut',
            height: 160,
            width: 160,
            background: 'transparent',
            animations: { enabled: true, easing: 'easeinout', speed: 500 },
        },
        series: @js(array_column($deviceData, 'value')),
        labels: @js(array_column($deviceData, 'name')),
        colors: @js(array_column($deviceData, 'color')),
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '11px', color: sub, offsetY: 6 },
                        value: { show: true, fontSize: '20px', fontWeight: '700', color: txt, offsetY: -6 },
                        total: {
                            show: true,
                            label: 'Days',
                            fontSize: '11px',
                            color: sub,
                            formatter: () => '7',
                        },
                    },
                },
            },
        },
        stroke: { show: false },
        legend: { show: false },
        tooltip: { theme: isDark ? 'dark' : 'light' },
    });
    donutChart.render();

    /* ── World heatmap ───────────────────────────── */
    await loadScript('https://cdn.jsdelivr.net/npm/jsvectormap@1.5.1/dist/js/jsvectormap.min.js');
    await loadScript('https://cdn.jsdelivr.net/npm/jsvectormap@1.5.1/dist/maps/world.js');

    if (!document.querySelector('link[data-jsvmap]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.setAttribute('data-jsvmap', '');
        l.href = 'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.1/dist/css/jsvectormap.min.css';
        document.head.appendChild(l);
    }

    new jsVectorMap({
        selector: '#world-traffic-map',
        map: 'world',
        backgroundColor: 'transparent',
        zoomButtons: false,
        zoomOnScroll: false,
        regionStyle: {
            initial: {
                fill: isDark ? '#2b2836' : '#efeae0',
                stroke: isDark ? '#1d1e2a' : '#e0d8ca',
                strokeWidth: 0.5,
                fillOpacity: 1,
            },
            hover: { fill: '#7a7df2', cursor: 'pointer' },
        },
        series: {
            regions: [{
                values: @js($countryData),
                scale: ['#e6d6c6', '#7a7df2'],
                normalizeFunction: 'polynomial',
            }],
        },
    });

})();
</script>
@endscript
