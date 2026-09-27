<x-tri-layout title="Analytics" subtitle="Traffic and engagement for this site." :site-name="$site->name"
    :labels="['📊 Totals', '📈 Analytics', '🏆 Top lists']" quick-width="lg:!w-[410px]">

    {{-- ── LEFT rail: stat tiles ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="number_format($totals['visits'])" label="Page views"
                icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                sub="in range" />
        <x-tile accent="lime" :value="number_format($totals['unique_visitors'])" label="Unique visitors"
                icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                sub="daily fingerprint" />
        <x-tile accent="lavender" :value="number_format($totals['unique_sources'])" label="Traffic sources"
                icon="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-.928-8.9a4 4 0 015.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"
                sub="distinct referrers" />
        <x-tile accent="sky" :value="count($charts['country'])" label="Countries"
                icon="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18"
                sub="on the heatmap" />
    </div>
    </x-slot:rail>

<div class="max-w-[52rem] mx-auto flex flex-col gap-5">

    {{-- ── Range selector ─────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
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

    {{-- ══ RIGHT rail: the page's top lists + related ══ --}}
    <x-slot:quick>
        {{-- Top traffic sources --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Top traffic sources</h3>
                <button wire:click="toggleSources" class="text-[11px] font-semibold text-indigo-500 hover:underline">
                    {{ $allSources ? 'Top 10' : 'Top 20' }}
                </button>
            </div>
            @if (count($referrers))
                <x-analytics.bar-list :items="$referrers" />
            @else
                <p class="text-[12px] text-gray-400 py-4 text-center">No external referrers yet — traffic is direct.</p>
            @endif
        </div>

        {{-- Top pages --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-3">Top pages</h3>
            @if (count($topPages)) <x-analytics.bar-list :items="$topPages" />
            @else <p class="text-[12px] text-gray-400 py-4 text-center">No data yet.</p> @endif
        </div>

        {{-- Top locations --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-3">Top locations</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Countries</p>
                    @if (count($geo['countries'])) <x-analytics.bar-list :items="$geo['countries']" />
                    @else <p class="text-[11px] text-gray-400">No geo data.</p> @endif
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Cities</p>
                    @if (count($geo['cities'])) <x-analytics.bar-list :items="$geo['cities']" />
                    @else <p class="text-[11px] text-gray-400">No geo data.</p> @endif
                </div>
            </div>
        </div>

        {{-- Browsers --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-3">Browsers</h3>
            @if (count($browsers)) <x-analytics.bar-list :items="$browsers" />
            @else <p class="text-[12px] text-gray-400 py-4 text-center">No data yet.</p> @endif
        </div>

        {{-- Related elsewhere in the app --}}
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Pages', 'the content these visits land on', $site->name.'/pages'],
                ['Posts', 'publish more to grow traffic', $site->name.'/posts'],
                ['Go live', 'your domains & serving status', $site->name.'/publish'],
            ] as [$rl, $rd, $ru])
                <a href="{{ url($ru) }}" class="fx flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">{{ $rd }}</span>
                    </span>
                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
