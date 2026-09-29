@php
    $tier = $sub->tier();
    $fmtBytes = function (int $b): string {
        if ($b >= 1073741824) return number_format($b / 1073741824, 1).' GB';
        if ($b >= 1048576) return number_format($b / 1048576, 1).' MB';
        return number_format(max($b, 0) / 1024, 1).' KB';
    };
    $storageUsed = $sub->storageUsedBytes();
    $storageMb = $sub->storageLimitMb();
@endphp
<x-tri-layout :title="$user->name" :subtitle="$user->email"
    :labels="['📊 Numbers', '👤 Account', 'ℹ️ Summary']" quick-width="lg:!w-[320px] xl:!w-[340px]">

    <x-slot:header>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-full text-xs font-bold text-white" style="background: {{ $tier['color'] }}">{{ $sub->badgeLabel() }}</span>
            <a href="{{ route('admin.accounts', ['q' => $user->email]) }}" wire:navigate
               class="fx px-3.5 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">
                Manage pricing
            </a>
            @unless ($user->isSuper() || $user->is(auth()->user()))
                <form method="POST" action="{{ route('admin.impersonate', $user->id) }}">
                    @csrf
                    <button type="submit" data-confirm="View the app as {{ $user->name }}? You'll act as them for up to 60 minutes; this is recorded in their activity."
                            class="fx px-3.5 py-1.5 rounded-xl text-xs font-bold" style="background:var(--primary);color:var(--on-primary)">
                        View as this client
                    </button>
                </form>
            @endunless
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: this account's numbers ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$sub->badgeLabel()" label="Plan" :sub="'joined '.$user->created_at->format('j M Y')"
                style="background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)"
                icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.3 0 2.4.5 2.8 1.3M12 8V7m0 10v-1m0 1c-1.3 0-2.4-.5-2.8-1.3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="sky" :value="$sub->sitesUsage()" label="Sites" :sub="$sites->where('live', true)->count().' live'"
                icon="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9" />
        <x-tile accent="lavender" :value="$fmtBytes($storageUsed)" label="Storage" :sub="$storageMb ? 'of '.$storageMb.' MB' : 'no limit'"
                icon="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z" />
        <x-tile accent="lime" :value="$lastSeen ? $lastSeen->diffForHumans(short: true) : '—'" label="Last seen" sub="activity"
                icon="M13 10V3L4 14h7v7l9-11h-7z" />
        <x-tile accent="cocoa" :value="$tokens->count()" label="API tokens"
                :sub="$tokens->first()?->last_used_at ? 'used '.$tokens->first()->last_used_at->diffForHumans(short: true) : 'none used'"
                icon="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
    </div>
    </x-slot:rail>

