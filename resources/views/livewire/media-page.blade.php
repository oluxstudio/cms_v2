@php
    $tabs = [
        'all'      => ['label' => 'All',       'count' => $counts['all']],
        'image'    => ['label' => 'Images',    'count' => $counts['image']],
        'video'    => ['label' => 'Videos',    'count' => $counts['video']],
        'audio'    => ['label' => 'Audio',     'count' => $counts['audio'] ?? 0],
        'font'     => ['label' => 'Fonts',     'count' => $counts['font'] ?? 0],
        'document' => ['label' => 'Others',    'count' => $counts['document']],
    ];
    $typeStyles = [
        'image'    => 'bg-indigo-600 text-white',
        'video'    => 'bg-pink-600 text-white',
        'audio'    => 'bg-emerald-600 text-white',
        'font'     => 'bg-violet-600 text-white',
        'document' => 'bg-amber-500 text-white',
    ];
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    // Solid buttons (the app body is a gradient — outline buttons need a filled background).
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
@endphp

<x-tri-layout title="Assets" :subtitle="$counts['all'].' files · '.$recent.' added this week'" :site-name="$site->name"
    :labels="['📊 Overview', '🗂️ Assets', '📌 Summary']" quick-width="lg:!w-[300px] xl:!w-[320px]">

    <x-slot:header>
        <button wire:click="openCreate" class="{{ $btnSolid }} text-sm px-4 py-2.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            Add by URL
        </button>
    </x-slot:header>

    {{-- ══ LEFT rail: counts + storage ══ --}}
    <x-slot:rail>
        <div class="grid grid-cols-2 gap-3">
            <x-tile accent="ink" wide :value="$counts['all']" label="All files" :sub="$recent.' added this week'"
                icon="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
            <x-tile accent="lavender" :value="$counts['image']" label="Images" icon="M4 16l4.6-4.6a2 2 0 012.8 0L16 16M14 8h.01M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
            <x-tile accent="rose" :value="$counts['video']" label="Videos" icon="M15 10l4.6-2.3A1 1 0 0121 8.6v6.8a1 1 0 01-1.4.9L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
            <x-tile accent="lime" :value="$counts['audio'] ?? 0" label="Audio" icon="M9 19V6l11-2v13M9 19a2 2 0 11-4 0 2 2 0 014 0zm11-2a2 2 0 11-4 0 2 2 0 014 0z" />
            <x-tile accent="sky" :value="$counts['font'] ?? 0" label="Fonts" icon="M4 7V5a1 1 0 011-1h14a1 1 0 011 1v2M9 20h6M12 4v16" />
            <x-tile accent="cocoa" wide :value="$counts['document']" label="Others" sub="documents & files" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z" />
        </div>

        {{-- Storage: used vs plan quota + free space --}}
        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Storage</span>
                <span class="text-[11px] font-semibold {{ ($storage['pct'] ?? 0) >= 90 ? 'text-rose-500' : 'text-gray-500 dark:text-gray-400' }}">{{ $storage['pct'] }}%</span>
            </div>
            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $storage['used_h'] }} <span class="font-normal text-gray-400">/ {{ $storage['limit_h'] }}</span></p>
            <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                <div class="h-full rounded-full transition-all {{ ($storage['pct'] ?? 0) >= 90 ? 'bg-rose-500' : (($storage['pct'] ?? 0) >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width:{{ max(2, $storage['pct']) }}%"></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-1.5">{{ $storage['free_h'] }} free for new assets · shared by all your sites</p>
        </div>
    </x-slot:rail>

