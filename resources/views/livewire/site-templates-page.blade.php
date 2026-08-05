@php
/**
 * Gradient literals — complete strings kept here so Tailwind's scanner picks them up.
 * from-gray-400 to-gray-600
 * from-indigo-500 to-blue-600
 * from-emerald-400 to-teal-600
 * from-violet-500 to-purple-700
 * from-rose-400 to-orange-500
 */
@endphp

{{--
  Alpine listener lives on the root div.
  When Livewire dispatches 'open-preview', Alpine opens the Vue template in a new window.
--}}
<div class="h-full flex flex-col"
     x-data="{}"
     @open-preview.window="window.open($event.detail.url, '_blank', 'width=1280,height=800,noopener,noreferrer')">

{{-- ══════════════════════════════════════════════════════════════
     MODE: GALLERY
     ══════════════════════════════════════════════════════════════ --}}
@if($mode === 'gallery')
<x-page-layout title="Templates" subtitle="Start from a template and edit to taste.">
    <x-slot:stats>
        <x-stat-tile label="Available templates" :value="count($templates)" color="#6366f1"
            icon="M4 6a2 2 0 012-2h12a2 2 0 012 2v2H4V6zm0 4h16v8a2 2 0 01-2 2H6a2 2 0 01-2-2v-8z" />
        <x-stat-tile label="Pages included" :value="collect($templates)->sum('pageCount')" color="#10b981"
            icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z" />
        <x-stat-tile label="Components" :value="collect($templates)->sum('componentCount')" color="#8b5cf6"
            icon="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
    </x-slot:stats>

