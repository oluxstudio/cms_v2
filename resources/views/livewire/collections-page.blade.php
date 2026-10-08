@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $typeClassFor = fn ($t) => match($t) {
        'grid'  => 'bg-violet-100 dark:bg-violet-500/10 text-violet-700 dark:text-violet-300',
        'table' => 'bg-orange-100 dark:bg-orange-500/10 text-orange-700 dark:text-orange-300',
        default => 'bg-sky-100 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300',
    };
    $typeColor = ['grid' => '#8b5cf6', 'table' => '#f97316', 'list' => '#0ea5e9'];
    $typeIcon = [
        'grid'  => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
        'table' => 'M3 10h18M3 14h18M10 4v16M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z',
        'list'  => 'M4 6h16M4 12h16M4 18h10',
    ];
    $filters = [
        'all' => ['All', $total],
        'linked' => ['On blocks / pages', $stats['linked']],
        'unlinked' => ['Not linked', $stats['unlinked']],
        'empty' => ['Empty', $stats['empty']],
        'pending' => ['Awaiting review', $stats['pendingList']->count()],
    ];
    $usedIn = function ($c) {
        $bits = [];
        if ($c->blocks_count) $bits[] = $c->blocks_count.' '.Str::plural('block', $c->blocks_count);
        if ($c->pages_count) $bits[] = $c->pages_count.' '.Str::plural('page', $c->pages_count);
        return $bits ? implode(' · ', $bits) : null;
    };
