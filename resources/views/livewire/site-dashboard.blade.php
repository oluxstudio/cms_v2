@php
    $firstName  = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
    $siteTitle  = \Illuminate\Support\Str::headline($site->name);
    $cardColors = ['#6366f1','#8b5cf6','#ec4899','#0ea5e9','#10b981','#f59e0b'];
@endphp

{{-- Full-screen canvas: left rail | center | right rail --}}
<div class="min-h-full flex flex-col app-bg">

    {{-- Ambient colored elements — this page paints its own app-bg surface,
         so the blobs must live INSIDE it to show through. --}}
    <x-bg-ambient />

    {{-- ── Greeting row ── --}}
    <div class="flex items-center justify-between px-6 py-5 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-full flex items-center justify-center text-white text-sm font-bold ring-2 ring-white shadow"
                 style="background:linear-gradient(135deg,var(--primary),#ec4899)">
                {{ auth()->user()->initials() }}
            </div>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white leading-none">Hi, {{ $firstName }}!</h1>
                <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mt-0.5">{{ now()->format('l, F j') }}</p>
            </div>
        </div>

        <div class="hidden md:flex items-center gap-2">
            <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $siteTitle }}</p>
            <x-preview-button :href="$site->templatePreviewUrl()" small />
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-400 mr-1">Team</span>
            <div class="flex -space-x-2">
                @foreach (array_slice($team, 0, 3) as $m)
                    <div class="w-8 h-8 rounded-full ring-2 ring-white flex items-center justify-center text-[10px] font-bold text-white"
                         style="background:{{ $cardColors[$loop->index % count($cardColors)] }}" title="{{ $m['name'] }}">
                        {{ $m['initials'] }}
                    </div>
                @endforeach
                @if (count($team) > 3)
                    <div class="w-8 h-8 rounded-full ring-2 ring-white bg-gray-900 text-white flex items-center justify-center text-[10px] font-bold">+{{ count($team) - 3 }}</div>
                @endif
                <a href="{{ url($site->name.'/team') }}"
                   class="w-8 h-8 rounded-full ring-2 ring-white bg-white text-gray-500 hover:text-indigo-600 flex items-center justify-center shadow-sm" title="Manage team">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- ── Three panes: mobile swipe carousel · desktop side-by-side ── --}}
    @php
        $tr = $traffic;
        $trend = $tr['visitors_prev'] > 0
            ? (($d = (int) round(($tr['visitors'] - $tr['visitors_prev']) / $tr['visitors_prev'] * 100)) >= 0 ? '+'.$d : $d).'%'
            : '7 days';
        $liveHost = $site->live && $site->domain ? $site->domain : ($site->subdomainHost() ?: 'not published');
        $commerceAccent = fn ($label) => match (true) {
            str_contains($label, 'overdue') => 'rose',
            str_contains($label, 'Invoice') => 'sky',
            str_contains($label, 'Order') => 'lavender',
            str_contains($label, 'stock') => 'cocoa',
            default => 'lime',
        };
    @endphp
    <x-carousel :labels="['📊 Site data', '🔔 Activity', 'ℹ️ Summary']" :start="1">

        {{-- ════ LEFT RAIL — the site's key numbers, as tiles ════ --}}
        <x-carousel.slide class="lg:!w-[25rem] lg:shrink-0 px-5 pb-24 lg:pb-6 max-h-full overflow-y-auto
                      lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
            @php
                // Only tiles for the add-ons this site actually runs: bookings,
                // store, invoices each bring their own tiles; nothing otherwise.
                $has = fn (string $f) => $site->hasFeature($f);
                $overdueTasks = collect($pendingTasks)->where('due_passed', true)->count();
                $railTiles = array_values(array_filter([
                    ['accent' => 'ink', 'value' => $site->live ? 'Live' : 'Offline', 'label' => 'Site status', 'sub' => $liveHost,
                        'href' => url($site->name.'/publish'), 'wide' => true,
                        'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9',
                        'style' => $site->live ? 'background:var(--primary);color:var(--on-primary);--tile-icon:var(--on-primary)' : 'background:var(--foreground);color:var(--background);--tile-icon:var(--background)'],
                    ['accent' => 'lavender', 'value' => $unreadMessages, 'label' => 'Unread messages', 'sub' => $unreadMessages ? 'new' : 'none new',
                        'href' => url($site->name.'/messages'),
                        'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
                    ['accent' => 'rose', 'value' => $unreadAlerts, 'label' => 'Alerts', 'sub' => $unreadAlerts ? 'unread' : 'none new',
                        'href' => url($site->name.'/alerts'),
                        'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
                    ['accent' => 'sky', 'value' => number_format($tr['visitors']), 'label' => 'Visitors', 'sub' => $trend, 'href' => url($site->name.'/analytics'),
                        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['accent' => 'lavender', 'value' => number_format($tr['views']), 'label' => 'Page views', 'sub' => '7 days', 'href' => url($site->name.'/analytics'),
                        'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
                    ['accent' => 'lime', 'value' => $weekStats['leads'], 'label' => 'New leads', 'sub' => '7 days', 'href' => url($site->name.'/contacts'),
                        'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
                    ['accent' => 'rose', 'value' => $responsesCount, 'label' => 'Form responses', 'sub' => $formsCount.' forms', 'href' => url($site->name.'/forms'),
                        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                    $has('bookings') ? ['accent' => 'lime', 'value' => $weekStats['bookings'], 'label' => 'Bookings', 'sub' => '7 days', 'href' => url($site->name.'/bookings'),
                        'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'] : null,
                    ($has('invoices') || $has('store')) ? ['accent' => 'cocoa', 'value' => '£'.number_format($weekStats['revenue_cents'] / 100, 0), 'label' => 'Collected',
                        'sub' => $outstandingCents ? '£'.number_format($outstandingCents / 100, 0).' owed' : '7 days',
                        'href' => url($site->name.'/'.($has('invoices') ? 'invoices' : 'orders')),
                        'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'] : null,
                    ['accent' => 'cocoa', 'value' => $publishedCount.' / '.$pagesCount, 'label' => 'Pages live',
                        'sub' => $pagesCount - $publishedCount ? ($pagesCount - $publishedCount).' drafts' : 'all live', 'href' => url($site->name.'/pages'),
                        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z'],
                    ['accent' => 'sky', 'value' => $openTasksCount, 'label' => 'Open tasks', 'sub' => $overdueTasks ? $overdueTasks.' overdue' : 'on track', 'href' => url($site->name.'/tasks'),
                        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                    ...array_map(fn ($t) => ['accent' => $commerceAccent($t['label']), 'value' => $t['value'], 'label' => $t['label'], 'sub' => $t['hint'] ?: null,
                        'href' => url($site->name.'/'.$t['seg']), 'icon' => $t['icon']], $commerceTiles),
                    ['accent' => 'lavender', 'value' => $mediaCount, 'label' => 'Assets', 'sub' => 'images & files', 'href' => url($site->name.'/media'),
                        'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ['accent' => 'rose', 'value' => count($team) + 1, 'label' => 'Team', 'sub' => count($team) ? 'incl. owner' : 'just you', 'href' => url($site->name.'/team'),
                        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ]));

                // Horizontal masonry: rows of two half tiles, broken up by a
                // full-width tile every few rows; a half tile left alone in its
                // row stretches to full width so no row has a gap.
                $halfRun = 0;
                foreach ($railTiles as $i => &$rt) {
                    $rt['wide'] = ($rt['wide'] ?? false) || $halfRun === 4;
                    if ($rt['wide']) {
                        if ($halfRun % 2) $railTiles[$i - 1]['wide'] = true;
                        $halfRun = 0;
                    } else {
                        $halfRun++;
                    }
                }
                unset($rt);
                if ($halfRun % 2) $railTiles[array_key_last($railTiles)]['wide'] = true;
            @endphp
            <div class="grid grid-cols-2 gap-3">
                @foreach ($railTiles as $rt)
                    <x-tile :accent="$rt['accent']" :value="$rt['value']" :label="$rt['label']" :sub="$rt['sub'] ?? null"
                            :href="$rt['href']" :icon="$rt['icon']" :wide="$rt['wide']" :style="$rt['style'] ?? null" />
                @endforeach
            </div>
        </x-carousel.slide>

        {{-- ════ CENTER — Activity Feed ════ --}}
        <x-carousel.slide class="lg:flex-1 px-3 lg:px-5 pb-24 lg:pb-8 overflow-y-auto no-scrollbar main-body">
            <div class="max-w-[35rem] mx-auto">

            <div class="flex items-center justify-between mb-4 pt-1">
                <h2 class="text-base font-extrabold text-gray-900 dark:text-white tracking-tight">Recent Activity</h2>
            </div>

            {{-- Activity cards — strictly newest-first; consecutive entries of
                 the same category merge into one expandable timeline tile. --}}
            @php
                $activityGroup = fn ($t) => match ($t) {
                    'page' => 'Pages',
                    'media' => 'Assets',
                    'component' => 'Components',
                    'form', 'form_response', 'response' => 'Forms',
                    'contact', 'estimate', 'interest' => 'Leads',
                    'booking' => 'Bookings',
                    'invoice' => 'Invoices',
                    'todo' => 'Tasks',
                    'member' => 'Team',
                    default => 'Other',
                };
                // Run-length grouping: keep chronological order, only merging
                // neighbours that share a category.
                $activityRuns = [];
                foreach ($recentActivities as $a) {
                    $label = $activityGroup($a['entity_type'] ?? '');
                    if ($activityRuns !== [] && $activityRuns[array_key_last($activityRuns)]['label'] === $label) {
                        $activityRuns[array_key_last($activityRuns)]['items'][] = $a;
                    } else {
                        $activityRuns[] = ['label' => $label, 'items' => [$a]];
                    }
                }
            @endphp

            @forelse ($activityRuns as $run)
            @php
                $groupLabel = $run['label'];
                $groupItems = collect($run['items']);
                $groupCount = $groupItems->count();
                // The FIRST tile previews up to 5 entries while collapsed;
                // the tiles below it stay compact with just their latest one.
                $preview = $loop->first ? 5 : 1;
            @endphp
            {{-- ONE tile per group: latest entries shown, the rest expand inside. --}}
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-sm border border-gray-100/80 dark:border-white/[0.05] mb-3 overflow-hidden hover:shadow-md transition-shadow"
                 x-data="{ open: false }">
                <div class="flex items-center gap-2 px-5 pt-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">{{ $groupLabel }}</p>
                    @if ($groupCount > 1)
                        <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $groupCount }}</span>
                    @endif
                    @if ($groupCount > $preview)
                        <button @click="open = ! open"
                                class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                            <span x-text="open ? 'Collapse' : 'Show all {{ $groupCount }}'"></span>
                            <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    @endif
                </div>

                {{-- Entries — newest always enters at the TOP; expanding reveals
                     the older ones beneath it, joined by the vertical timeline rail. --}}
                <div :class="{{ min($groupCount, $preview) > 1 ? 'true' : 'open' }} ? 'activity-timeline' : ''">
                    @foreach ($groupItems->take($preview) as $act)
                        @include('partials.dashboard-activity-item')
                    @endforeach

                    @if ($groupCount > $preview)
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms>
                        @foreach ($groupItems->skip($preview) as $act)
                            @include('partials.dashboard-activity-item')
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <p class="text-sm text-gray-400 dark:text-gray-500">No activity yet.</p>
            </div>
            @endforelse

            </div>
        </x-carousel.slide>

        {{-- ════ RIGHT RAIL — summaries of the site ════ --}}
        <x-carousel.slide class="lg:!w-[320px] xl:!w-[340px] lg:shrink-0 flex flex-col px-5 pb-24 lg:pb-6 gap-3
                      max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] no-scrollbar">

            {{-- Site at a glance --}}
            <div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05]">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $siteTitle }}</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                          style="{{ $site->live ? 'background:var(--primary);color:var(--on-primary)' : 'background:var(--foreground);color:var(--background)' }}">{{ $site->live ? 'Live' : 'Offline' }}</span>
                </div>
                @foreach ([
                    ['Address', $site->live && $site->domain ? $site->domain : ($site->subdomainHost() ?: '—')],
                    ['Template', Str::headline($site->template ?: 'none')],
                    ['Pages', $publishedCount.' of '.$pagesCount.' published'],
                    ['Created', $site->created_at?->format('j M Y')],
                ] as [$k, $v])
                    <div class="flex items-center justify-between gap-3 py-1.5 text-xs {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <span class="text-gray-500 dark:text-gray-400">{{ $k }}</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-100 truncate text-right">{{ $v }}</span>
                    </div>
                @endforeach
                <div class="flex items-center gap-2 mt-3">
                    <x-preview-button :href="$site->templatePreviewUrl()" small />
                    <a href="{{ url($site->name.'/connect') }}" class="fx px-3 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200">Edit site</a>
                </div>
            </div>

            @include('partials.dashboard-actions')
            @include('partials.dashboard-checklist')
            @include('partials.dashboard-agenda')
            @include('partials.dashboard-money')
            @include('partials.dashboard-vertical')

            {{-- Recent quotes --}}
            @if ($recentEstimates)
            <div class="rounded-3xl p-4 shadow-sm bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">Recent quotes</span>
                    <a href="{{ url($site->name.'/estimates') }}" class="text-[10px] font-bold hover:underline" style="color:var(--primary)">All →</a>
                </div>
                @foreach ($recentEstimates as $e)
                    <div class="flex items-center gap-2 py-1.5 text-xs {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <span class="font-mono text-gray-400 shrink-0">{{ $e['reference'] }}</span>
                        <span class="flex-1 min-w-0 text-gray-800 dark:text-gray-100 truncate">{{ $e['name'] }}</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300 shrink-0">{{ ucfirst($e['status']) }}</span>
                    </div>
                @endforeach
            </div>
            @endif

            <div class="border-t border-gray-100 dark:border-white/[0.06] shrink-0 mt-1"></div>

            {{-- Quick links --}}
            <div class="flex items-center justify-between pt-1 shrink-0">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">Quick access</h3>
                <a href="{{ url($site->name.'/tasks') }}" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">+ Task</a>
            </div>

            @foreach([
                ['New page',       $site->name.'/pages',     'M12 4v16m8-8H4',                                                                                                                                                                                                                                                       '#eef2ff','#6366f1'],
                ['Upload assets',  $site->name.'/media',     'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',                                                                                                                                                                                                     '#fef2f2','#ef4444'],
                ['New form',       $site->name.'/forms',     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',                                                                                                                                  '#fffbeb','#d97706'],
                ['Manage team',    $site->name.'/team',      'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',                                                       '#f5f3ff','#7c3aed'],
                ['View analytics', $site->name.'/analytics', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',                                                        '#f0fdf4','#16a34a'],
            ] as [$label, $href, $icon, $bg, $fg])
            <a href="{{ url($href) }}" class="shrink-0 flex items-center gap-3 bg-white dark:bg-[#1d1e2a] rounded-2xl px-4 py-3 shadow-sm border border-gray-100/80 dark:border-white/[0.05] hover:shadow-md transition-shadow group">
                <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:{{ $bg }};color:{{ $fg }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200 flex-1 truncate">{{ $label }}</span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endforeach

            {{-- Divider --}}
            <div class="border-t border-gray-100 dark:border-white/[0.06] shrink-0"></div>

            {{-- User-added quick links --}}
            <div class="flex items-center justify-between shrink-0">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white">My links</h3>
                <button wire:click="$toggle('addingLink')" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ $addingLink ? 'Cancel' : '+ Add link' }}
                </button>
            </div>

            @if ($addingLink)
            <form wire:submit="addQuickLink" class="shrink-0 bg-white dark:bg-[#1d1e2a] rounded-2xl p-3.5 shadow-sm border border-gray-100/80 dark:border-white/[0.05] space-y-2">
                <input wire:model="linkLabel" type="text" placeholder="Label (e.g. Brand guide)" required
                       class="w-full px-3 py-2 text-xs rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                @error('linkLabel')<p class="text-[10px] text-rose-500">{{ $message }}</p>@enderror
                <input wire:model="linkUrl" type="text" placeholder="URL or /{{ $site->name }}/pages" required
                       class="w-full px-3 py-2 text-xs rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                @error('linkUrl')<p class="text-[10px] text-rose-500">{{ $message }}</p>@enderror
                <button type="submit" class="w-full py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Save link</button>
            </form>
            @endif

            @forelse ($quickLinks as $i => $ql)
            <div class="shrink-0 flex items-center gap-3 bg-white dark:bg-[#1d1e2a] rounded-2xl pl-4 pr-2 py-3 shadow-sm border border-gray-100/80 dark:border-white/[0.05] hover:shadow-md transition-shadow group">
                <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#d9f068;color:#2b3110">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m7.328-7.328a4 4 0 015.656 5.656l-1.5 1.5"/></svg>
                </span>
                <a href="{{ str_starts_with($ql['url'], '/') ? url($ql['url']) : $ql['url'] }}"
                   @unless(str_starts_with($ql['url'], '/')) target="_blank" rel="noopener" @endunless
                   class="text-sm font-medium text-gray-700 dark:text-gray-200 flex-1 truncate">{{ $ql['label'] }}</a>
                <button wire:click="removeQuickLink({{ $i }})" title="Remove link"
                        class="w-7 h-7 flex items-center justify-center rounded-full text-gray-300 opacity-0 group-hover:opacity-100 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @empty
                @unless ($addingLink)
                <p class="text-[11px] text-gray-400 shrink-0">Pin your own shortcuts here — pages you use daily, docs, external tools.</p>
                @endunless
            @endforelse

        </x-carousel.slide>

        </x-carousel>
</div>