<div class="max-w-[52rem] mx-auto"
     x-data="{ toast:'', toastType:'success', copied:'' }"
     x-init="
        $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
     ">

    {{-- ════════ Drag & drop upload panel (solid card) ════════ --}}
    <div x-data="{ over:false }"
         x-on:dragover.prevent.stop="over=true"
         x-on:dragleave.prevent.stop="over=false"
         x-on:drop.prevent.stop="over=false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles:true }))"
         :class="over ? 'border-indigo-500 ring-4 ring-indigo-500/20 bg-indigo-50 dark:bg-[#232540]' : 'border-gray-300 dark:border-white/[0.14] bg-white dark:bg-[#1d1e2a]'"
         class="relative border-2 border-dashed rounded-[1.75rem] shadow-sm mb-6 transition-colors">

        <input type="file" wire:model="uploads" multiple x-ref="input" id="media-input"
               accept="image/*,video/*,audio/*,.svg,.ttf,.otf,.woff,.woff2,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.ppt,.pptx,.zip"
               class="hidden">

        {{-- Whole panel is a clickable label → opens the file dialog --}}
        <label for="media-input" class="flex flex-col sm:flex-row items-center gap-4 cursor-pointer px-6 py-7 text-center sm:text-left" wire:loading.remove wire:target="uploads">
            <div class="w-14 h-14 shrink-0 rounded-2xl flex items-center justify-center" style="background:var(--primary)">
                <svg class="w-7 h-7" style="color:var(--on-primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Drag &amp; drop files here</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Images, video, audio, fonts and documents · up to 50&nbsp;MB each · several at once</p>
            </div>
            <span class="inline-flex items-center gap-2 shrink-0 px-5 py-2.5 text-sm font-bold rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Browse files
            </span>
        </label>

        <div wire:loading.flex wire:target="uploads" class="flex-col items-center justify-center hidden px-6 py-9">
            <svg class="w-7 h-7 animate-spin mb-2" style="color:var(--primary)" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Uploading…</p>
        </div>

        @error('uploads')   <p class="text-xs text-red-500 pb-3 text-center">{{ $message }}</p> @enderror
        @error('uploads.*') <p class="text-xs text-red-500 pb-3 text-center">{{ $message }}</p> @enderror
    </div>

    {{-- ════════ Tabs + search ════════ --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        <div class="min-w-0 flex-1 basis-full sm:basis-auto">
            <div class="flex gap-2 overflow-x-auto no-scrollbar">
                @foreach($tabs as $key => $tab)
                    <button type="button" wire:click="setTab('{{ $key }}')"
                        class="shrink-0 px-4 py-1.5 rounded-full text-sm font-semibold border transition-colors
                            {{ $activeTab === $key
                                ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                                : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                        {{ $tab['label'] }} <span class="opacity-60">{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <div class="relative w-full sm:w-auto">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search"
                              placeholder="Search media…" class="w-full sm:w-52" style="padding-left:2.25rem" />
            </div>
        </div>
    </div>

    @if ($missingAlt)
        <div class="mb-4 flex items-center gap-3 rounded-2xl px-4 py-3 bg-amber-50 dark:bg-amber-500/10 text-[13px] text-amber-800 dark:text-amber-200">
            <span class="flex-1">Showing images without alt text — open one with <b>Edit</b> to add a short description.</span>
            <button type="button" wire:click="setTab('all')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Show all</button>
        </div>
    @endif

    {{-- ════════ Grid ════════ --}}
    @if($mediaItems->isEmpty())
    <div class="flex flex-col items-center justify-center py-20 text-center {{ $panel }}">
        <span class="text-4xl mb-3">🗂️</span>
        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No {{ $activeTab === 'all' ? '' : $activeTab }} files{{ $search ? ' match your search' : '' }}.</p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Drag files into the panel above to upload.</p>
    </div>
    @elseif($viewMode !== 'grid')
    {{-- ── List & Compact (thumbnail rows; compact tightens) ── --}}
    @php $compact = $viewMode === 'compact'; @endphp
    <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] divide-y divide-gray-50 dark:divide-white/[0.04] overflow-hidden">
        @foreach($mediaItems as $item)
        <div class="group flex items-center gap-3 {{ $compact ? 'px-4 py-2' : 'px-4 py-3' }} hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors">
            <div wire:click="preview('{{ $item->id }}')" class="{{ $compact ? 'w-9 h-9' : 'w-12 h-12' }} rounded-lg bg-gray-100 dark:bg-white/[0.04] overflow-hidden shrink-0 cursor-pointer relative">
                @switch($item->file_type)
                    @case('image')
                        {{-- SVGs render on a light checker so transparent marks are visible --}}
                        <img src="{{ $item->url }}" alt="{{ $item->alt_text ?: $item->name }}" class="w-full h-full {{ Str::endsWith(Str::lower($item->name), '.svg') ? 'object-contain p-1 bg-white' : 'object-cover' }}" loading="lazy">
                        @break
                    @case('video')
                        <span class="w-full h-full flex items-center justify-center text-gray-400"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
                        @break
                    @case('audio')
                        <span class="w-full h-full flex items-center justify-center text-emerald-500"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l11-2v13M9 19a2 2 0 11-4 0 2 2 0 014 0zm11-2a2 2 0 11-4 0 2 2 0 014 0z"/></svg></span>
                        @break
                    @case('font')
                        <span class="w-full h-full flex items-center justify-center font-extrabold text-violet-500 {{ $compact ? 'text-sm' : 'text-lg' }}">Aa</span>
                        @break
                    @default
                        <span class="w-full h-full flex items-center justify-center text-[9px] uppercase text-gray-400">{{ pathinfo($item->name, PATHINFO_EXTENSION) ?: 'file' }}</span>
                @endswitch
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" title="{{ $item->name }}">{{ $item->name }}</p>
                @unless($compact)
                    <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ ucfirst($item->file_type) }} · {{ $item->size ?: '—' }}</p>
                @endunless
            </div>
            <span class="hidden sm:inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $typeStyles[$item->file_type] ?? '' }}">{{ ucfirst($item->file_type) }}</span>
            <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $item->publicUrl() }}'); copied='{{ $item->id }}'; setTimeout(()=>copied='',1500)"
                        class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline px-1.5">
                    <span x-show="copied !== '{{ $item->id }}'">Copy</span>
                    <span x-show="copied === '{{ $item->id }}'" x-cloak class="text-emerald-500">Copied!</span>
                </button>
                <button wire:click="preview('{{ $item->id }}')" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]" title="Preview">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
                <button wire:click="openEdit('{{ $item->id }}')" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10" title="Edit">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button wire:click="deleteMedia('{{ $item->id }}')" data-confirm="Delete this file? The stored file is removed too." class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10" title="Delete">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        @endforeach
        @if($mediaItems->hasPages())
        <div class="px-4 py-3">{{ $mediaItems->links() }}</div>
        @endif
    </div>
    @else
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        @foreach($mediaItems as $item)
        <div class="group bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm overflow-hidden flex flex-col" wire:key="media-{{ $item->id }}">
            {{-- Thumbnail (click to preview) --}}
            <div wire:click="preview('{{ $item->id }}')" class="aspect-square bg-gray-100 dark:bg-white/[0.04] relative cursor-pointer">
                <span class="absolute inset-0 z-10 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
                @switch($item->file_type)
                    @case('image')
                        <img src="{{ $item->url }}" alt="{{ $item->alt_text ?: $item->name }}" class="w-full h-full {{ Str::endsWith(Str::lower($item->name), '.svg') ? 'object-contain p-3 bg-white' : 'object-cover' }}" loading="lazy">
                        @break
                    @case('video')
                        <video src="{{ $item->url }}" class="w-full h-full object-cover" muted preload="metadata"></video>
                        <span class="absolute inset-0 flex items-center justify-center">
                            <span class="w-10 h-10 rounded-full bg-black/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </span>
                        @break
                    @case('font')
                        <div class="w-full h-full flex items-center justify-center text-4xl font-extrabold text-violet-500">Aa</div>
                        @break
                    @default
                        <div class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-[10px] mt-1 uppercase">{{ pathinfo($item->name, PATHINFO_EXTENSION) ?: 'file' }}</span>
                        </div>
                @endswitch
                <span class="absolute top-2 left-2 text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm {{ $typeStyles[$item->file_type] ?? 'bg-gray-700 text-white' }}">{{ ucfirst($item->file_type) }}</span>
                @if ($item->file_type === 'image' && blank($item->alt_text))
                    <span class="absolute top-2 right-2 text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm bg-amber-500 text-white" title="No alt text">no alt</span>
                @endif
            </div>

            {{-- Meta --}}
            <div class="p-3 flex-1 flex flex-col">
                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate" title="{{ $item->name }}">{{ $item->name }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $item->size ?: '—' }}</p>

                <div class="flex items-center gap-1.5 mt-auto pt-2.5">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $item->publicUrl() }}'); copied='{{ $item->id }}'; setTimeout(()=>copied='',1500)"
                            title="Copy this file's URL"
                            class="flex-1 min-w-0 min-h-[30px] inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-lg text-[11.5px] font-bold" style="background:var(--primary);color:var(--on-primary)">
                        <svg x-show="copied !== '{{ $item->id }}'" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 8V5a1 1 0 011-1h10a1 1 0 011 1v10a1 1 0 01-1 1h-3M5 8h10a1 1 0 011 1v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9a1 1 0 011-1z"/></svg>
                        <svg x-show="copied === '{{ $item->id }}'" x-cloak class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                        <span class="sr-only">Copy URL</span>
                    </button>
                    <button wire:click="openEdit('{{ $item->id }}')" class="{{ $btnSolid }} w-[30px] h-[30px]" title="Edit name & alt text">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button wire:click="deleteMedia('{{ $item->id }}')" data-confirm="Delete this file? The stored file is removed too." class="{{ $btnSolid }} w-[30px] h-[30px] hover:!text-red-600 hover:!border-red-200" title="Delete">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($mediaItems->hasPages())
    <div class="mt-6">
        {{ $mediaItems->links() }}
    </div>
    @endif
    @endif

    {{-- ════════ Preview lightbox ════════ --}}
    @if($this->previewItem)
    @php $p = $this->previewItem; @endphp
    <div class="fixed inset-0 z-[55] flex items-center justify-center p-4 sm:p-8" wire:key="preview-{{ $p->id }}">
        <div class="absolute inset-0 bg-black/80" wire:click="closePreview"></div>

        <div class="relative max-w-4xl w-full max-h-[90vh] bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-white/[0.06] shrink-0">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $p->name }}</p>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ ucfirst($p->file_type) }} · {{ $p->size ?: '—' }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ $p->publicUrl() }}" target="_blank" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Open original</a>
                    <button wire:click="closePreview" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-auto bg-gray-50 dark:bg-black/40 flex items-center justify-center p-4">
                @switch($p->file_type)
                    @case('image')
                        <img src="{{ $p->publicUrl() }}" alt="{{ $p->alt_text ?: $p->name }}" class="max-w-full max-h-[70vh] object-contain rounded-lg">
                        @break
                    @case('video')
                        <video src="{{ $p->publicUrl() }}" controls autoplay class="max-w-full max-h-[70vh] rounded-lg bg-black"></video>
                        @break
                    @default
                        @php $ext = strtolower(pathinfo($p->name, PATHINFO_EXTENSION)); @endphp
                        @if($ext === 'pdf')
                            <iframe src="{{ $p->publicUrl() }}" class="w-full h-[70vh] rounded-lg bg-white" title="{{ $p->name }}"></iframe>
                        @else
                            <div class="text-center py-12">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-amber-100 dark:bg-amber-500/10 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $p->name }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 uppercase">{{ $ext ?: 'file' }} document</p>
                                <a href="{{ $p->publicUrl() }}" target="_blank" download class="inline-block mt-4 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">Download / Open</a>
                            </div>
                        @endif
                @endswitch
            </div>
        </div>
    </div>
    @endif

    {{-- ════════ Add-by-URL / Edit modal ════════ --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
        <div class="relative bg-white dark:bg-[#1e1f2b] rounded-2xl shadow-2xl w-full max-w-lg border border-gray-200 dark:border-white/[0.08] p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingId ? 'Edit media' : 'Add media by URL' }}</h2>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div>
                <x-field.text label="Name" model="name" />
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-field.select label="Type" model="file_type" :empty="null"
                                :options="['image' => 'Image', 'video' => 'Video', 'document' => 'Document']" />
                <x-field.text label="Size (optional)" model="size" placeholder="e.g. 240 KB" />
            </div>
            <div>
                <x-field.text label="URL" model="url" placeholder="https://… or /storage/…" mono />
                @error('url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <x-field.text label="Alt text (optional)" model="alt_text" />
            <div class="flex gap-3 pt-1">
                <button wire:click="$set('showModal', false)" class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
                <button wire:click="save" class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm">{{ $editingId ? 'Save' : 'Add' }}</button>
            </div>
        </div>
    </div>
    @endif


    {{-- Toast --}}
    <div x-show="toast" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-[60] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium"
         :class="toastType === 'success' ? 'bg-gray-900 text-white' : 'bg-red-600 text-white'">
        <span x-text="toast"></span>
    </div>
</div>

    {{-- ══ RIGHT rail: what's in the library, what needs attention, related ══ --}}
    <x-slot:quick>
        @php $typeColor = ['image' => '#6366f1', 'video' => '#ec4899', 'audio' => '#10b981', 'font' => '#8b5cf6', 'document' => '#f59e0b']; @endphp
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Library summary</p>
            <p class="text-[13px] text-gray-600 dark:text-gray-300 mb-3">
                <b class="text-gray-900 dark:text-white">{{ $counts['all'] }}</b> {{ Str::plural('file', $counts['all']) }} ·
                <b class="text-gray-900 dark:text-white">{{ \App\Models\Media::humanSize($summary['totalBytes']) }}</b> stored
            </p>
            @if ($summary['totalBytes'] > 0)
                <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-white/[0.06] mb-3">
                    @foreach ($summary['byType'] as $bt)
                        @if ($bt['bytes'] > 0)
                            <span style="width:{{ max(2, round($bt['bytes'] / $summary['totalBytes'] * 100)) }}%;background:{{ $typeColor[$bt['type']] ?? '#9ca3af' }}" title="{{ ucfirst($bt['type']) }}"></span>
                        @endif
                    @endforeach
                </div>
            @endif
            <ul class="space-y-1.5">
                @forelse ($summary['byType'] as $bt)
                    <li class="flex items-center gap-2 text-[12.5px]">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $typeColor[$bt['type']] ?? '#9ca3af' }}"></span>
                        <span class="flex-1 text-gray-700 dark:text-gray-200">{{ $tabs[$bt['type']]['label'] ?? ucfirst($bt['type']) }} <span class="text-gray-400">· {{ $bt['n'] }}</span></span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ \App\Models\Media::humanSize($bt['bytes']) }}</span>
                    </li>
                @empty
                    <li class="text-[12.5px] text-gray-500">Nothing uploaded yet.</li>
                @endforelse
            </ul>
        </div>

        @if ($summary['missingAlt'] || $summary['heavy'] || $summary['unused']['count'])
            <div class="{{ $panel }} p-5">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-2">Needs attention</h3>
                <div class="space-y-2.5">
                    @if ($summary['missingAlt'])
                        <button type="button" wire:click="showMissingAlt" class="w-full text-left flex items-start gap-2.5 rounded-xl p-2.5 bg-amber-50 dark:bg-amber-500/10">
                            <span class="mt-0.5 w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-bold text-gray-900 dark:text-white">{{ $summary['missingAlt'] }} {{ Str::plural('image', $summary['missingAlt']) }} without alt text</span>
                                <span class="block text-[11.5px] text-gray-600 dark:text-gray-300">Helps screen readers and search. Show them →</span>
                            </span>
                        </button>
                    @endif
                    @if ($summary['heavy'])
                        <div class="flex items-start gap-2.5 rounded-xl p-2.5 bg-rose-50 dark:bg-rose-500/10">
                            <span class="mt-0.5 w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-bold text-gray-900 dark:text-white">{{ $summary['heavy'] }} {{ Str::plural('image', $summary['heavy']) }} over 1 MB</span>
                                <span class="block text-[11.5px] text-gray-600 dark:text-gray-300">Large images slow pages down — compress or resize them.</span>
                            </span>
                        </div>
                    @endif
                    @if ($summary['unused']['count'])
                        <div class="rounded-xl p-2.5 bg-gray-50 dark:bg-white/[0.04]">
                            <p class="text-[13px] font-bold text-gray-900 dark:text-white">{{ $summary['unused']['count'] }} {{ Str::plural('file', $summary['unused']['count']) }} not found in your content <span class="font-normal text-gray-500">· {{ $summary['unused']['bytes'] }}</span></p>
                            <p class="text-[11.5px] text-gray-500 dark:text-gray-400">Not used in sections, posts or settings — check before deleting, a template may still use them.</p>
                            <div class="mt-1.5 space-y-0.5">
                                @foreach ($summary['unused']['sample'] as $u)
                                    <button type="button" wire:click="preview('{{ $u['id'] }}')" class="block w-full text-left text-[12px] text-gray-700 dark:text-gray-200 truncate hover:underline">{{ $u['name'] }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($summary['largest'])
            <div class="{{ $panel }} p-5">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1.5">Largest files</h3>
                @foreach ($summary['largest'] as $lf)
                    <button type="button" wire:click="preview('{{ $lf['id'] }}')" class="w-full flex items-center gap-2.5 py-2 text-left {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $typeColor[$lf['type']] ?? '#9ca3af' }}"></span>
                        <span class="min-w-0 flex-1 text-[12.5px] text-gray-800 dark:text-gray-100 truncate">{{ $lf['name'] }}</span>
                        <span class="text-[12px] font-semibold text-gray-500 dark:text-gray-400">{{ $lf['size'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([
                ['Site properties', 'logo, icon and share image', url($site->name.'/properties')],
                ['Posts', 'cover images and inline media', url($site->name.'/posts')],
                ['Edit site', 'pick assets for sections', url($site->name.'/connect')],
                ['Plans', $storage['limit_h'].' storage on your plan', route('account.subscription')],
            ] as [$rl, $rd, $ru])
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