<div class="max-w-[52rem] mx-auto">

    {{-- ── Sites ── --}}
    <h2 class="mb-2 text-sm font-bold text-gray-900 dark:text-white">Sites ({{ $sites->count() }})</h2>
    @if ($sites->isEmpty() && $memberSites->isEmpty())
        <p class="text-sm text-gray-400">No sites yet.</p>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($sites as $s)
                <a href="{{ route('site.dashboard', ['siteID' => $s->name]) }}"
                   class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-4 hover:border-indigo-300 transition-colors">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $s->name }}</p>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold
                                     {{ $s->live ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                            {{ $s->live ? 'Live' : 'Draft' }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">{{ $s->pages_count }} pages · {{ $s->media_count }} media · created {{ $s->created_at->format('M j, Y') }}</p>
                    @if ($s->domain)<p class="text-[11px] truncate mt-0.5" style="color:var(--primary)">{{ $s->domain }}</p>@endif
                </a>
            @endforeach
            @foreach ($memberSites as $s)
                <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.08] p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $s->name }}</p>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                            Member · {{ $s->pivot->role ?? 'editor' }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">Owned by another account</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Per-site usage ── --}}
    @if ($sites->isNotEmpty())
    <h2 class="mt-6 mb-2 text-sm font-bold text-gray-900 dark:text-white">Usage by site</h2>
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left" style="min-width:760px">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                        @foreach (['Site', 'Storage', 'Pages', 'Products', 'Bookings', 'Orders', 'Revenue', 'Contacts', 'Messages', 'Visits 30d'] as $th)
                            <th class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sites as $s)
                    <tr class="border-b border-gray-50 dark:border-white/[0.04] last:border-0">
                        <td class="px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $s->name }}
                            @if($s->live)<span class="ml-1 text-[9px] font-bold text-emerald-500 uppercase">live</span>@endif
                        </td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $fmtBytes((int) ($usage['media_bytes'][$s->id] ?? 0)) }}</td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ $s->pages_count }}</td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ (int) ($usage['products'][$s->id] ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ (int) ($usage['bookings'][$s->id] ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ (int) ($usage['orders'][$s->id] ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-xs font-bold tabular-nums text-gray-900 dark:text-white">{{ \App\Support\Money::format((int) ($usage['revenue_cents'][$s->id] ?? 0), 'gbp') }}</td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ (int) ($usage['contacts'][$s->id] ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ (int) ($usage['messages'][$s->id] ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-xs font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format((int) ($usage['visits_30d'][$s->id] ?? 0)) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">Traffic — all sites, 30 days</h2>
        <div id="pa-visits-chart" wire:ignore></div>
    </div>
    @endif

    {{-- ── Diary / timeline ── --}}
    <div class="mt-7 flex flex-wrap items-center gap-2">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white mr-2">Activity diary</h2>
        @foreach (['all' => 'All', 'account' => 'Logins & security', 'content' => 'Sites & content', 'api' => 'API'] as $key => $label)
            <button wire:click="setFilter('{{ $key }}')"
                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-colors
                           {{ $filter === $key ? 'text-white' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.08]' }}"
                    @if ($filter === $key) style="background:var(--primary)" @endif>{{ $label }}</button>
        @endforeach
    </div>

    <div class="mt-3 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5 sm:p-6">
        @forelse ($days as $date => $entries)
            @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
            <div class="sticky top-0 z-10 -mx-2 px-2 py-1 bg-white/90 dark:bg-[#1d1e2a]/90 backdrop-blur">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400">
                    {{ $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('D, M j, Y')) }}
                </span>
            </div>
            <div class="mt-2 mb-5 space-y-0">
                @foreach ($entries as $entry)
                    @php $log = $entry['log']; @endphp
                    <div class="flex gap-3 relative pb-4 last:pb-1">
                        @unless ($loop->last)
                            <span class="absolute left-[13px] top-7 bottom-0 w-px bg-gray-100 dark:bg-white/[0.06]"></span>
                        @endunless
                        @if ($entry['kind'] === 'account')
                            <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center ring-4 ring-white dark:ring-[#1d1e2a]"
                                  style="background:color-mix(in srgb, {{ $log->accent() }} 18%, transparent)">
                                <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $log->accent() }}"></span>
                            </span>
                        @else
                            @php [$bg, $fg] = $log->iconColors(); @endphp
                            <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center ring-4 ring-white dark:ring-[#1d1e2a]"
                                  style="background: {{ $bg }}; color: {{ $fg }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $log->iconPath() }}"/></svg>
                            </span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $log->title }}</p>
                                @if ($entry['kind'] === 'account')
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase"
                                          style="background:color-mix(in srgb, {{ $log->accent() }} 15%, transparent); color: {{ $log->accent() }}">{{ $log->category }}</span>
                                @else
                                    @php [$badge, $bBg, $bFg] = $log->actionBadge(); @endphp
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase" style="background: {{ $bBg }}; color: {{ $bFg }}">{{ $badge }}</span>
                                    @if ($log->site)
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-300">{{ $log->site->name }}</span>
                                    @endif
                                @endif
                            </div>
                            @if ($log->description)
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $log->description }}</p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-0.5 tabular-nums">{{ $entry['at']->format('g:i a') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="py-10 text-center text-sm text-gray-400">Nothing recorded for this account yet.</p>
        @endforelse

        @if ($hasMore)
            <button wire:click="loadMore"
                    class="mt-2 w-full py-2 rounded-xl text-xs font-bold text-gray-500 dark:text-gray-300 bg-gray-50 dark:bg-white/[0.04] hover:bg-gray-100 dark:hover:bg-white/[0.07]">
                Load older entries
                <span wire:loading wire:target="loadMore">…</span>
            </button>
        @endif
    </div>
</div>

    <x-slot:quick>
        <div class="rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([['All accounts', 'search, plans & pricing', route('admin.accounts')], ['Dashboard', 'platform numbers', route('admin.dashboard')], ['Templates', 'catalog, uploads & review', route('admin.templates')]] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" wire:navigate class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>

@script
<script>
(function () {
    const el = document.querySelector('#pa-visits-chart');
    if (!el || typeof ApexCharts === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    const sub = isDark ? '#9ca3af' : '#6b7280';
    const c = @js($charts);
    new ApexCharts(el, {
        chart: { type: 'area', height: 170, background: 'transparent', toolbar: { show: false } },
        series: [{ name: 'Visits', data: c.visits }],
        xaxis: { categories: c.labels, tickAmount: 6, labels: { style: { colors: sub, fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        stroke: { curve: 'smooth', width: 2 }, colors: [getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#f97316'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } },
        dataLabels: { enabled: false },
        grid: { borderColor: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No traffic yet', style: { color: sub } },
    }).render();
})();
</script>
@endscript
