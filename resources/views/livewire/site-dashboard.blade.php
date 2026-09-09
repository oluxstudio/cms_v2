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
                <p class="text-xs text-gray-400 mt-0.5">{{ now()->format('l, F j') }}</p>
            </div>
        </div>

        <div class="hidden md:flex items-center gap-2">
            <p class="text-sm font-semibold text-gray-400">{{ $siteTitle }}</p>
            <a href="{{ $site->templatePreviewUrl() }}" target="_blank" rel="noopener"
               title="Your site exactly as visitors see it"
               class="text-xs font-semibold text-indigo-400 hover:text-indigo-500 whitespace-nowrap">Live preview ↗</a>
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
    <x-carousel :labels="['📊 This week', '🔔 Activity', '⚡ Quick access']" :start="1">

        {{-- ════ LEFT RAIL ════ --}}
        <x-carousel.slide class="lg:!max-w-[25rem] px-5 pb-24 lg:pb-6 space-y-4 max-h-full overflow-y-auto
                      lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">

            {{-- Site selector pill --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3 bg-white/70 dark:bg-white/[0.06] backdrop-blur rounded-2xl px-4 py-3 shadow-sm hover:bg-white dark:hover:bg-white/[0.1] transition-colors">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow" style="background:linear-gradient(135deg,#f59e0b,#ef4444)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $siteTitle }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ $site->domain ?: 'switch site' }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-300 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </a>

            {{-- This week — the numbers that matter for running the business --}}
            <div class="rounded-3xl p-5 shadow-sm text-white"
                 style="background:linear-gradient(150deg,#1f2330,#11131c)">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-bold">This week</p>
                    <a href="{{ url($site->name.'/analytics') }}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-300 hover:text-indigo-200">
                        Analytics <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div><p class="text-2xl font-extrabold leading-none">{{ $weekStats['bookings'] }}</p><p class="text-[10px] text-white/50 mt-1">Bookings</p></div>
                    <div><p class="text-2xl font-extrabold leading-none">£{{ number_format($weekStats['revenue_cents'] / 100, 0) }}</p><p class="text-[10px] text-white/50 mt-1">Collected</p></div>
                    <div><p class="text-2xl font-extrabold leading-none">{{ $weekStats['leads'] }}</p><p class="text-[10px] text-white/50 mt-1">New leads</p></div>
                </div>
            </div>

            {{-- Ops cards: what needs doing, today's diary, money owed, setup --}}
            @include('partials.dashboard-actions')
            @include('partials.dashboard-checklist')
            @include('partials.dashboard-agenda')
            @include('partials.dashboard-vertical')
            @include('partials.dashboard-money')

            {{-- Project Activity tile (amber, Lisso-style) --}}
            <div class="bg-amber-50 dark:bg-amber-500/10 rounded-3xl p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">Project Activity</span>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-200/80 dark:bg-amber-500/30 text-amber-800 dark:text-amber-300">Statistic</span>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center">
                    @foreach([
                        ['Pages',     $pagesCount,     'pages'],
                        ['Assets',    $mediaCount,     'media'],
                        ['Forms',     $formsCount,     'forms'],
                        ['Responses', $responsesCount, 'forms'],
                    ] as [$lbl, $val, $seg])
                    <a href="{{ url($site->name.'/'.$seg) }}" class="flex flex-col items-center gap-0.5 hover:opacity-80 transition-opacity">
                        <span class="text-2xl font-extrabold text-gray-900 dark:text-white leading-none">{{ $val }}</span>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">{{ $lbl }}</span>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Commerce tiles — bookings, orders, stock, invoices. Only rendered
                 when the feature is on and there is something to show. --}}
            @if ($commerceTiles)
            <div class="grid grid-cols-2 gap-3">
                @foreach ($commerceTiles as $t)
                    <a href="{{ url($site->name.'/'.$t['seg']) }}"
                       class="bg-[#f2efe8] dark:bg-[#282433] rounded-[1.4rem] p-3.5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center mb-2" style="background:{{ $t['bg'] }};color:{{ $t['fg'] }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                        </span>
                        <p class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white leading-none">{{ $t['value'] }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ $t['label'] }}</p>
                        @if ($t['hint']) <p class="text-[10px] font-semibold text-rose-500 mt-0.5">{{ $t['hint'] }}</p> @endif
                    </a>
                @endforeach
            </div>
            @endif

            {{-- KPI pair — ink hero + lime accent --}}
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ url($site->name.'/contacts') }}" class="rounded-[1.4rem] p-4 shadow-sm bg-[#332433] text-white hover:shadow-md hover:-translate-y-0.5 transition-all">
                    <p class="text-3xl font-extrabold tracking-tight leading-none">{{ $contactsCount }}</p>
                    <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:#d9f068;color:#2b3110">Leads</span>
                </a>
                <a href="{{ url($site->name.'/forms') }}" class="rounded-[1.4rem] p-4 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all" style="background:#d9f068">
                    <p class="text-3xl font-extrabold tracking-tight leading-none" style="color:#2b3110">{{ $responsesCount }}</p>
                    <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#332433] text-white">Responses</span>
                </a>
            </div>

        </x-carousel.slide>

        {{-- ════ CENTER — Activity Feed ════ --}}
        <x-carousel.slide class="lg:flex-1 px-3 lg:px-5 pb-24 lg:pb-8 overflow-y-auto no-scrollbar main-body">
            <div class="max-w-[35rem] mx-auto">

            <div class="flex items-center justify-between mb-4 pt-1">
                <h2 class="text-base font-extrabold text-gray-900 dark:text-white tracking-tight">Recent Activity</h2>
            </div>

            {{-- Activity cards — grouped by what they relate to --}}
            @php
                // One group per entity — uploads land in Assets, page edits in
                // Pages, submissions in Forms, and so on.
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
                $activityGroups = collect($recentActivities)->groupBy(fn ($a) => $activityGroup($a['entity_type'] ?? ''));
                $groupOrder = collect(['Pages', 'Assets', 'Components', 'Forms', 'Leads', 'Bookings', 'Invoices', 'Tasks', 'Team', 'Other'])
                    ->filter(fn ($g) => $activityGroups->has($g));
            @endphp

            @forelse ($groupOrder as $groupLabel)
            @php $groupItems = $activityGroups[$groupLabel]; $groupCount = $groupItems->count(); @endphp
            {{-- ONE tile per group: latest entry shown, the rest expand inside. --}}
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-sm border border-gray-100/80 dark:border-white/[0.05] mb-3 overflow-hidden hover:shadow-md transition-shadow"
                 x-data="{ open: false }">
                <div class="flex items-center gap-2 px-5 pt-3.5">
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">{{ $groupLabel }}</p>
                    @if ($groupCount > 1)
                        <span class="text-[10px] font-bold min-w-[1.15rem] text-center px-1.5 py-0.5 rounded-full" style="background:#d9f068;color:#2b3110">{{ $groupCount }}</span>
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
                <div :class="open ? 'activity-timeline' : ''">
                    @include('partials.dashboard-activity-item', ['act' => $groupItems->first()])

                    @if ($groupCount > 1)
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms>
                        @foreach ($groupItems->skip(1) as $act)
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

        {{-- ════ RIGHT RAIL — Quick access ════ --}}
        <x-carousel.slide class="lg:!w-[270px] xl:!w-[290px] flex flex-col px-5 pb-24 lg:pb-6 gap-3
                      max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] no-scrollbar">

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