@endphp
<x-tri-layout title="Collections" subtitle="Structured data sources — the rows behind grids, lists and galleries." :site-name="$site->name"
    :labels="['📊 Overview', '🗂 Collections', '⚡ Quick access']">

    {{-- ── LEFT rail: the data at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$total" label="Collections" :sub="$recent ? $recent.' added this week' : $types.' '.Str::plural('type', $types)"
                icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        <x-tile accent="lime" :value="number_format($stats['entries'])" label="Live entries" sub="published, all collections"
                icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="{{ $stats['pending'] ? 'rose' : 'sky' }}" :value="$stats['pending']" label="Awaiting review" :sub="$stats['pending'] ? 'visitor submissions' : 'nothing to approve'"
                icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="lavender" :value="$stats['linked']" label="On the site" :sub="'linked to blocks or pages · of '.$total"
                icon="M13.8 10.2a4 4 0 00-5.6 0l-4 4a4 4 0 105.6 5.6l1.1-1.1m-.7-4.9a4 4 0 005.6 0l4-4a4 4 0 00-5.6-5.6l-1.1 1.1" />
        <x-tile accent="cocoa" :value="$stats['empty']" label="Empty" :sub="$stats['empty'] ? 'no entries yet' : 'every collection has entries'"
                icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.6a1 1 0 00-.7.3l-2.4 2.4a1 1 0 01-.7.3h-3.2a1 1 0 01-.7-.3l-2.4-2.4a1 1 0 00-.7-.3H4" />
        <x-tile accent="sky" :value="$stats['autoPublish']" label="Auto-publishing" :sub="$stats['autoPublish'] ? 'visitor posts go live unreviewed' : 'visitor posts wait for review'"
                icon="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    @unless ($showModal || $viewing)
    {{-- ── Toolbar: search · filter · sort · layout · new ── --}}
    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search by name, type or description…" class="w-full" style="padding-left:2.25rem" />
            </div>
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                <option value="updated">Recently active</option>
                <option value="entries">Most entries</option>
                <option value="name">Name A–Z</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <a href="{{ route('collections.create', $site->name) }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New collection
            </a>
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
    </div>

    @if ($collections->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </span>
            @if ($total === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No collections yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">A collection holds repeating content — team members, events, FAQs, products — that blocks show as grids and lists.</p>
                <a href="{{ route('collections.create', $site->name) }}" wire:navigate class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Create the first collection</a>
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or filter.</p>
                <button type="button" x-on:click="$wire.set('search', ''); $wire.setFilter('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all collections</button>
            @endif
        </div>
    @elseif ($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($collections as $collection)
                @php $show = route('collections.show', [$site->name, $collection->id]); $used = $usedIn($collection); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl hover:shadow-md transition-shadow overflow-hidden" wire:key="col-{{ $collection->id }}">
                    <a href="{{ $show }}" wire:navigate class="p-5 flex-1 block">
                        <div class="flex items-start gap-3">
                            <span class="w-11 h-11 rounded-xl grid place-items-center shrink-0 {{ $typeClassFor($collection->type) }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcon[$collection->type] ?? $typeIcon['list'] }}"/></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $collection->name }}</span>
                                <span class="block text-[12px] text-gray-400 mt-0.5">{{ ucfirst($collection->type) }} · {{ $collection->fields_count }} {{ Str::plural('field', $collection->fields_count) }} · active {{ $collection->last_activity->diffForHumans() }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-2xl font-extrabold tabular-nums leading-none {{ $collection->items_count ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $collection->items_count }}</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ Str::plural('entry', $collection->items_count) }}</span>
                            </span>
                        </div>
                        <p class="mt-3 text-[13px] text-gray-500 dark:text-gray-400 line-clamp-2 min-h-[2.5em]">{{ $collection->description ?: 'No description yet.' }}</p>
                        <span class="mt-3 flex flex-wrap gap-1.5">
                            @if ($used)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">● On {{ $used }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400" title="No block or page links it — a template may still read it by name">Not linked</span>
                            @endif
                            @if ($collection->pending_count)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-500 text-white">{{ $collection->pending_count }} to review</span>
                            @endif
                            @if (! $collection->items_count)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300">Empty</span>
                            @endif
                            @if ($collection->allow_submit && $collection->auto_publish)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300" title="Visitor submissions publish without review">Auto-publishes submissions</span>
                            @endif
                        </span>
                    </a>
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        <a href="{{ route('collections.entries.new', [$site->name, $collection->id]) }}" wire:navigate
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">＋ Entry</a>
                        <a href="{{ $show }}" wire:navigate class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Open</a>
                        <span class="ml-auto flex items-center gap-0.5">
                            <button wire:click="toggleInsights('{{ $collection->id }}')" title="Engagement insights"
                                    class="p-1.5 rounded-lg {{ $insightsId === $collection->id ? 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10' : 'text-gray-400' }} hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </button>
                            <a href="{{ route('collections.settings', [$site->name, $collection->id]) }}" wire:navigate title="Settings"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3c.4-1.8 3-1.8 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.5c1.8.4 1.8 3 0 3.4a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-3 1.8-3.4 0a1.7 1.7 0 00-2.5-1c-1.6.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1.1-2.6c-1.8-.4-1.8-3 0-3.4a1.7 1.7 0 001.1-2.5c-.9-1.6.8-3.3 2.4-2.4 1 .6 2.3.1 2.5-1.1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </a>
                            <button wire:click="deleteCollection('{{ $collection->id }}')" data-confirm="Delete “{{ $collection->name }}” and all {{ $collection->items_count }} {{ Str::plural('entry', $collection->items_count) }}?" title="Delete"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ── List & Compact (table) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Collection</th>
                            <th class="px-4 py-3 text-right">Entries</th>
                            @unless ($compact)
                                <th class="px-4 py-3">Used on</th>
                                <th class="px-4 py-3">Active</th>
                            @endunless
                            <th class="w-28 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($collections as $collection)
                            @php $used = $usedIn($collection); @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors group" wire:key="row-{{ $collection->id }}">
                                <td class="{{ $pad }}">
                                    <a href="{{ route('collections.show', [$site->name, $collection->id]) }}" wire:navigate class="flex items-center gap-2.5 min-w-0">
                                        <span class="w-8 h-8 rounded-lg grid place-items-center shrink-0 {{ $typeClassFor($collection->type) }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcon[$collection->type] ?? $typeIcon['list'] }}"/></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-gray-900 dark:text-white truncate hover:underline">{{ $collection->name }}</span>
                                            @unless ($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[16rem]">{{ ucfirst($collection->type) }} · {{ $collection->fields_count }} {{ Str::plural('field', $collection->fields_count) }}{{ $collection->description ? ' · '.$collection->description : '' }}</span>@endunless
                                        </span>
                                        @if ($collection->pending_count)<span class="shrink-0 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white">{{ $collection->pending_count }} new</span>@endif
                                    </a>
                                </td>
                                <td class="{{ $pad }} text-right font-bold tabular-nums {{ $collection->items_count ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $collection->items_count }}</td>
                                @unless ($compact)
                                    <td class="{{ $pad }} text-[12px] {{ $used ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-400' }}">{{ $used ?? 'Not linked' }}</td>
                                    <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ $collection->last_activity->diffForHumans() }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-1 justify-end">
                                        <a href="{{ route('collections.entries.new', [$site->name, $collection->id]) }}" wire:navigate title="Add an entry"
                                           class="px-2 py-1 rounded-lg text-[11px] font-bold opacity-0 group-hover:opacity-100 transition-opacity" style="background:var(--primary);color:var(--on-primary)">＋</a>
                                        <a href="{{ route('collections.settings', [$site->name, $collection->id]) }}" wire:navigate title="Settings"
                                           class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <button wire:click="deleteCollection('{{ $collection->id }}')" data-confirm="Delete “{{ $collection->name }}” and all {{ $collection->items_count }} {{ Str::plural('entry', $collection->items_count) }}?" title="Delete"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── Engagement insights: which media gets the most attention ── --}}
    @if($insightsId && ($ins = $this->mediaInsights))
    <div class="mt-4 bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm p-5">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">📈 {{ $ins['name'] }} — engagement, last 30 days</h2>
            <button wire:click="toggleInsights('{{ $insightsId }}')" class="text-xs font-semibold text-gray-400 hover:text-gray-600">✕ Close</button>
        </div>
        @if($ins['views'] === 0 && $ins['plays'] === 0)
            <p class="text-sm text-gray-400 py-6 text-center">No visitor activity yet — views and plays appear here as people open and play this collection's media on your website.</p>
        @else
        <p class="text-xs text-gray-400 mb-4">{{ number_format($ins['views']) }} views · {{ number_format($ins['plays']) }} plays · {{ $ins['rate'] }}% of viewers pressed play</p>
        <div class="grid sm:grid-cols-2 gap-6">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-500 dark:text-gray-400 mb-2">Most viewed</p>
                @if(count($ins['top_viewed'])) <x-analytics.bar-list :items="$ins['top_viewed']" />
                @else <p class="text-sm text-gray-400 py-3">No views yet.</p> @endif
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-500 dark:text-gray-400 mb-2">Most played</p>
                @if(count($ins['top_played'])) <x-analytics.bar-list :items="$ins['top_played']" />
                @else <p class="text-sm text-gray-400 py-3">No plays yet.</p> @endif
            </div>
        </div>
        @endif
    </div>
    @endif

    @endunless

    {{-- ── New collection / a collection's settings: their own page ── --}}
    @if($showModal)
    <x-page-panel close="cancelSettings" :back-label="$editingId ? 'Back to the collection' : 'Back to collections'"
        :title="$editingId ? 'Collection settings' : 'New collection'" :subtitle="$editingId ? $name : 'A structured data source for grids, lists and galleries'">
        <div class="space-y-5">
            <div class="space-y-4">
                <div>
                    <x-field.text label="Collection Name" model="name" placeholder="e.g. Blog Posts" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-field.select label="Display Type" model="type" :empty="null"
                                    :options="['list' => 'List', 'grid' => 'Grid', 'table' => 'Table']" />
                    @error('type') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="olx-adv-lead">More options</div>
                <x-panel-group label="Description & visitor submissions" hint="what it's for, public submissions">
                <div>
                    <x-field.textarea label="Description (optional)" model="description" rows="3"
                                      placeholder="What is this collection for?" class="resize-none" />
                    @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-2">
                    <x-field.check text="Allow visitor submissions" wire:model.live="allowSubmit"
                                   hint="Visitors on the client site can add items through the public API." />
                    @if ($allowSubmit)
                        <x-field.check model="autoPublish" text="Auto-publish submissions"
                                       hint="Off (recommended): new submissions are held as pending until you approve them here." />
                    @endif
                </div>

                @include('partials.visibility-fields', ['visSet' => $visFrom || $visUntil || $visDays !== [] || $visTimeFrom || $visRequiresContent || $visPromo])

                {{-- Attach the collection to pages --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Show on pages <span class="font-normal normal-case tracking-normal text-gray-400">— optional; can be placed on many</span></p>
                    @if ($sitePages->isEmpty())
                        <p class="text-xs text-gray-400">This site has no pages yet.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($sitePages as $p)
                                <label class="flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer select-none text-xs font-semibold transition-colors
                                              {{ in_array((string) $p->id, array_map('strval', $pageIds), true) ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300' : 'border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400' }}">
                                    <input type="checkbox" wire:model.live="pageIds" value="{{ $p->id }}" class="hidden">
                                    {{ $p->name }} <span class="font-mono font-normal opacity-60">{{ $p->url }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                </x-panel-group>
            </div>
            <div class="flex gap-3 pt-1">
                <button wire:click="cancelSettings"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08]
                               text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
                    Cancel
                </button>
                <button wire:click="save"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm">
                    {{ $editingId ? 'Save Changes' : 'Create Collection' }}
                </button>
            </div>
        </div>
    </x-page-panel>
    @endif

    {{-- ── Entries (older ?open links now go to the collection's own page) ── --}}
    @if($viewing)
    @php $cols = collect($viewing->fields ?? [])->take(6); @endphp
    <x-page-panel close="closeEntries" back-label="Back to collections">
        <div class="-mx-6 -my-5">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-white/[0.05]">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $viewing->name }} — entries <a href="{{ route('collections.show', [$site->name, $viewing->id]) }}" wire:navigate class="ml-1 text-xs font-semibold" style="color:var(--primary)">Open page ↗</a></h2>
                    <p class="text-xs text-gray-400">{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if(!empty($viewing->fields))
                    <button wire:click="openItem" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--primary)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add entry
                    </button>
                    @endif
                </div>
            </div>

            {{-- ── Grouped components (the collection's members) ── --}}
            <div class="p-5 border-b border-gray-100 dark:border-white/[0.05]">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <p class="text-xs font-bold uppercase tracking-[.12em] text-gray-400">Components <span class="text-gray-300 dark:text-gray-600">({{ $members->count() }})</span></p>
                    <div class="flex items-center gap-2" x-data="{ pick: '' }">
                        <input wire:model.live.debounce.300ms="memberSearch" type="text" placeholder="Search components…"
                               class="px-2.5 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 placeholder-gray-400 w-40">
                        <select x-model="pick" class="px-2.5 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                            <option value="">Add a component…</option>
                            @foreach($available as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        <button x-on:click="if(pick){ $wire.addComponent(pick); pick='' }"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--primary)">Add</button>
                    </div>
                </div>
                @if($available->isEmpty() && $memberSearch !== '')
                    <p class="text-[11px] text-gray-400 mb-2">No standalone components match "{{ $memberSearch }}".</p>
                @endif

                @if($members->isEmpty())
                    <p class="text-xs text-gray-400">No components yet — add existing ones above, or set a component's collection on the Components page.</p>
                @else
                    <div class="space-y-1.5">
                        @foreach($members as $i => $m)
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-100 dark:border-white/[0.06]">
                            <div class="flex flex-col leading-none">
                                <button wire:click="moveComponent('{{ $m->id }}', -1)" @if($i === 0) disabled @endif class="text-gray-400 hover:text-gray-700 disabled:opacity-30">▲</button>
                                <button wire:click="moveComponent('{{ $m->id }}', 1)" @if($i === $members->count() - 1) disabled @endif class="text-gray-400 hover:text-gray-700 disabled:opacity-30">▼</button>
                            </div>
                            <span class="flex-1 text-sm text-gray-800 dark:text-gray-200 truncate">{{ $m->name }}
                                <span class="text-xs text-gray-400">· {{ $m->nodes_count }} {{ Str::plural('node', $m->nodes_count) }}</span>
                            </span>
                            <button wire:click="removeComponent('{{ $m->id }}')" class="text-xs font-semibold text-rose-500 hover:text-rose-600">Remove</button>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @include('livewire.partials.collection-item-form', ['viewing' => $viewing])
            <div class="overflow-auto p-2">
                @if($cols->isEmpty())
                    <p class="p-6 text-sm text-gray-400 text-center">This collection has no fields defined.</p>
                @elseif($entries->isEmpty())
                    <p class="p-6 text-sm text-gray-400 text-center">No entries yet.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                                @foreach($cols as $f)
                                    <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-3 py-2">{{ $f['label'] ?? $f['key'] }}</th>
                                @endforeach
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach($entries as $item)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    @foreach($cols as $f)
                                        @php $v = data_get($item->data, $f['key']); @endphp
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200 align-top max-w-[200px] truncate">
                                            @if(is_array($v))
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                                                      title="{{ json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
                                                    {{ count($v) }} {{ Str::plural('item', count($v)) }}
                                                </span>
                                            @else
                                                {{ ($v === null || $v === '') ? '—' : $v }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <button wire:click="openItem('{{ $item->id }}')"
                                                class="p-1 rounded text-gray-400 hover:text-indigo-500" title="Edit entry">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="deleteItem('{{ $item->id }}')" data-confirm="Delete this entry?"
                                                class="p-1 rounded text-gray-400 hover:text-red-500" title="Delete entry">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </x-page-panel>
    @endif

    {{-- ── Delete Modal ── --}}


    {{-- Asset library dialog for the item editor's photo fields --}}
    <livewire:media-picker :site-id="$site->id" />
</div>
    {{-- ══ RIGHT rail: summary · needs attention · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Data summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $total }}</b> {{ Str::plural('collection', $total) }} ·
                <b class="text-gray-900 dark:text-white">{{ number_format($stats['entries']) }}</b> live {{ Str::plural('entry', $stats['entries']) }}
            </p>
            @if ($total)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach ($stats['byType'] as $t => $n)
                        <span style="width:{{ round($n / $total * 100, 2) }}%;background:{{ $typeColor[$t] ?? '#94a3b8' }}" title="{{ ucfirst($t) }} · {{ $n }}"></span>
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach ($stats['byType'] as $t => $n)
                        <div class="flex items-center gap-2 text-[12.5px]">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $typeColor[$t] ?? '#94a3b8' }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ ucfirst($t) }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ $n }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($stats['pendingList']->isNotEmpty() || $stats['emptyList']->isNotEmpty() || $stats['unlinked'])
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @foreach ($stats['pendingList']->take(3) as $c)
                    <a href="{{ route('collections.show', [$site->name, $c->id]) }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $c->pending_count }} {{ Str::plural('submission', $c->pending_count) }} to review</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">in {{ $c->name }} — approve to publish →</p>
                    </a>
                @endforeach
                @if ($stats['emptyList']->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $stats['emptyList']->count() }} empty {{ Str::plural('collection', $stats['emptyList']->count()) }}</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70 mb-1.5">Blocks reading these show nothing yet.</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($stats['emptyList']->take(6) as $c)
                                <a href="{{ route('collections.entries.new', [$site->name, $c->id]) }}" wire:navigate class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/80 dark:bg-white/[0.08] text-amber-800 dark:text-amber-200 hover:underline">＋ {{ $c->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($stats['unlinked'])
                    <button type="button" wire:click="setFilter('unlinked')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $stats['unlinked'] }} not linked to a block or page</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">A template may still read them by name — check before deleting. Show them →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if ($stats['largest']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Largest collections</p>
            @php $max = max(1, $stats['largest']->max('items_count')); @endphp
            <div class="space-y-2.5">
                @foreach ($stats['largest'] as $c)
                    <a href="{{ route('collections.show', [$site->name, $c->id]) }}" wire:navigate class="block group">
                        <span class="flex items-center justify-between text-[12.5px]">
                            <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline">{{ $c->name }}</span>
                            <span class="font-bold text-gray-900 dark:text-white tabular-nums">{{ $c->items_count }}</span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full" style="width:{{ round($c->items_count / $max * 100) }}%;background:{{ $typeColor[$c->type] ?? 'var(--primary)' }}"></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        @if ($stats['recentlyEdited']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Recently active</p>
            <div class="space-y-2">
                @foreach ($stats['recentlyEdited'] as $c)
                    <a href="{{ route('collections.show', [$site->name, $c->id]) }}" wire:navigate class="flex items-center gap-2.5 group">
                        <span class="w-7 h-7 rounded-lg grid place-items-center shrink-0 {{ $typeClassFor($c->type) }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcon[$c->type] ?? $typeIcon['list'] }}"/></svg>
                        </span>
                        <span class="min-w-0 flex-1 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate group-hover:underline">{{ $c->name }}</span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $c->last_activity->diffForHumans(null, true) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Edit site', 'Show lists on blocks', url($site->name.'/connect')],
                    ['Components', 'Blocks & their data', route('site.components', $site->name)],
                    ['Forms', 'Submissions & fields', route('site.forms', $site->name)],
                    ['Assets', 'Images for entries', route('media', $site->name)],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