<div class="flex-1 overflow-y-auto">

    {{-- Header --}}
    <div class="mb-7">
        <p class="text-xs font-bold tracking-widest text-gray-400 dark:text-gray-500 uppercase mb-1">{{ $site->name }}</p>
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Your Templates</h1>
        <p class="mt-1.5 text-gray-500 dark:text-gray-400 text-sm max-w-xl">
            Templates installed to this site. Apply one to rebuild your pages &amp; theme, preview it, or generate the site files.
            Get more from the <a href="{{ url($site->name.'/marketplace') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Marketplace</a>.
        </p>
    </div>

    {{-- ─── Browse curated templates (real apps — preview == published site) ─── --}}
    @if(count($this->curated))
    <div class="mb-9">
        <h2 class="text-sm font-bold tracking-widest text-gray-400 dark:text-gray-500 uppercase mb-3">Browse Templates</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($this->curated as $c)
            <div class="group rounded-2xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.02] overflow-hidden flex flex-col">
                <div class="aspect-[16/10] bg-cover bg-center" style="background-color: {{ $c['accent'] }};"
                     @if($c['thumbnail']) style="background-image:url('{{ $c['thumbnail'] }}'); background-color: {{ $c['accent'] }};" @endif></div>
                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-gray-900 dark:text-white">{{ $c['name'] }}</h3>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background: {{ $c['accent'] }}22; color: {{ $c['accent'] }};">{{ $c['category'] }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1 line-clamp-2 flex-1">{{ $c['description'] ?: 'A first-party template — the preview is exactly what publishes.' }}</p>
                    <div class="flex items-center gap-2 mt-4">
                        <a href="{{ $c['previewUrl'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:text-indigo-600">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12C4 7 8 4 12 4s8 3 9.5 8c-1.5 5-5.5 8-9.5 8s-8-3-9.5-8z"/></svg>
                            Live preview
                        </a>
                        <button type="button" wire:click="useCurated('{{ $c['key'] }}')" wire:loading.attr="disabled"
                            class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                            {{ $c['installed'] ? 'Use again' : 'Use this template' }}
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <h2 class="text-sm font-bold tracking-widest text-gray-400 dark:text-gray-500 uppercase mb-3">Installed</h2>

    @if(empty($templates))
        {{-- Empty state --}}
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] py-16 text-center">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No templates installed yet</p>
            <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Browse the Marketplace to install a built-in template or upload your own <code>.zip</code>.</p>
            <a href="{{ url($site->name.'/marketplace') }}"
               class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Browse Marketplace
            </a>
        </div>
    @else
    {{-- Template grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($templates as $tpl)
        <div wire:click="showDetail('{{ $tpl['key'] }}')"
             class="group rounded-2xl border border-gray-200 dark:border-white/[0.07]
                    bg-white dark:bg-[#1d1e2a] overflow-hidden flex flex-col
                    hover:border-indigo-300 dark:hover:border-indigo-500/40 hover:shadow-lg
                    transition-all cursor-pointer">

            {{-- Thumbnail: template screenshot if available, else a gradient placeholder --}}
            <div class="h-32 bg-gradient-to-br {{ $tpl['gradientClass'] }} relative flex items-end pb-3.5 px-4 shrink-0 overflow-hidden">
                @if(!empty($tpl['thumbnail']))
                    <img src="{{ $tpl['thumbnail'] }}" alt="{{ $tpl['name'] }} preview" loading="lazy"
                         class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-[1.03] transition-transform duration-300">
                    <span class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/30 to-transparent"></span>
                @else
                    <div class="absolute top-3 left-3.5 flex gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-white/40"></span>
                        <span class="w-2 h-2 rounded-full bg-white/40"></span>
                        <span class="w-2 h-2 rounded-full bg-white/40"></span>
                    </div>
                    <div class="w-full space-y-1.5 mt-5">
                        <div class="h-2 bg-white/25 rounded-full w-3/5"></div>
                        <div class="h-1.5 bg-white/15 rounded-full w-2/3"></div>
                        <div class="h-1.5 bg-white/15 rounded-full w-2/5"></div>
                    </div>
                @endif
                <span class="absolute top-3 right-3 z-10 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider"
                      style="background:rgba(0,0,0,0.45);color:#fff">
                    {{ $tpl['category'] }}
                </span>
            </div>

            {{-- Body --}}
            <div class="p-4 flex flex-col flex-1">
                <h3 class="font-bold text-gray-900 dark:text-white text-sm group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    {{ $tpl['name'] }}
                </h3>
                <p class="text-gray-500 dark:text-gray-400 text-xs mt-1.5 leading-relaxed line-clamp-2 flex-1">
                    {{ $tpl['description'] }}
                </p>

                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.05] flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-gray-400 dark:text-gray-500">
                            {{ $tpl['pageCount'] > 0 ? $tpl['pageCount'].' '.Str::plural('page', $tpl['pageCount']) : 'Blank' }}
                        </span>
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <span class="text-[10px] text-gray-400 dark:text-gray-500">v{{ $tpl['version'] }}</span>
                    </div>
                    <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400
                                 group-hover:underline transition-all flex items-center gap-1">
                        View details
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
</x-page-layout>

@endif

{{-- ══════════════════════════════════════════════════════════════
     MODE: DETAIL
     ══════════════════════════════════════════════════════════════ --}}
@if($mode === 'detail' && count($selectedTemplate) > 0)
@php
    $tpl = $selectedTemplate;
    $formattedDate = \Carbon\Carbon::parse($tpl['createdAt'])->format('F j, Y');
@endphp

<div class="flex-1 overflow-y-auto">

    {{-- ── Top action bar ── --}}
    <div class="sticky top-0 z-10 flex items-center gap-3 px-6 h-14 shrink-0
                bg-white dark:bg-[#1d1e2a]
                border-b border-gray-200 dark:border-white/[0.06]">

        <button wire:click="backToGallery"
                class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400
                       hover:text-gray-800 dark:hover:text-white transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            Templates
        </button>

        <svg class="w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>

        <span class="font-semibold text-sm text-gray-800 dark:text-white">{{ $tpl['name'] }}</span>

        {{-- Right: action buttons --}}
        <div class="ml-auto flex items-center gap-2">

            {{-- Preview --}}
            <button wire:click="previewTemplate('{{ $tpl['key'] }}')"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                           border border-gray-200 dark:border-white/[0.10]
                           text-gray-600 dark:text-gray-300
                           hover:border-indigo-400 dark:hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400
                           transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview
            </button>

            {{-- Use / Stop using — appends the template's pages, theme, font & assets
                 (reversible). Stop using removes exactly what it added. --}}
            @if(!empty($tpl['applied']))
                <button wire:click="stopUsing"
                        data-confirm="Stop using this template? Its pages, theme, font and assets will be removed from {{ $site->name }}. Your default content stays."
                        wire:loading.attr="disabled" wire:target="stopUsing"
                        class="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold text-white cursor-pointer transition-opacity hover:opacity-90 shadow-sm"
                        style="background:#e11d48">
                    <span wire:loading.remove wire:target="stopUsing" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        In use — Stop using
                    </span>
                    <span wire:loading wire:target="stopUsing">Removing…</span>
                </button>
            @else
                <button wire:click="applyTemplate"
                        data-confirm="Use this template? Its pages, theme, font and assets will be added to {{ $site->name }} (you can stop using it later to remove them)."
                        wire:loading.attr="disabled" wire:target="applyTemplate"
                        class="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold text-white cursor-pointer transition-opacity hover:opacity-90 shadow-sm"
                        style="background:#10b981">
                    <span wire:loading.remove wire:target="applyTemplate" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Use this Template
                    </span>
                    <span wire:loading wire:target="applyTemplate" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Applying…
                    </span>
                </button>
            @endif

            {{-- Generate static Vue site files --}}
            <button wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold
                           text-white cursor-pointer transition-opacity hover:opacity-90 shadow-sm"
                    style="background:var(--primary)">
                <span wire:loading.remove wire:target="generate" class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Generate Site
                </span>
                <span wire:loading wire:target="generate" class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Generating…
                </span>
            </button>
        </div>
    </div>

    {{-- ── Detail body ── --}}
    <div class="max-w-3xl mx-auto px-6 py-8 space-y-8">

        {{-- Hero card --}}
        <div class="rounded-2xl overflow-hidden border border-gray-200 dark:border-white/[0.07]">
            {{-- Banner: template screenshot if available, else a gradient with the name --}}
            <div class="h-56 bg-gradient-to-br {{ $tpl['gradientClass'] }} relative flex items-center justify-center overflow-hidden">
                @if(!empty($tpl['thumbnail']))
                    <img src="{{ $tpl['thumbnail'] }}" alt="{{ $tpl['name'] }} preview" loading="lazy"
                         class="absolute inset-0 w-full h-full object-cover object-top">
                    <span class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-black/20"></span>
                @else
                    <div class="absolute top-4 left-4 flex gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-white/40"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-white/40"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-white/40"></span>
                    </div>
                @endif
                {{-- Template name overlay --}}
                <div class="relative text-center px-4">
                    <p class="text-white/75 text-xs font-bold tracking-widest uppercase mb-1" style="text-shadow:0 1px 6px rgba(0,0,0,.4)">{{ $tpl['category'] }}</p>
                    <h2 class="text-white text-3xl font-black tracking-tight" style="text-shadow:0 2px 10px rgba(0,0,0,.5)">{{ $tpl['name'] }}</h2>
                </div>
                {{-- Version badge --}}
                <span class="absolute bottom-4 right-4 z-10 text-[10px] font-bold px-2.5 py-1 rounded-full"
                      style="background:rgba(0,0,0,0.4);color:#fff">
                    v{{ $tpl['version'] }}
                </span>
            </div>

            {{-- Meta strip --}}
            <div class="bg-white dark:bg-[#1d1e2a] px-6 py-4 flex flex-wrap gap-6">
                {{-- Author --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-0.5">Author</p>
                    <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-black text-white"
                             style="background:var(--primary)">
                            {{ strtoupper(substr($tpl['author'], 0, 1)) }}
                        </div>
                        <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ $tpl['author'] }}</span>
                    </div>
                </div>

                {{-- Published --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-0.5">Published</p>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ $formattedDate }}</span>
                </div>

                {{-- Pages --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-0.5">Pages</p>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">
                        {{ $tpl['pageCount'] > 0 ? $tpl['pageCount'] : '—' }}
                    </span>
                </div>

                {{-- Components --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-0.5">Components</p>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">
                        {{ $tpl['componentCount'] > 0 ? $tpl['componentCount'] : '—' }}
                    </span>
                </div>

                {{-- Framework --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-0.5">Framework</p>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">Vue.js 3</span>
                </div>
            </div>
        </div>

        {{-- Description --}}
        <div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">About this template</h3>
            <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">{{ $tpl['description'] }}</p>
        </div>

        {{-- Tags --}}
        <div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Tags</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($tpl['tags'] as $tag)
                <span class="text-xs font-semibold px-3 py-1 rounded-full
                             bg-gray-100 dark:bg-white/[0.06]
                             text-gray-600 dark:text-gray-400
                             border border-gray-200 dark:border-white/[0.07]">
                    {{ $tag }}
                </span>
                @endforeach
            </div>
        </div>

        {{-- Features --}}
        <div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">What's included</h3>
            <ul class="space-y-2">
                @foreach($tpl['features'] as $feature)
                <li class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $feature }}</span>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Page structure --}}
        @if(count($tpl['pages']) > 0)
        <div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Page structure</h3>
            <div class="rounded-xl border border-gray-200 dark:border-white/[0.07] overflow-hidden divide-y divide-gray-100 dark:divide-white/[0.05]">
                @foreach($tpl['pages'] as $pg)
                <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-[#1d1e2a]">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ $pg['name'] }}</p>
                        <p class="text-[11px] font-mono text-gray-400 dark:text-gray-500">{{ $pg['url'] }}</p>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">
                        {{ $pg['componentCount'] }} {{ Str::plural('component', $pg['componentCount']) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="rounded-xl border border-dashed border-gray-200 dark:border-white/[0.08] px-5 py-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <p class="text-sm text-gray-500 dark:text-gray-400">No pre-built pages — you start with a blank canvas.</p>
        </div>
        @endif

        {{-- Bottom CTA --}}
        <div class="flex items-center gap-3 pt-2 pb-4">
            <button wire:click="previewTemplate('{{ $tpl['key'] }}')"
                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold
                           border border-gray-200 dark:border-white/[0.10]
                           text-gray-700 dark:text-gray-300
                           hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400
                           transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Preview in New Window
            </button>

            <button wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold
                           text-white cursor-pointer transition-opacity hover:opacity-90 shadow-sm"
                    style="background:var(--primary)">
                <span wire:loading.remove wire:target="generate" class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Generate Site
                </span>
                <span wire:loading wire:target="generate" class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Generating…
                </span>
            </button>
        </div>

    </div>
</div>

@endif

{{-- ══════════════════════════════════════════════════════════════
     MODE: SUCCESS
     ══════════════════════════════════════════════════════════════ --}}
@if($mode === 'success')
@php $tpl = $selectedTemplate; @endphp
<div class="flex-1 flex items-center justify-center p-8">
    <div class="text-center max-w-md w-full">

        {{-- Check icon --}}
        <div class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center"
             style="background:linear-gradient(135deg,var(--primary),var(--primary-2))">
            <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2">Site Generated!</h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">
            The <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $tpl['name'] ?? $selectedKey }}</span>
            template has been exported for
            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $site->name }}</span>.
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mb-8 font-mono">
            public/sites/<span class="text-indigo-500">{{ $generatedFolder }}</span>/
        </p>

        {{-- What was created --}}
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.03] p-4 mb-8 text-left">
            <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">What was created</p>
            <div class="space-y-2">
                @foreach([['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'text' => 'index.html — Vue.js SPA entry point'], ['icon' => 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'text' => 'content.json — your site\'s data, baked in']] as $item)
                <div class="flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                        </svg>
                    </div>
                    <span class="text-xs text-gray-600 dark:text-gray-400 font-mono">{{ $item['text'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-col gap-3">
            <a href="{{ url('sites/'.$generatedFolder.'/index.html') }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center justify-center gap-2 w-full py-3 text-sm font-semibold
                      text-white rounded-xl transition-opacity hover:opacity-90"
               style="background:var(--primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Open Generated Site
            </a>

            <a href="/{{ $site->name }}/pages"
               class="flex items-center justify-center gap-2 w-full py-3 text-sm font-semibold
                      text-gray-600 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-white/[0.08]
                      hover:border-gray-300 dark:hover:border-white/[0.15] transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Edit Pages in Dashboard
            </a>

            <button wire:click="backToGallery"
                    class="w-full py-3 text-sm font-medium text-gray-400 dark:text-gray-500
                           hover:text-gray-600 dark:hover:text-gray-300 transition-colors cursor-pointer">
                ← Try Another Template
            </button>
        </div>

        {{-- ── Publish to GitHub ── --}}
        <div class="mt-6 text-left bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.06] rounded-2xl p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-5 h-5 text-gray-800 dark:text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5C5.7.5.5 5.7.5 12c0 5.1 3.3 9.4 7.9 10.9.6.1.8-.3.8-.6v-2c-3.2.7-3.9-1.5-3.9-1.5-.5-1.3-1.3-1.7-1.3-1.7-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.7-1.6-2.6-.3-5.3-1.3-5.3-5.7 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.3 1.2a11.5 11.5 0 016 0C17.3 4.7 18.3 5 18.3 5c.6 1.6.2 2.8.1 3.1.8.8 1.2 1.8 1.2 3.1 0 4.4-2.7 5.4-5.3 5.7.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6 4.6-1.5 7.9-5.8 7.9-10.9C23.5 5.7 18.3.5 12 .5z"/></svg>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Publish to GitHub</h3>
            </div>

            @if($this->github && $this->github->isConfigured())
                {{-- Connected --}}
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Connected to
                    <a href="{{ $this->github->repoUrl() }}" target="_blank" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">{{ $this->github->owner }}/{{ $this->github->repo }}</a>
                    @if($this->github->last_pushed_at)<span class="text-gray-400"> · last pushed {{ $this->github->last_pushed_at->diffForHumans() }}</span>@endif
                </p>

                @if($ghResult)
                    <div class="mb-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 px-4 py-3 text-xs">
                        <p class="font-semibold text-emerald-700 dark:text-emerald-300">✓ Pushed successfully.</p>
                        <a href="{{ $ghResult['repo_url'] }}" target="_blank" class="block mt-1 text-emerald-700 dark:text-emerald-300 hover:underline truncate">{{ $ghResult['repo_url'] }}</a>
                        @if(!empty($ghResult['pages_url']))
                            <a href="{{ $ghResult['pages_url'] }}" target="_blank" class="block mt-0.5 text-emerald-700 dark:text-emerald-300 hover:underline truncate">🌐 {{ $ghResult['pages_url'] }} (Pages may take a minute)</a>
                        @endif
                    </div>
                @endif

                <div class="flex items-center gap-2">
                    <button wire:click="pushToGithub" wire:loading.attr="disabled" wire:target="pushToGithub"
                            class="fx flex-1 inline-flex items-center justify-center gap-2 py-2.5 text-sm font-semibold text-white rounded-xl disabled:opacity-60"
                            style="background:#1f2330">
                        <span wire:loading.remove wire:target="pushToGithub">↑ Push to GitHub</span>
                        <span wire:loading wire:target="pushToGithub" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                            Publishing…
                        </span>
                    </button>
                    <button wire:click="disconnectGithub"
                            class="fx px-3 py-2.5 text-xs font-medium text-gray-400 hover:text-red-500 rounded-xl border border-gray-200 dark:border-white/[0.08]">
                        Disconnect
                    </button>
                </div>
            @else
                {{-- Connect form --}}
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Paste a <a href="https://github.com/settings/tokens/new?scopes=repo&description=Olux%20CMS" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">Personal Access Token</a> (repo scope) to push this site to its own repository.
                </p>
                <div class="space-y-2">
                    <input type="text" wire:model="ghRepo" placeholder="repository name (e.g. my-site)"
                           class="w-full px-3 py-2 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                    <input type="text" wire:model="ghOwner" placeholder="owner / org (optional — defaults to you)"
                           class="w-full px-3 py-2 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                    <input type="password" wire:model="ghToken" placeholder="ghp_… personal access token"
                           class="w-full px-3 py-2 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                    <button wire:click="connectGithub" wire:loading.attr="disabled" wire:target="connectGithub"
                            class="fx w-full inline-flex items-center justify-center gap-2 py-2.5 text-sm font-semibold text-white rounded-xl disabled:opacity-60"
                            style="background:#1f2330">
                        <span wire:loading.remove wire:target="connectGithub">Connect GitHub</span>
                        <span wire:loading wire:target="connectGithub">Verifying…</span>
                    </button>
                </div>
            @endif

            @error('ghRepo') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
            @error('ghToken') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
            @if($ghError) <p class="text-xs text-red-500 mt-2">{{ $ghError }}</p> @endif
        </div>
    </div>
</div>
@endif

{{-- ══════════════════ APPLIED ══════════════════ --}}
@if($mode === 'applied')
<div class="max-w-md mx-auto px-6 py-16">
    <div class="bg-white dark:bg-[#1d1e2a] rounded-3xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-8 text-center">
        <div class="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2">Template applied!</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Created <strong>{{ $appliedPages }}</strong> {{ Str::plural('page', $appliedPages) }} and
            <strong>{{ $appliedComponents }}</strong> {{ Str::plural('component', $appliedComponents) }} on
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $site->name }}</span>.
        </p>

        <div class="grid grid-cols-2 gap-2 mt-6">
            <a href="{{ url($site->name.'/pages') }}" class="py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">View Pages</a>
            <a href="{{ url($site->name.'/components') }}" class="py-2.5 text-sm font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">View Components</a>
        </div>
        <a href="{{ url('preview/'.$site->name.'/').'?preview=1' }}" target="_blank"
           class="mt-2 flex items-center justify-center gap-2 w-full py-2.5 text-sm font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Preview Home Page
        </a>

        <button wire:click="backToGallery"
                class="w-full py-3 mt-1 text-sm font-medium text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors cursor-pointer">
            ← Back to Templates
        </button>
    </div>
</div>
@endif

</div>
