@php
    $canManage = $this->canManage;
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $iconBtn = 'p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10';
    $total = $stats['total'];
    $filters = [
        'all' => ['All', $total],
        'used' => ['On pages', $stats['used']],
        'unused' => ['Unused', $stats['unused']],
        'source' => ['With data source', $stats['source']],
        'inactive' => ['Inactive', $stats['inactive']],
        'attention' => ['Needs attention', $stats['attention']],
    ];
    $sourceBadge = function ($c) {
        if ($c->site_template_id) return ['Template', 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300'];
        return match ($c->source ?? 'app') {
            'api' => ['API', 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300'],
            'imported' => ['Imported', 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300'],
            'app' => ['App', 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300'],
            default => [ucfirst($c->source), 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300'],
        };
    };
    $icons = [
        'block' => 'M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5',
        'page' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'unused' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
        'source' => 'M4 7v10c0 2.2 3.6 4 8 4s8-1.8 8-4V7M4 7c0 2.2 3.6 4 8 4s8-1.8 8-4M4 7c0-2.2 3.6-4 8-4s8 1.8 8 4m0 5c0 2.2-3.6 4-8 4s-8-1.8-8-4',
        'empty' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z',
        'inactive' => 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'edit' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'editor' => 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14',
        'copy' => 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
        'trash' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    ];
    $deleteMsg = fn ($c) => 'Delete “'.$c->name.'”? It is removed from every page it\'s attached to.';
@endphp
<x-tri-layout title="Components" subtitle="Standalone content components — build the fields once, attach to pages or link collections anywhere." :site-name="$site->name"
    :labels="['📊 Overview', '🧩 Components', '⚡ Quick access']">

    {{-- ── LEFT rail: the components at a glance (tap a tile to filter) ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$total" label="Components"
                :sub="$stats['recent'] ? $stats['recent'].' added this week' : number_format($stats['fields']).' '.Str::plural('field', $stats['fields'])"
                :icon="$icons['block']" />
        <x-tile accent="lime" :value="$stats['used']" label="On pages" :sub="'live · of '.$total"
                :icon="$icons['page']" role="button" wire:click="setFilter('used')" class="cursor-pointer" />
        <x-tile accent="lavender" :value="$stats['unused']" label="Unused" :sub="$stats['unused'] ? 'on no page' : 'all placed'"
                :icon="$icons['unused']" role="button" wire:click="setFilter('unused')" class="cursor-pointer" />
        <x-tile accent="sky" :value="$stats['source']" label="Data source" sub="fed by a collection"
                :icon="$icons['source']" role="button" wire:click="setFilter('source')" class="cursor-pointer" />
        <x-tile :accent="$stats['empty'] ? 'rose' : 'cocoa'" :value="number_format($stats['empty'])" label="Empty fields"
                :sub="$stats['empty'] ? 'in '.$stats['emptyComponents'].' '.Str::plural('component', $stats['emptyComponents']) : 'all filled in'"
                :icon="$icons['empty']" role="button" wire:click="setFilter('attention')" class="cursor-pointer" />
        <x-tile :accent="$stats['inactive'] ? 'rose' : 'cocoa'" :value="$stats['inactive']" label="Inactive"
                :sub="$stats['inactive'] ? 'parked by a template switch' : 'none parked'"
                :icon="$icons['inactive']" role="button" wire:click="setFilter('inactive')" class="cursor-pointer" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">
    @if ($errorMessage)
        <p class="px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    {{-- A component (view / edit) and a new component open on their own page, without the list --}}
    @unless ($viewingId !== null || $editingId !== null)
    {{-- ── Toolbar: search · collection · sort · layout · new, then filter pills and tags ── --}}
    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, tag or description…"
                       class="pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 w-full">
            </div>
            <select wire:model.live="filterCollection" title="Collection"
                    class="py-2 pl-3 pr-8 text-[13px] rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                <option value="">All collections</option>
                <option value="none">Standalone (no collection)</option>
                @foreach ($this->siteCollections as $col)<option value="{{ $col->id }}">{{ $col->name }}</option>@endforeach
            </select>
            <select wire:model.live="sort" title="Order"
                    class="py-2 pl-3 pr-8 text-[13px] rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                <option value="updated">Recently edited</option>
                <option value="name">Name A–Z</option>
                <option value="used">Most used</option>
                <option value="fields">Most fields</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            @if ($canManage)
            <a href="{{ route('site.components.create', $site->name) }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New component
            </a>
            @endif
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach ($filters as $key => [$label, $n])
                <button type="button" wire:click="setFilter('{{ $key }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                        {{ $filter === $key
                            ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                            : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                    {{ $label }} <span class="opacity-60">{{ $n }}</span>
                </button>
            @endforeach
        </div>
        @if (count($tags))
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mr-1">Tags</span>
            @foreach ($tags as $tag)
            <button type="button" wire:click="setTag(@js($tag))" @class([
                'px-2.5 py-0.5 rounded-full text-[12px] font-semibold border transition-colors',
                'bg-indigo-600 text-white border-indigo-600' => $filterTag === $tag,
                'bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08] hover:border-indigo-400' => $filterTag !== $tag,
            ])>#{{ $tag }}</button>
            @endforeach
        </div>
        @endif
    </div>

    @if ($components->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['block'] }}"/></svg>
            </span>
            @if ($siteTotal === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No components yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">A component is a named set of content fields — a hero, a pricing card, a footer — built once and placed on any page.</p>
                @if ($canManage)
                <a href="{{ route('site.components.create', $site->name) }}" wire:navigate class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Create the first component</a>
                @endif
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No component fits this search or filter.</p>
                <button type="button" wire:click="resetListing" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all components</button>
            @endif
        </div>

    @elseif ($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($components as $c)
                @php [$srcLabel, $srcClass] = $sourceBadge($c); $show = $this->pageUrl($c->id); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl hover:shadow-md transition-shadow overflow-hidden" wire:key="comp-{{ $c->id }}">
                    <a href="{{ $show }}" wire:navigate class="p-5 flex-1 block">
                        <div class="flex items-start gap-3">
                            <span class="w-11 h-11 rounded-xl grid place-items-center shrink-0 bg-indigo-100 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['block'] }}"/></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $c->name }}</span>
                                <span class="block text-[12px] text-gray-400 mt-0.5 truncate">
                                    <span class="inline-flex px-1.5 py-px rounded-md text-[10.5px] font-bold {{ $srcClass }}">{{ $srcLabel }}</span>
                                    {{ $c->creator?->name ?? $c->author ?? '' }} · edited {{ $c->last_edited->diffForHumans() }}
                                </span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-2xl font-extrabold tabular-nums leading-none {{ $c->nodes_count ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $c->nodes_count }}</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ Str::plural('field', $c->nodes_count) }}</span>
                            </span>
                        </div>
                        <p class="mt-3 text-[13px] text-gray-500 dark:text-gray-400 line-clamp-2">{{ $c->description ?: 'No description yet.' }}</p>

                        {{-- Pages it's on --}}
                        <span class="mt-3 flex flex-wrap items-center gap-1.5">
                            @forelse ($c->live_pages->take(3) as $p)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" title="{{ $p->url }}">● {{ $p->name }}</span>
                            @empty
                                @if ($c->is_inactive)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300" title="Only on pages of a template that isn't the current one">Inactive — {{ $c->placements_count }} parked {{ Str::plural('placement', $c->placements_count) }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">On no page</span>
                                @endif
                            @endforelse
                            @if ($c->live_pages_count > 3)<span class="text-[11px] text-gray-400">+{{ $c->live_pages_count - 3 }} more</span>@endif
                        </span>

                        {{-- Fields · data source · tags --}}
                        <span class="mt-2 flex flex-wrap items-center gap-1.5">
                            @if ($c->empty_nodes_count)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300">{{ $c->empty_nodes_count }} empty {{ Str::plural('field', $c->empty_nodes_count) }}</span>
                            @elseif (! $c->nodes_count)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300">No fields</span>
                            @endif
                            @foreach ($c->data_sources->take(2) as $srcName)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300" title="Data source">📦 {{ $srcName }}</span>
                            @endforeach
                            @foreach (($c->tags ?? []) as $tag)
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">#{{ $tag }}</span>
                            @endforeach
                        </span>
                    </a>
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        @if ($canManage)
                            <a href="{{ $this->pageUrl($c->id, true) }}" wire:navigate
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Edit</a>
                        @endif
                        <a href="{{ $show }}" wire:navigate class="{{ $btnSolid }} text-[12px] px-3 py-1.5">View</a>
                        <span class="ml-auto flex items-center gap-0.5">
                            <a href="{{ url($site->name.'/connect?component='.$c->id) }}" title="Open in the site editor" class="{{ $iconBtn }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['editor'] }}"/></svg>
                            </a>
                            @if ($canManage)
                            <button type="button" wire:click="duplicateComponent('{{ $c->id }}')" title="Duplicate" class="{{ $iconBtn }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['copy'] }}"/></svg>
                            </button>
                            <button type="button" wire:click="deleteComponent('{{ $c->id }}')" data-confirm="{{ $deleteMsg($c) }}" title="Delete"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['trash'] }}"/></svg>
                            </button>
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

    @else
        {{-- ── List & Compact (table; compact hides description, source and tags) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Component</th>
                            <th class="px-4 py-3 text-right">Fields</th>
                            <th class="px-4 py-3">Pages</th>
                            @unless ($compact)
                                <th class="px-4 py-3">Data source</th>
                                <th class="px-4 py-3">Edited</th>
                            @endunless
                            <th class="w-32 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($components as $c)
                            @php [$srcLabel, $srcClass] = $sourceBadge($c); @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors" wire:key="row-{{ $c->id }}">
                                <td class="{{ $pad }}">
                                    <a href="{{ $this->pageUrl($c->id) }}" wire:navigate class="block min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="font-semibold text-gray-900 dark:text-white truncate hover:underline">{{ $c->name }}</span>
                                            @unless ($compact)<span class="shrink-0 px-1.5 py-px rounded-md text-[10px] font-bold {{ $srcClass }}">{{ $srcLabel }}</span>@endunless
                                        </span>
                                        @unless ($compact)
                                            @if ($c->description)<span class="block text-[11px] text-gray-400 truncate max-w-[18rem]">{{ $c->description }}</span>@endif
                                            @if ($c->tags)
                                            <span class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($c->tags as $tag)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">#{{ $tag }}</span>@endforeach
                                            </span>
                                            @endif
                                        @endunless
                                    </a>
                                </td>
                                <td class="{{ $pad }} text-right whitespace-nowrap">
                                    <span class="font-bold tabular-nums text-gray-900 dark:text-white">{{ $c->nodes_count }}</span>
                                    @if ($c->empty_nodes_count)<span class="block text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ $c->empty_nodes_count }} empty</span>@endif
                                </td>
                                <td class="{{ $pad }} text-[12px]">
                                    @if ($c->is_used)
                                        <span class="text-emerald-700 dark:text-emerald-300">{{ $c->live_pages->take(2)->pluck('name')->implode(', ') }}@if ($c->live_pages_count > 2) +{{ $c->live_pages_count - 2 }}@endif</span>
                                    @elseif ($c->is_inactive)
                                        <span class="text-amber-700 dark:text-amber-300">Inactive</span>
                                    @else
                                        <span class="text-gray-400">On no page</span>
                                    @endif
                                </td>
                                @unless ($compact)
                                    <td class="{{ $pad }} text-[12px] {{ $c->has_source ? 'text-sky-700 dark:text-sky-300' : 'text-gray-400' }}">{{ $c->has_source ? $c->data_sources->implode(', ') : '—' }}</td>
                                    <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ $c->last_edited->diffForHumans() }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-0.5 justify-end">
                                        @if ($canManage)
                                        <a href="{{ $this->pageUrl($c->id, true) }}" wire:navigate title="Edit" class="{{ $iconBtn }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['edit'] }}"/></svg>
                                        </a>
                                        @endif
                                        <a href="{{ url($site->name.'/connect?component='.$c->id) }}" title="Open in the site editor" class="{{ $iconBtn }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['editor'] }}"/></svg>
                                        </a>
                                        @if ($canManage)
                                        <button type="button" wire:click="duplicateComponent('{{ $c->id }}')" title="Duplicate" class="{{ $iconBtn }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['copy'] }}"/></svg>
                                        </button>
                                        <button type="button" wire:click="deleteComponent('{{ $c->id }}')" data-confirm="{{ $deleteMsg($c) }}" title="Delete"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['trash'] }}"/></svg>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    @endunless

    {{-- ═══ DETAIL VIEW — every stored fact, on the reusable lightbox ═══ --}}
    @if ($viewingId !== null && $this->viewing)
    @php $v = $this->viewing; @endphp
    <x-page-panel :back="$this->pageUrl()" back-label="Back to components" icon="🧩" :title="$v->name" :subtitle="$v->description">
        <x-slot:badge>
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full {{ ($v->source ?? 'app') === 'api' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' }}">
                {{ ($v->source ?? 'app') === 'api' ? '🔌 API' : '🖥 App' }}</span>
        </x-slot:badge>

        {{-- Provenance grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach ([
                ['ID', '#'.$v->id, 'font-mono'],
                ['Created by', $v->creator?->name ?? $v->author ?? '—', ''],
                ['Tags', $v->tags ? '#'.implode(' #', $v->tags) : '—', ''],
                ['Created', $v->created_at->format('M j, Y · g:i A'), ''],
                ['Last modified', $v->updated_at->format('M j, Y · g:i A'), ''],
                ['Created via', ($v->source ?? 'app') === 'api' ? 'API call' : 'App interface', ''],
            ] as [$label, $value, $extra])
            <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate {{ $extra }}" title="{{ $value }}">{{ $value }}</p>
            </div>
            @endforeach
        </div>

        {{-- Nodes --}}
        <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mt-5 mb-2">Nodes ({{ $v->nodes->count() }})</p>
        <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider text-gray-400 bg-gray-50 dark:bg-white/[0.03]">
                        <th class="px-3.5 py-2 font-bold">Label</th>
                        <th class="px-3.5 py-2 font-bold">Type</th>
                        <th class="px-3.5 py-2 font-bold">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($v->nodes as $n)
                    <tr class="border-t border-gray-50 dark:border-white/[0.04] align-top">
                        <td class="px-3.5 py-2 text-xs font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ $n->label }}</td>
                        <td class="px-3.5 py-2"><span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">{{ $n->type }}</span></td>
                        <td class="px-3.5 py-2 text-xs text-gray-500 dark:text-gray-400 break-all">
                            @if ($n->type === 'collection')
                                {{ $n->linkedCollection()?->name ?? $n->value }} <span class="text-gray-300">(collection)</span>
                            @else
                                {{ Str::limit((string) $n->value, 90) ?: '—' }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Attachments --}}
        <div class="grid sm:grid-cols-2 gap-4 mt-5">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Attached to pages ({{ $v->pages->count() }})</p>
                @forelse ($v->pages as $p)
                    <p class="text-xs text-gray-600 dark:text-gray-300 py-0.5">{{ $p->name }} <span class="font-mono text-gray-400">{{ $p->url }}</span></p>
                @empty
                    <p class="text-xs text-gray-400">Standalone — not on any page.</p>
                @endforelse
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Linked collections</p>
                @forelse ($v->collections() as $col)
                    <p class="text-xs text-gray-600 dark:text-gray-300 py-0.5">{{ $col->name }}</p>
                @empty
                    <p class="text-xs text-gray-400">None.</p>
                @endforelse
            </div>

            @if ($v->collection_id && ($vCol = \App\Models\Collection::where('site_id', $site->id)->withCount('items')->find($v->collection_id)))
            <div class="sm:col-span-2">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">📦 Data source — {{ $vCol->name }} ({{ $vCol->items_count }})</p>
                    <a href="{{ route('collections.show', [$site->name, $vCol->id]) }}"
                       class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Manage entries →</a>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5">
                    @foreach ($vCol->items()->limit(12)->get() as $ci)
                        @php
                            $d = (array) ($ci->data ?? []);
                            $ciImg = (string) ($d['img'] ?? $d['image'] ?? $d['photo'] ?? '');
                            $ciImg = $ciImg !== '' ? \App\Models\Media::resolveRef($site->id, '@media/'.basename($ciImg)) : '';
                        @endphp
                        <a href="{{ route('collections.entries.show', [$site->name, $vCol->id, $ci->id]) }}"
                           class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-1.5 hover:border-indigo-300 dark:hover:border-indigo-500/40 transition-colors">
                            @if ($ciImg)<img src="{{ $ciImg }}" alt="" class="w-full aspect-square rounded-md object-cover mb-1" onerror="this.style.display='none'">@endif
                            <span class="block text-[10px] font-semibold text-gray-600 dark:text-gray-300 truncate">{{ $d['name'] ?? $d['title'] ?? '…' }}</span>
                        </a>
                    @endforeach
                </div>
                <p class="mt-1.5 text-[10px] text-gray-400">These entries feed this component on the site — add, remove or edit them in the collection.</p>
            </div>
            @endif
        </div>

        @if ($canManage)
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <a href="{{ $this->pageUrl() }}" wire:navigate class="px-4 py-2 rounded-xl text-xs font-medium text-gray-500 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08]">Close</a>
                <a href="{{ $this->pageUrl($v->id, true) }}" wire:navigate
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Edit this component</a>
            </div>
        </x-slot:footer>
        @endif
    </x-page-panel>
    @endif

    {{-- ═══ EDITOR ═══ --}}
    @if ($editingId !== null)
    <x-page-panel close="close" :back-label="$editingId ? 'Back to the component' : 'Back to components'" icon="🧩" :title="$editingId ? 'Edit component' : 'New component'"
                subtitle="Nodes are the content fields; attach to pages or link a collection node." max-width="max-w-3xl">
            <form wire:submit="save" class="space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Name</label>
                        <input wire:model="cName" type="text" required placeholder="e.g. Hero banner"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        @error('cName')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Description <span class="font-normal text-gray-400">(optional)</span></label>
                        <input wire:model="cDescription" type="text" placeholder="What is this component for?"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Tags <span class="font-normal text-gray-400">— comma separated; used to filter the page picker</span></label>
                        <input wire:model="cTags" type="text" placeholder="e.g. hero, marketing, footer"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                </div>

                @include('partials.visibility-fields', ['visSet' => $visFrom || $visUntil || $visDays !== [] || $visTimeFrom || $visRequiresContent || $visPromo])

                {{-- ── Nodes ── --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Nodes — the component's content fields</p>
                    <div class="space-y-2">
                        @foreach ($nodes as $i => $n)
                        <div class="flex flex-wrap items-start gap-2 rounded-xl bg-gray-50 dark:bg-white/[0.04] p-2.5" wire:key="node-{{ $i }}">
                            <input wire:model="nodes.{{ $i }}.label" type="text" placeholder="Label (e.g. Heading)"
                                   class="flex-1 min-w-[130px] px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            <select wire:model.live="nodes.{{ $i }}.type"
                                    class="pr-7 pl-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                @foreach ($this->nodeTypes as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                            </select>
                            @if (($n['type'] ?? 'text') === 'collection')
                                <select wire:model="nodes.{{ $i }}.value"
                                        class="flex-1 min-w-[140px] pr-7 pl-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                    <option value="">— link a collection —</option>
                                    @foreach ($this->siteCollections as $col)<option value="{{ $col->id }}">{{ $col->name }}</option>@endforeach
                                </select>
                            @elseif (($n['type'] ?? 'text') === 'boolean')
                                <select wire:model="nodes.{{ $i }}.value"
                                        class="px-3 py-2 pr-7 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                    <option value="1">True</option><option value="0">False</option>
                                </select>
                            @elseif (($n['type'] ?? 'text') === 'image')
                                <x-asset-picker model="nodes.{{ $i }}.value" :site="$site" type="image" placeholder="Image URL or pick from assets" />
                            @elseif (($n['type'] ?? 'text') === 'url')
                                <x-asset-picker model="nodes.{{ $i }}.value" :site="$site" type="" placeholder="URL or pick any asset" />
                            @else
                                <input wire:model="nodes.{{ $i }}.value" type="text" placeholder="Value"
                                       class="flex-1 min-w-[140px] px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            @endif
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" wire:click="moveNode({{ $i }}, -1)" title="Move up" class="w-7 h-7 rounded-lg text-gray-400 hover:text-indigo-500 hover:bg-white dark:hover:bg-white/[0.06]">↑</button>
                                <button type="button" wire:click="moveNode({{ $i }}, 1)" title="Move down" class="w-7 h-7 rounded-lg text-gray-400 hover:text-indigo-500 hover:bg-white dark:hover:bg-white/[0.06]">↓</button>
                                <button type="button" wire:click="removeNode({{ $i }})" title="Remove" class="w-7 h-7 rounded-lg text-gray-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10">✕</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addNode"
                            class="mt-2 w-full py-2 rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-400 hover:text-indigo-500 hover:border-indigo-300 transition-colors">+ Add node</button>
                </div>

                {{-- ── Belongs to collection ── --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Belongs to collection <span class="font-normal normal-case tracking-normal text-gray-400">— optional; groups this with sibling components</span></p>
                    <select wire:model="collectionId"
                            class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        <option value="">— None (standalone) —</option>
                        @foreach ($this->siteCollections as $col)<option value="{{ $col->id }}">{{ $col->name }}</option>@endforeach
                    </select>
                </div>

                {{-- ── Attach to pages ── --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Attached to pages <span class="font-normal normal-case tracking-normal text-gray-400">— optional; a component can stand alone</span></p>
                    @if ($this->sitePages->isEmpty())
                        <p class="text-xs text-gray-400">This site has no pages yet.</p>
                    @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->sitePages as $p)
                        <label class="flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer select-none text-xs font-semibold transition-colors
                                      {{ in_array((string) $p->id, array_map('strval', $pageIds), true) ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300' : 'border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400' }}">
                            <input type="checkbox" wire:model.live="pageIds" value="{{ $p->id }}" class="hidden">
                            {{ $p->name }} <span class="font-mono font-normal opacity-60">{{ $p->url }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" wire:click="close" class="px-4 py-2 rounded-xl text-sm font-medium text-gray-500 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08]">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save component</button>
                </div>
            </form>
    </x-page-panel>
    @endif
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · most used · recently edited · related ══ --}}
    <x-slot:quick>
        @php
            $split = [
                ['On pages', $stats['used'], '#10b981', 'used'],
                ['Inactive', $stats['inactive'], '#f59e0b', 'inactive'],
                ['Unused', $stats['unused'], '#9ca3af', 'unused'],
            ];
        @endphp
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Components summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $total }}</b> {{ Str::plural('component', $total) }} ·
                <b class="text-gray-900 dark:text-white">{{ number_format($stats['fields']) }}</b> {{ Str::plural('field', $stats['fields']) }} ·
                <b class="text-gray-900 dark:text-white">{{ $stats['placements'] }}</b> live {{ Str::plural('placement', $stats['placements']) }}
            </p>
            @if ($total)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach ($split as [$label, $n, $color])
                        @if ($n)<span style="width:{{ round($n / $total * 100, 2) }}%;background:{{ $color }}" title="{{ $label }} · {{ $n }}"></span>@endif
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach ($split as [$label, $n, $color, $key])
                        <button type="button" wire:click="setFilter('{{ $key }}')" class="w-full flex items-center gap-2 text-[12.5px] hover:underline">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $color }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ $n }}</span>
                        </button>
                    @endforeach
                </div>
                @if ($stats['byTag'])
                    <p class="mt-4 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">Top tags</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($stats['byTag'] as $tag => $n)
                            <button type="button" wire:click="setTag(@js((string) $tag))" class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 hover:underline">#{{ $tag }} <span class="opacity-60">{{ $n }}</span></button>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-[12.5px] text-gray-500 dark:text-gray-400">Nothing built yet.</p>
            @endif
        </div>

        @if ($stats['emptyList']->isNotEmpty() || $stats['noFields']->isNotEmpty() || $stats['inactive'] || $stats['unused'])
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($stats['emptyList']->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $stats['empty'] }} empty {{ Str::plural('field', $stats['empty']) }} to fill in</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 mb-1.5">These show blank on the site.</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($stats['emptyList'] as $c)
                                <a href="{{ $this->pageUrl($c->id, $canManage) }}" wire:navigate class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/80 dark:bg-white/[0.08] text-rose-800 dark:text-rose-200 hover:underline">{{ $c->name }} · {{ $c->empty_nodes_count }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($stats['noFields']->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $stats['noFields']->count() }} without any fields</p>
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @foreach ($stats['noFields']->take(5) as $c)
                                <a href="{{ $this->pageUrl($c->id, $canManage) }}" wire:navigate class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/80 dark:bg-white/[0.08] text-amber-800 dark:text-amber-200 hover:underline">{{ $c->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($stats['inactive'])
                    <button type="button" wire:click="setFilter('inactive')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10 hover:ring-2 hover:ring-amber-200 dark:hover:ring-amber-500/30">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $stats['inactive'] }} inactive after a template switch</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70">Only on another template's pages — they return if you switch back. Show them →</p>
                    </button>
                @endif
                @if ($stats['unused'])
                    <button type="button" wire:click="setFilter('unused')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $stats['unused'] }} on no page</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Place them on a page or tidy them up. Show them →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if ($stats['mostUsed']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Most used</p>
            @php $max = max(1, $stats['mostUsed']->max('live_pages_count')); @endphp
            <div class="space-y-2.5">
                @foreach ($stats['mostUsed'] as $c)
                    <a href="{{ $this->pageUrl($c->id) }}" wire:navigate class="block group">
                        <span class="flex items-center justify-between text-[12.5px]">
                            <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline">{{ $c->name }}</span>
                            <span class="font-bold text-gray-900 dark:text-white tabular-nums">{{ $c->live_pages_count }} {{ Str::plural('page', $c->live_pages_count) }}</span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full" style="width:{{ round($c->live_pages_count / $max * 100) }}%;background:var(--primary)"></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        @if ($stats['recentlyEdited']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Recently edited</p>
            <div class="space-y-2">
                @foreach ($stats['recentlyEdited'] as $c)
                    <a href="{{ $this->pageUrl($c->id) }}" wire:navigate class="flex items-center gap-2.5 group">
                        <span class="w-7 h-7 rounded-lg grid place-items-center shrink-0 bg-indigo-100 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['block'] }}"/></svg>
                        </span>
                        <span class="min-w-0 flex-1 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate group-hover:underline">{{ $c->name }}</span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $c->last_edited->diffForHumans(null, true) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Edit site', 'Place blocks visually', url($site->name.'/connect'), false],
                    ['Pages', 'Where components sit', route('pages', $site->name), true],
                    ['Collections', 'Data behind blocks', route('collections', $site->name), true],
                    ['Assets', 'Images for fields', route('media', $site->name), true],
                    ['Forms', 'Submissions & fields', route('site.forms', $site->name), true],
                    ['Designs', 'Templates & themes', route('site.designs', $site->name), true],
                ] as [$label, $hint, $href, $navigate])
                    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
