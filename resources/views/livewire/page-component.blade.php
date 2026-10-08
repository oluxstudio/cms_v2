@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $iconBtn = 'p-1.5 rounded-lg text-gray-400 transition-colors';
    $badge = 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold';
    $editUrl = url($site->name.'/connect');
    $previewFor = fn ($p) => url('preview/'.$site->name.'/'.ltrim((string) $p->url, '/')).'?preview=1';
    $detailFor = fn ($p) => url($site->name.'/pages/'.$p->id.'/details');
    $layoutName = fn ($p) => $p->blockLayout?->name ?? 'Blank';
    $filters = [
        'all' => ['All', $total],
        'live' => ['Live', $stats['live']],
        'hidden' => ['Hidden', $stats['hidden']],
        'inactive' => ['Inactive template pages', $stats['inactive']],
        'attention' => ['Needs attention', $stats['attention']],
    ];
    $statusColor = ['live' => '#10b981', 'hidden' => '#94a3b8', 'inactive' => '#f59e0b', 'system' => '#6366f1'];
    $icons = [
        'page' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z',
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'eye' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
        'puzzle' => 'M11 4a1 1 0 112 0v1h3a1 1 0 011 1v3h1a1 1 0 110 2h-1v3a1 1 0 01-1 1h-3v1a1 1 0 11-2 0v-1H8a1 1 0 01-1-1v-3H6a1 1 0 110-2h1V6a1 1 0 011-1h3V4z',
        'gear' => 'M10.3 4.3c.4-1.8 3-1.8 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.5c1.8.4 1.8 3 0 3.4a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-3 1.8-3.4 0a1.7 1.7 0 00-2.5-1c-1.6.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1.1-2.6c-1.8-.4-1.8-3 0-3.4a1.7 1.7 0 001.1-2.5c-.9-1.6.8-3.3 2.4-2.4 1 .6 2.3.1 2.5-1.1z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'pencil' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'trash' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    ];
    $svg = fn ($d, $cls = 'w-4 h-4') => '<svg class="'.$cls.'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'"/></svg>';
    $deleteMsg = fn ($p) => 'Delete “'.$p->name.'” and all its blocks?';
@endphp
<x-tri-layout title="Pages" subtitle="Every page of your site — what's on it, what's live and what needs a look." :site-name="$site->name"
    :labels="['📊 Overview', '📄 Pages', '⚡ Summary']">

    {{-- ── LEFT rail: the pages at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$total" :label="Str::plural('Page', $total)"
                :sub="$stats['live'].' live · '.$stats['hidden'].' hidden'.($stats['thisWeek'] ? ' · '.$stats['thisWeek'].' new this week' : '')"
                :icon="$icons['page']" />
        <x-tile accent="lime" :value="$stats['inNav']" label="In the navigation" sub="pages your visitors can reach"
                icon="M4 6h16M4 12h16M4 18h7" />
        <x-tile accent="lavender" :value="$stats['sections']" label="Sections" :sub="$stats['avgSections'].' per page on average'"
                :icon="$icons['puzzle']" />
        <x-tile accent="{{ $stats['noSeo'] ? 'rose' : 'sky' }}" :value="$stats['noSeo']" label="Missing SEO"
                :sub="$stats['noSeo'] ? 'no search description yet' : 'every page is described'"
                icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        <x-tile accent="{{ $stats['empty'] ? 'rose' : 'sky' }}" :value="$stats['empty']" label="Empty pages"
                :sub="$stats['empty'] ? 'no sections — visitors see a blank page' : 'every page has content'"
                icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.6a1 1 0 00-.7.3l-2.4 2.4a1 1 0 01-.7.3h-3.2a1 1 0 01-.7-.3l-2.4-2.4a1 1 0 00-.7-.3H4" />
        <x-tile accent="cocoa" :value="$stats['inactive']" label="Inactive template pages"
                :sub="$stats['inactive'] ? 'parked by a template switch' : 'nothing parked'"
                icon="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    {{-- ── Toolbar: search · filter · sort · layout · new ── --}}
    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.250ms="search" placeholder="Search by name, URL or keyword…" class="w-full" style="padding-left:2.25rem" />
            </div>
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                <option value="menu">Menu order</option>
                <option value="name">Name A–Z</option>
                <option value="updated">Recently edited</option>
                <option value="sections">Most sections</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <button type="button" wire:click="openCreate"
                    class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New page
            </button>
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

    @if ($pages->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06] text-gray-400">
                {!! $svg($icons['page'], 'w-7 h-7') !!}
            </span>
            @if ($total === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No pages yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Pages are what visitors browse — a home page, about, contact. Each one is built from sections.</p>
                <button type="button" wire:click="openCreate" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Create the first page</button>
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search or filter.</p>
                <button type="button" wire:click="resetFilters" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all pages</button>
            @endif
        </div>
    @elseif ($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($pages as $page)
                @php $names = $page->activeComponents->pluck('name'); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl hover:shadow-md transition-shadow overflow-hidden {{ $page->is_inactive ? 'opacity-80' : '' }}" wire:key="page-{{ $page->id }}">
                    <a href="{{ $detailFor($page) }}" class="p-5 flex-1 block">
                        <div class="flex items-start gap-3">
                            <span class="w-11 h-11 rounded-xl grid place-items-center shrink-0 {{ $page->is_home ? 'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-indigo-100 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300' }}">
                                {!! $svg($page->is_home ? $icons['home'] : $icons['page'], 'w-5 h-5') !!}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $page->name }}</span>
                                <span class="block font-mono text-[12px] text-gray-400 mt-0.5 truncate">{{ $page->url }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-2xl font-extrabold tabular-nums leading-none {{ $page->sections_count ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $page->sections_count }}</span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ Str::plural('section', $page->sections_count) }}</span>
                            </span>
                        </div>

                        <span class="mt-3 flex flex-wrap gap-1.5">
                            @if ($page->is_home)
                                <span class="{{ $badge }} bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">Home</span>
                            @endif
                            @if ($page->is_system)
                                <span class="{{ $badge }} bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300" title="Used by the builder — not a page in your menu">System</span>
                            @elseif ($page->is_live)
                                <span class="{{ $badge }} bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">● Live</span>
                            @elseif ($page->is_hidden)
                                <span class="{{ $badge }} bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">Hidden</span>
                            @endif
                            @foreach ($page->issues as $issue)
                                <span class="{{ $badge }} bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300">{{ $issue }}</span>
                            @endforeach
                        </span>

                        <span class="mt-3 block text-[12.5px] text-gray-500 dark:text-gray-400 line-clamp-2 min-h-[2.5em]">
                            @if ($names->isEmpty())
                                No sections yet — add some to give this page content.
                            @else
                                {{ $names->take(3)->implode(' · ') }}@if ($names->count() > 3) <span class="text-gray-400">+{{ $names->count() - 3 }} more</span>@endif
                            @endif
                        </span>
                        <span class="mt-2 block text-[11.5px] text-gray-400">{{ $layoutName($page) }} layout · edited {{ $page->last_edited?->diffForHumans() }}</span>
                    </a>
                    @if ($page->is_inactive)
                        <div class="px-5 -mt-2 pb-3">@include('partials.template-inactive', ['item' => $page, 'action' => 'activatePage', 'noun' => 'page'])</div>
                    @endif
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        <a href="{{ $editUrl }}?page={{ $page->id }}" title="Open this page in Edit site" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Edit</a>
                        <button type="button" wire:click="openPicker('{{ $page->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5" title="Attach or remove sections">Sections</button>
                        <span class="ml-auto flex items-center gap-0.5">
                            <a href="{{ $previewFor($page) }}" target="_blank" title="View live preview" class="{{ $iconBtn }} hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">{!! $svg($icons['eye']) !!}</a>
                            <button type="button" wire:click="show('{{ $page->id }}')" title="SEO & settings" class="{{ $iconBtn }} hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">{!! $svg($icons['gear']) !!}</button>
                            <button type="button" wire:click="openEdit('{{ $page->id }}')" title="Rename / change URL" class="{{ $iconBtn }} hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">{!! $svg($icons['pencil']) !!}</button>
                            <button type="button" wire:click="deletePage('{{ $page->id }}')" data-confirm="{{ $deleteMsg($page) }}" title="Delete" class="{{ $iconBtn }} hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">{!! $svg($icons['trash']) !!}</button>
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
                            <th class="px-4 py-3">Page</th>
                            <th class="px-4 py-3 text-right">Sections</th>
                            @unless ($compact)
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Layout</th>
                                <th class="px-4 py-3">Edited</th>
                            @endunless
                            <th class="w-36 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($pages as $page)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors group" wire:key="row-{{ $page->id }}">
                                <td class="{{ $pad }} {{ $page->is_inactive ? 'opacity-70' : '' }}">
                                    <a href="{{ $detailFor($page) }}" class="flex items-center gap-2.5 min-w-0">
                                        @unless ($compact)
                                        <span class="w-8 h-8 rounded-lg grid place-items-center shrink-0 {{ $page->is_home ? 'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-indigo-100 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300' }}">
                                            {!! $svg($page->is_home ? $icons['home'] : $icons['page']) !!}
                                        </span>
                                        @endunless
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-gray-900 dark:text-white truncate hover:underline">{{ $page->name }}</span>
                                            <span class="block font-mono text-[11px] text-gray-400 truncate max-w-[16rem]">{{ $page->url }}</span>
                                        </span>
                                    </a>
                                    @include('partials.template-inactive', ['item' => $page, 'action' => 'activatePage', 'noun' => 'page'])
                                </td>
                                <td class="{{ $pad }} text-right font-bold tabular-nums {{ $page->sections_count ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ $page->sections_count }}</td>
                                @unless ($compact)
                                    <td class="{{ $pad }}">
                                        <span class="flex flex-wrap gap-1">
                                            @if ($page->is_home)<span class="{{ $badge }} bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">Home</span>@endif
                                            @if ($page->is_system)<span class="{{ $badge }} bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">System</span>
                                            @elseif ($page->is_live)<span class="{{ $badge }} bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">Live</span>
                                            @elseif ($page->is_hidden)<span class="{{ $badge }} bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">Hidden</span>@endif
                                            @foreach ($page->issues as $issue)<span class="{{ $badge }} bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300">{{ $issue }}</span>@endforeach
                                        </span>
                                    </td>
                                    <td class="{{ $pad }} text-[12px] text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $layoutName($page) }}</td>
                                    <td class="{{ $pad }} text-[12px] text-gray-400 whitespace-nowrap">{{ $page->last_edited?->diffForHumans() }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-0.5 justify-end">
                                        <a href="{{ $editUrl }}?page={{ $page->id }}" title="Open this page in Edit site" class="px-2 py-1 rounded-lg text-[11px] font-bold mr-1" style="background:var(--primary);color:var(--on-primary)">Edit</a>
                                        <a href="{{ $previewFor($page) }}" target="_blank" title="View live preview" class="{{ $iconBtn }} hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">{!! $svg($icons['eye']) !!}</a>
                                        <button type="button" wire:click="openPicker('{{ $page->id }}')" title="Sections ({{ $page->sections_count }})" class="{{ $iconBtn }} hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">{!! $svg($icons['puzzle']) !!}</button>
                                        <button type="button" wire:click="show('{{ $page->id }}')" title="SEO & settings" class="{{ $iconBtn }} hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">{!! $svg($icons['gear']) !!}</button>
                                        <button type="button" wire:click="openEdit('{{ $page->id }}')" title="Rename / change URL" class="{{ $iconBtn }} hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">{!! $svg($icons['pencil']) !!}</button>
                                        <button type="button" wire:click="deletePage('{{ $page->id }}')" data-confirm="{{ $deleteMsg($page) }}" title="Delete" class="{{ $iconBtn }} hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">{!! $svg($icons['trash']) !!}</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── Create / Edit Modal ── --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex justify-end" x-data x-on:keydown.escape.window="$wire.set('showModal', false)">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
        <div class="relative h-full w-full max-w-lg bg-white dark:bg-[#1e1f2b] border-l border-gray-200 dark:border-white/[0.08]
                    shadow-2xl overflow-y-auto p-6 space-y-5"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ $editingId ? 'Edit Page' : 'New Page' }}
                </h2>
                <button wire:click="$set('showModal', false)"
                        class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Page Name</label>
                    <input wire:model="form.name" type="text" placeholder="e.g. Home"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl
                                  bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]
                                  text-gray-900 dark:text-white placeholder-gray-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500/50"/>
                    @error('form.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">URL Path</label>
                    <input wire:model="form.url" type="text" placeholder="e.g. /about"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl font-mono
                                  bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]
                                  text-gray-900 dark:text-white placeholder-gray-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500/50"/>
                    @error('form.url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                        Keywords <span class="normal-case font-normal text-gray-400">(comma separated)</span>
                    </label>
                    <input wire:model="form.keywords" type="text" placeholder="e.g. home, landing, hero"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl
                                  bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]
                                  text-gray-900 dark:text-white placeholder-gray-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500/50"/>
                    @error('form.keywords') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Start from a layout (template pages) — new pages only --}}
                @if(! $editingId && $this->layouts !== [])
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Start from a layout</label>
                    <div class="space-y-1.5 max-h-56 overflow-y-auto pr-1">
                        <label class="flex items-start gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer transition-colors
                                      {{ $layout === 'blank' ? 'border-indigo-400 bg-indigo-50/60 dark:bg-indigo-500/[0.08]' : 'border-gray-200 dark:border-white/[0.08] hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="layout" value="blank" class="mt-0.5">
                            <span>
                                <span class="block text-sm font-bold text-gray-900 dark:text-white">Blank page</span>
                                <span class="block text-xs text-gray-400">Start empty and add sections yourself.</span>
                            </span>
                        </label>
                        @foreach($this->layouts as $slug => $l)
                        <label class="flex items-start gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer transition-colors
                                      {{ $layout === $slug ? 'border-indigo-400 bg-indigo-50/60 dark:bg-indigo-500/[0.08]' : 'border-gray-200 dark:border-white/[0.08] hover:border-gray-300' }}"
                               wire:key="layout-{{ $slug }}">
                            <input type="radio" wire:model.live="layout" value="{{ $slug }}" class="mt-0.5">
                            <span class="min-w-0">
                                <span class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $l['name'] }} layout</span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500">{{ count($l['blocks']) }} sections</span>
                                </span>
                                <span class="block text-xs text-gray-400 truncate">{{ implode(' + ', $l['blocks']) }}</span>
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            <div class="flex gap-3 pt-1">
                <button wire:click="$set('showModal', false)"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a]
                               text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
                    Cancel
                </button>
                <button wire:click="save"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm">
                    {{ $editingId ? 'Save Changes' : 'Create Page' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    

    {{-- ═══ COMPONENT PICKER — attach components to a page, filter by tag ═══ --}}
    @if ($pickerPageId !== null && $this->pickerPage)
    <div class="fixed inset-0 z-50 flex justify-end" x-data x-on:keydown.escape.window="$wire.closePicker()">
        <div class="absolute inset-0 bg-black/40" wire:click="closePicker"></div>
        <div class="relative h-full w-full max-w-xl bg-white dark:bg-[#1d1e2a] border-l border-gray-100 dark:border-white/[0.06] shadow-2xl overflow-y-auto p-6"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Components on “{{ $this->pickerPage->name }}”</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Tick to attach, untick to remove — new attachments append at the end of the page.</p>
                </div>
                <button wire:click="closePicker" class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 shrink-0">✕ Close</button>
            </div>

            {{-- Search + tag filter --}}
            <div class="flex flex-wrap items-center gap-2 mt-4 mb-3">
                <div class="relative flex-1 min-w-[180px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input wire:model.live.debounce.250ms="pickerSearch" type="text" placeholder="Search components…"
                           class="w-full pl-9 pr-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                </div>
            </div>
            @if (count($this->componentTags))
            <div class="flex flex-wrap gap-1.5 mb-4">
                <button wire:click="$set('pickerTag', '')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors {{ $pickerTag === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 hover:text-indigo-500' }}">All</button>
                @foreach ($this->componentTags as $tag)
                    <button wire:click="$set('pickerTag', '{{ $tag }}')"
                            class="px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors {{ $pickerTag === $tag ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 hover:text-indigo-500' }}">#{{ $tag }}</button>
                @endforeach
            </div>
            @endif

            {{-- Component list --}}
            @php $attachedIds = $this->pickerPage->components()->pluck('components.id')->map(fn ($i) => (string) $i)->all(); @endphp
            <div class="space-y-2">
                @forelse ($this->pickerComponents as $comp)
                @php $attached = in_array((string) $comp->id, $attachedIds, true); @endphp
                <div x-data="{ peek: false }"
                     class="rounded-xl border transition-colors
                            {{ $attached ? 'border-indigo-400 bg-indigo-50/60 dark:bg-indigo-500/10' : 'border-gray-100 dark:border-white/[0.06] bg-gray-50 dark:bg-white/[0.03] hover:border-indigo-300' }}">
                    <label class="flex items-center gap-3 px-3.5 py-2.5 cursor-pointer">
                        <input type="checkbox" @checked($attached) wire:click="toggleComponent('{{ $comp->id }}')"
                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 shrink-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">🧩 {{ $comp->name }}</p>
                            <p class="text-[11px] text-gray-400 truncate">
                                {{ $comp->nodes->count() }} {{ Str::plural('node', $comp->nodes->count()) }}
                                @if ($comp->description) · {{ Str::limit($comp->description, 60) }} @endif
                            </p>
                        </div>
                        @if ($comp->tags)
                        <div class="hidden sm:flex flex-wrap gap-1 justify-end max-w-[35%]">
                            @foreach (array_slice($comp->tags, 0, 3) as $tag)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 dark:text-indigo-300 shrink-0">#{{ $tag }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if ($comp->nodes->isNotEmpty())
                        <button type="button" x-on:click.prevent.stop="peek = ! peek"
                                class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                :aria-expanded="peek" title="View this component's content">
                            👁 <span x-text="peek ? 'Hide' : 'View'"></span>
                        </button>
                        @endif
                    </label>

                    {{-- Content peek: every field with its current value --}}
                    @if ($comp->nodes->isNotEmpty())
                    <div x-show="peek" x-collapse x-cloak
                         class="px-3.5 pb-3 pt-1 border-t border-gray-100 dark:border-white/[0.06] space-y-1">
                        @foreach ($comp->nodes->sortBy('order')->take(20) as $node)
                        <div class="flex items-baseline gap-2 text-[11px]">
                            <span class="shrink-0 font-semibold text-gray-500 dark:text-gray-400">{{ $node->label ?: '(unlabelled)' }}</span>
                            <span class="min-w-0 truncate text-gray-700 dark:text-gray-200">{{ Str::limit((string) $node->value, 90) ?: '—' }}</span>
                        </div>
                        @endforeach
                        @if ($comp->nodes->count() > 20)
                            <p class="text-[10px] text-gray-400">…and {{ $comp->nodes->count() - 20 }} more — edit it in the Content tab.</p>
                        @endif
                    </div>
                    @endif
                </div>
                @empty
                <div class="py-10 text-center">
                    <p class="text-sm text-gray-400">No components match{{ $pickerTag !== '' ? ' the #'.$pickerTag.' tag' : '' }}.</p>
                    <a href="{{ url($site->name.'/components') }}" class="mt-2 inline-block text-xs font-semibold text-indigo-500 hover:underline">Create components →</a>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    {{-- ── Selected-page detail: metadata, preview & layout ── --}}
    @if($detailPageId && $this->detailPage)
    @php $dp = $this->detailPage; $preview = $site->previewUrl($dp->url); @endphp
    <x-lightbox close="closeDetail" :drawer="true" max-width="max-w-2xl" icon="📄"
                :title="$dp->name" :subtitle="$dp->url" wire:key="page-detail-{{ $dp->id }}">
        <x-slot:badge>
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $dp->is_published ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
                {{ $dp->is_published ? 'Live' : 'Draft' }}
            </span>
        </x-slot:badge>

        {{-- Quick actions --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <button wire:click="openEdit('{{ $dp->id }}')" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">✏️ Edit page</button>
            <a href="{{ route('blocks', ['siteID' => $site->name]) }}" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] text-xs font-semibold text-gray-600 dark:text-gray-300">🧱 Open in builder</a>
            @if($preview)
                <a href="{{ $preview }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#1d1e2a] text-xs font-semibold text-gray-600 dark:text-gray-300">↗ View live</a>
            @endif
        </div>

        {{-- Preview --}}
        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Page preview</p>
        @if($preview)
            <iframe src="{{ $preview }}" loading="lazy"
                    class="w-full min-h-[360px] rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white mb-5"></iframe>
        @else
            <p class="text-xs text-gray-400 mb-5 px-3 py-4 rounded-xl bg-gray-50 dark:bg-white/[0.03]">The preview renderer isn't built yet for this site.</p>
        @endif

        {{-- Metadata --}}
        <form wire:submit="saveMeta" class="space-y-3 mb-5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Metadata</p>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Meta description <span class="font-normal text-gray-400">— shown in search results</span></label>
                <textarea wire:model="metaDescription" rows="3" maxlength="5000" placeholder="A short summary of this page for search engines and link previews…"
                          class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] resize-y"></textarea>
                <p class="text-[10px] text-gray-400 mt-0.5 text-right">{{ mb_strlen($metaDescription) }} chars</p>
                @error('metaDescription')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Keywords <span class="font-normal text-gray-400">— comma separated</span></label>
                <input wire:model="metaKeywords" type="text" class="w-full px-3 py-2 rounded-xl text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                @error('metaKeywords')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Social share image (og:image URL)</label>
                <x-asset-picker model="ogImage" :site="$site" type="image" placeholder="Pick from assets, or https://…" />
                @error('ogImage')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
                <input wire:model="isPublished" type="checkbox" class="rounded"> Published (visible on the site)
            </label>

            <x-panel-group label="Custom attributes" hint="key / value pairs exposed to the site & API">
                @foreach($attrRows as $i => $row)
                <div class="flex items-center gap-2" wire:key="attr-{{ $i }}">
                    <input wire:model="attrRows.{{ $i }}.key" placeholder="key" class="w-40 px-2.5 py-1.5 rounded-lg text-xs font-mono bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                    <input wire:model="attrRows.{{ $i }}.value" placeholder="value" class="flex-1 px-2.5 py-1.5 rounded-lg text-xs bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                    <button type="button" wire:click="removeAttrRow({{ $i }})" class="text-gray-400 hover:text-red-500 text-sm">✕</button>
                </div>
                @error('attrRows.'.$i.'.key')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                @endforeach
                <button type="button" wire:click="addAttrRow" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">＋ Add attribute</button>
                @if($dp->getAttr('custom_js') !== null || $dp->getAttr('page_styles') !== null)
                    <p class="text-[10px] text-gray-400">custom_js / page_styles are managed by the builder and not shown here.</p>
                @endif
            </x-panel-group>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                <span wire:loading.remove wire:target="saveMeta">Save metadata</span>
                <span wire:loading wire:target="saveMeta">Saving…</span>
            </button>
        </form>

        {{-- Layout --}}
        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Page layout</p>
        @php $ordered = $dp->components()->withCount('nodes')->orderBy('page_component.order')->get(); @endphp
        @if($ordered->isEmpty())
            <p class="text-xs text-gray-400 px-3 py-4 rounded-xl bg-gray-50 dark:bg-white/[0.03]">No components attached yet — use the component picker
                <button wire:click="openPicker('{{ $dp->id }}')" class="text-indigo-500 font-semibold hover:underline">open picker</button>.</p>
        @else
            <div class="space-y-1.5 mb-2">
                @foreach($ordered as $i => $comp)
                <div class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.05]">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-[11px] font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $comp->name }}</span>
                    <span class="ml-auto text-[11px] text-gray-400 shrink-0">{{ $comp->nodes_count }} {{ Str::plural('field', $comp->nodes_count) }}</span>
                </div>
                @endforeach
            </div>
            <a href="{{ route('blocks', ['siteID' => $site->name]) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Rearrange in builder →</a>
        @endif
    </x-lightbox>
    @endif
    </div>

    {{-- ══ RIGHT rail: summary · needs attention · biggest · recent · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Pages summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $total }}</b> {{ Str::plural('page', $total) }} ·
                <b class="text-gray-900 dark:text-white">{{ $stats['sections'] }}</b> {{ Str::plural('section', $stats['sections']) }} ·
                <b class="text-gray-900 dark:text-white">{{ $stats['layouts'] }}</b> {{ Str::plural('layout', $stats['layouts']) }}
            </p>
            @php
                $breakdown = array_filter([
                    'live' => ['Live', $stats['live']],
                    'hidden' => ['Hidden', $stats['hidden']],
                    'inactive' => ['Inactive template', $stats['inactive']],
                    'system' => ['System', $stats['system']],
                ], fn ($r) => $r[1] > 0);
            @endphp
            @if ($total)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06]">
                    @foreach ($breakdown as $k => [$label, $n])
                        <span style="width:{{ round($n / $total * 100, 2) }}%;background:{{ $statusColor[$k] }}" title="{{ $label }} · {{ $n }}"></span>
                    @endforeach
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach ($breakdown as $k => [$label, $n])
                        <button type="button" @if ($k !== 'system') wire:click="setFilter('{{ $k }}')" @endif class="w-full flex items-center gap-2 text-[12.5px] text-left {{ $k !== 'system' ? 'hover:underline' : 'cursor-default' }}">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $statusColor[$k] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            <span class="ml-auto font-bold text-gray-900 dark:text-white">{{ $n }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-[12.5px] text-gray-400">No pages yet — create one to get started.</p>
            @endif
        </div>

        @if ($stats['attentionList']->isNotEmpty() || $stats['inactiveList']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @if ($stats['empty'])
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $stats['empty'] }} empty {{ Str::plural('page', $stats['empty']) }}</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 mb-1.5">No sections — visitors see a blank page. Add some:</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($stats['attentionList']->where('is_empty', true)->take(6) as $p)
                                <button type="button" wire:click="openPicker('{{ $p->id }}')" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white dark:bg-[#1d1e2a] text-rose-800 dark:text-rose-200 hover:underline">＋ {{ $p->name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($stats['noSeo'])
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $stats['noSeo'] }} without an SEO description</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70 mb-1.5">Search engines and link previews have nothing to show.</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($stats['attentionList']->where('has_seo', false)->take(6) as $p)
                                <button type="button" wire:click="show('{{ $p->id }}')" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white dark:bg-[#1d1e2a] text-amber-800 dark:text-amber-200 hover:underline">✎ {{ $p->name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($stats['inactive'])
                    <button type="button" wire:click="setFilter('inactive')" class="w-full text-left rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-gray-200 dark:hover:ring-white/10">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $stats['inactive'] }} inactive template {{ Str::plural('page', $stats['inactive']) }}</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Parked by a template switch — not on the live site. Activate the ones to keep →</p>
                    </button>
                @endif
            </div>
        </div>
        @endif

        @if ($stats['biggest']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Biggest pages</p>
            @php $max = max(1, $stats['biggest']->max('sections_count')); @endphp
            <div class="space-y-2.5">
                @foreach ($stats['biggest'] as $p)
                    <a href="{{ $detailFor($p) }}" class="block group">
                        <span class="flex items-center justify-between text-[12.5px]">
                            <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline">{{ $p->name }}</span>
                            <span class="font-bold text-gray-900 dark:text-white tabular-nums">{{ $p->sections_count }}</span>
                        </span>
                        <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                            <span class="block h-full rounded-full" style="width:{{ round($p->sections_count / $max * 100) }}%;background:var(--primary)"></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        @if ($stats['recent']->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Recently edited</p>
            <div class="space-y-2">
                @foreach ($stats['recent'] as $p)
                    <a href="{{ $detailFor($p) }}" class="flex items-center gap-2.5 group">
                        <span class="w-7 h-7 rounded-lg grid place-items-center shrink-0 bg-indigo-100 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                            {!! $svg($p->is_home ? $icons['home'] : $icons['page'], 'w-3.5 h-3.5') !!}
                        </span>
                        <span class="min-w-0 flex-1 text-[12.5px] font-semibold text-gray-700 dark:text-gray-200 truncate group-hover:underline">{{ $p->name }}</span>
                        <span class="text-[11px] text-gray-400 shrink-0">{{ $p->last_edited?->diffForHumans(null, true) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([
                    ['Edit site', 'Change page content', $editUrl],
                    ['Components', 'The sections on pages', route('site.components', $site->name)],
                    ['Collections', 'Lists shown on pages', route('collections', $site->name)],
                    ['Forms', 'Contact & sign-up forms', route('site.forms', $site->name)],
                    ['Assets', 'Images & media', route('media', $site->name)],
                    ['Designs', 'Layouts & styles', route('site.designs', $site->name)],
                ] as [$label, $hint, $href])
                    <a href="{{ $href }}" class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
