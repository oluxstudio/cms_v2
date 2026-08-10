<x-page-layout title="Analytics" subtitle="Traffic and engagement for this site.">
<div class="flex flex-col gap-5 h-full overflow-y-auto pr-1">

    {{-- ── Header + range selector ─────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">Analytics</h1>
            <nav class="flex items-center gap-1.5 text-sm mt-1">
                <a href="{{ url($site->name . '/dashboard') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">Dashboard</a>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <span class="text-gray-600 dark:text-gray-300">Analytics</span>
            </nav>
        </div>
        <div class="inline-flex rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] p-1 text-sm">
            @foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All'] as $key => $label)
                <button wire:click="setRange('{{ $key }}')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-colors
                               {{ $range === $key ? 'bg-indigo-600 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── Stat tiles ──────────────────────────────────── --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-tile accent="ink" :value="number_format($totals['visits'])" label="page views" sub="in range" />
        <x-tile accent="lime" :value="number_format($totals['unique_visitors'])" label="unique visitors" sub="by daily fingerprint" />
        <x-tile accent="lavender" :value="number_format($totals['unique_sources'])" label="traffic sources" sub="distinct referrers" />
        <x-tile accent="cocoa" :value="count($charts['country'])" label="countries reached" sub="heatmap below" />
    </div>

    @unless ($hasData)
        <div class="bg-white dark:bg-[#1d1e2a] border border-dashed border-gray-200 dark:border-white/[0.08] rounded-2xl p-10 text-center">
            <p class="text-gray-700 dark:text-gray-200 font-semibold">No visits recorded yet</p>
            <p class="text-sm text-gray-400 mt-1 max-w-md mx-auto">
                Once the tracking beacon is live on your site, page views appear here — with traffic sources, locations, devices and browsers.
            </p>
        </div>
    @endunless

    {{-- ── World Traffic Heatmap ───────────────────────── --}}
    <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm">Traffic by Country</h2>
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-2.5 rounded-sm" style="background:#e6d6c6"></span> Low</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-2.5 rounded-sm" style="background:#a99df3"></span> Medium</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-2.5 rounded-sm" style="background:#7a7df2"></span> High</span>
            </div>
        </div>
        <div wire:ignore id="world-traffic-map" style="height:280px;"></div>
    </div>

    {{-- ── Charts row: channels + device + OS ──────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Sessions By Channel --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-4">Traffic by Source</h2>
            <div wire:ignore id="sessions-bar-chart" style="height:200px;"></div>
        </div>

        {{-- Device donut --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-4">Devices</h2>
            <div class="flex items-center gap-4">
                <div wire:ignore id="device-donut-chart" class="shrink-0" style="width:150px;height:150px;"></div>
                <div class="flex-1 min-w-0 space-y-2">
                    @foreach ($charts['device']['labels'] as $i => $label)
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $charts['device']['colors'][$i] }}"></span>
                                <span class="text-gray-700 dark:text-gray-300 truncate capitalize">{{ $label }}</span>
                            </span>
                            <span class="text-gray-900 dark:text-white font-semibold shrink-0 ml-2">{{ number_format($charts['device']['series'][$i]) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- OS donut --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-4">Operating Systems</h2>
            <div class="flex items-center gap-4">
                <div wire:ignore id="os-donut-chart" class="shrink-0" style="width:150px;height:150px;"></div>
                <div class="flex-1 min-w-0 space-y-2">
                    @foreach ($charts['os']['labels'] as $i => $label)
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $charts['os']['colors'][$i] }}"></span>
                                <span class="text-gray-700 dark:text-gray-300 truncate">{{ $label }}</span>
                            </span>
                            <span class="text-gray-900 dark:text-white font-semibold shrink-0 ml-2">{{ number_format($charts['os']['series'][$i]) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tables row: top sources + geo demographics ──── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Top traffic sources --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-gray-900 dark:text-white font-bold text-sm">Top Traffic Sources</h2>
                <button wire:click="toggleSources" class="text-xs font-semibold text-indigo-500 hover:text-indigo-600">
                    {{ $allSources ? 'Show top 10' : 'Show top 20' }}
                </button>
            </div>
            @if (count($referrers))
                <x-analytics.bar-list :items="$referrers" />
            @else
                <p class="text-sm text-gray-400 py-6 text-center">No external referrers yet — traffic is direct.</p>
            @endif
        </div>

        {{-- Geographic demographics --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-3">Top Locations</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Countries</p>
                    @if (count($geo['countries']))
                        <x-analytics.bar-list :items="$geo['countries']" />
                    @else
                        <p class="text-xs text-gray-400">No geo data.</p>
                    @endif
                </div>
                <div>
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Cities</p>
                    @if (count($geo['cities']))
                        <x-analytics.bar-list :items="$geo['cities']" />
                    @else
                        <p class="text-xs text-gray-400">No geo data.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Browsers + top pages ────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-3">Browsers</h2>
            @if (count($browsers)) <x-analytics.bar-list :items="$browsers" />
            @else <p class="text-sm text-gray-400 py-4">No data.</p> @endif
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-3">Top Pages</h2>
            @if (count($topPages)) <x-analytics.bar-list :items="$topPages" />
            @else <p class="text-sm text-gray-400 py-4">No data.</p> @endif
        </div>
    </div>

</div>

@script
<script>
(async function initAnalytics() {
    const isDark = document.documentElement.classList.contains('dark');
    const txt = isDark ? '#e5e7eb' : '#374151';
    const sub = isDark ? '#9ca3af' : '#6b7280';

    let bar, deviceDonut, osDonut, worldMap;

    function loadScript(src) {
        if (document.querySelector(`script[src="${src}"]`)) return Promise.resolve();
        return new Promise((res, rej) => {
            const s = document.createElement('script');
            s.src = src; s.onload = res; s.onerror = rej;
            document.head.appendChild(s);
        });
    }

    function donut(el, d) {
        return new ApexCharts(el, {
            chart: { type: 'donut', height: 150, width: 150, background: 'transparent', animations: { speed: 400 } },
            series: d.series, labels: d.labels, colors: d.colors,
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '70%', labels: { show: true,
                name: { show: true, fontSize: '11px', color: sub, offsetY: 6 },
                value: { show: true, fontSize: '18px', fontWeight: '700', color: txt, offsetY: -6 },
                total: { show: true, label: 'Total', fontSize: '11px', color: sub } } } } },
            stroke: { show: false }, legend: { show: false },
            tooltip: { theme: isDark ? 'dark' : 'light' },
            noData: { text: 'No data', style: { color: sub } },
        });
    }

    /* Bar — traffic by source */
    bar = new ApexCharts(document.querySelector('#sessions-bar-chart'), {
        chart: { type: 'bar', height: 200, background: 'transparent', toolbar: { show: false }, animations: { speed: 400 } },
        plotOptions: { bar: { distributed: true, borderRadius: 8, borderRadiusApplication: 'end', columnWidth: '45%' } },
        series: [{ name: 'Visits', data: @js($charts['channel']['series']) }],
        xaxis: { categories: @js($charts['channel']['labels']), labels: { style: { colors: sub, fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { show: false },
        colors: @js($charts['channel']['colors']),
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: '600', colors: [txt] }, offsetY: -6 },
        grid: { show: false }, legend: { show: false },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data', style: { color: sub } },
    });
    bar.render();

    deviceDonut = donut(document.querySelector('#device-donut-chart'), @js($charts['device'])); deviceDonut.render();
    osDonut = donut(document.querySelector('#os-donut-chart'), @js($charts['os'])); osDonut.render();

    /* World heatmap */
    await loadScript('https://cdn.jsdelivr.net/npm/jsvectormap@1.5.1/dist/js/jsvectormap.min.js');
    await loadScript('https://cdn.jsdelivr.net/npm/jsvectormap@1.5.1/dist/maps/world.js');
    if (!document.querySelector('link[data-jsvmap]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet'; l.setAttribute('data-jsvmap', '');
        l.href = 'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.1/dist/css/jsvectormap.min.css';
        document.head.appendChild(l);
    }
    worldMap = new jsVectorMap({
        selector: '#world-traffic-map', map: 'world', backgroundColor: 'transparent',
        zoomButtons: false, zoomOnScroll: false,
        regionStyle: {
            initial: { fill: isDark ? '#2b2836' : '#efeae0', stroke: isDark ? '#1d1e2a' : '#e0d8ca', strokeWidth: 0.5 },
            hover: { fill: '#7a7df2', cursor: 'pointer' },
        },
        series: { regions: [{ values: @js((object) $charts['country']), scale: ['#e6d6c6', '#7a7df2'], normalizeFunction: 'polynomial' }] },
    });

    /* Live update when the range changes (server-rendered parts refresh on their own) */
    $wire.on('analytics-updated', (payload) => {
        const c = payload.charts ?? payload;
        bar.updateOptions({ xaxis: { categories: c.channel.labels }, colors: c.channel.colors }, false, false);
        bar.updateSeries([{ data: c.channel.series }]);
        deviceDonut.updateOptions({ labels: c.device.labels, colors: c.device.colors }, false, false);
        deviceDonut.updateSeries(c.device.series);
        osDonut.updateOptions({ labels: c.os.labels, colors: c.os.colors }, false, false);
        osDonut.updateSeries(c.os.series);
        try { worldMap.series.regions[0].setValues(c.country || {}); } catch (e) {}
    });
})();
</script>
@endscript
</x-page-layout>
