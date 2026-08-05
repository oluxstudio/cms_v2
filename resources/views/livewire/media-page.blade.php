@php
    $tabs = [
        'all'      => ['label' => 'All',       'count' => $counts['all']],
        'image'    => ['label' => 'Images',    'count' => $counts['image']],
        'video'    => ['label' => 'Videos',    'count' => $counts['video']],
        'document' => ['label' => 'Documents', 'count' => $counts['document']],
    ];
    $typeStyles = [
        'image'    => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400',
        'video'    => 'bg-pink-100 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400',
        'document' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
    ];
@endphp

<x-page-layout title="Assets" subtitle="Images, video & documents.">
    <x-slot:stats>
        <x-stat-tile label="All files" :value="$counts['all']" :sub="$recent.' added this week'" color="#6366f1" icon="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
        <x-stat-tile label="Images" :value="$counts['image']" color="#6366f1" icon="M4 16l4.6-4.6a2 2 0 012.8 0L16 16M14 8h.01M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
        <x-stat-tile label="Videos" :value="$counts['video']" color="#ec4899" icon="M15 10l4.6-2.3A1 1 0 0121 8.6v6.8a1 1 0 01-1.4.9L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" />
        <x-stat-tile label="Documents" :value="$counts['document']" color="#f59e0b" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z" />
    </x-slot:stats>

<div
     x-data="{ toast:'', toastType:'success', copied:'' }"
     x-init="
        $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
     ">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Assets</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">{{ $counts['all'] }} files · {{ $recent }} added this week</p>
        </div>
        <button wire:click="openCreate"
                class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            Add by URL
        </button>
    </div>

    {{-- ════════ Drag & drop upload panel ════════ --}}
    <div x-data="{ over:false }"
         x-on:dragover.prevent.stop="over=true"
         x-on:dragleave.prevent.stop="over=false"
         x-on:drop.prevent.stop="over=false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles:true }))"
         :class="over ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-500/10 ring-2 ring-indigo-500/30' : 'border-gray-200 dark:border-white/[0.1]'"
         class="relative border-2 border-dashed rounded-2xl mb-6 transition-colors">

        <input type="file" wire:model="uploads" multiple x-ref="input" id="media-input"
               accept="image/*,video/*,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.ppt,.pptx,.zip"
               class="hidden">

        {{-- Whole panel is a clickable label → opens the file dialog --}}
        <label for="media-input" class="block cursor-pointer px-6 py-10 text-center" wire:loading.remove wire:target="uploads">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center">
                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            </div>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Drag &amp; drop files here</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Images, videos, and documents · up to 50&nbsp;MB each</p>
            <span class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Browse files
            </span>
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-3">Uploading to <code class="text-indigo-500">media/{{ $site->name }}/</code></p>
        </label>

        <div wire:loading.flex wire:target="uploads" class="flex-col items-center justify-center hidden px-6 py-10">
            <svg class="w-7 h-7 text-indigo-600 animate-spin mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Uploading…</p>
        </div>

        @error('uploads')   <p class="text-xs text-red-500 pb-3 text-center">{{ $message }}</p> @enderror
        @error('uploads.*') <p class="text-xs text-red-500 pb-3 text-center">{{ $message }}</p> @enderror
    </div>

    {{-- ════════ Tabs + search ════════ --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        @foreach($tabs as $key => $tab)
        <button wire:click="setTab('{{ $key }}')"
            class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors
                {{ $activeTab === $key
                    ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                    : 'bg-white dark:bg-[#1d1e2a] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08]' }}">
            {{ $tab['label'] }} <span class="opacity-60">{{ $tab['count'] }}</span>
        </button>
        @endforeach

        <div class="ml-auto flex items-center gap-2">
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search"
                              placeholder="Search media…" style="width:14rem;padding-left:2.25rem" />
            </div>
        </div>
    </div>

    {{-- ════════ Grid ════════ --}}
    @if($mediaItems->isEmpty())
    <div class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05]">
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
            <div wire:click="preview({{ $item->id }})" class="{{ $compact ? 'w-9 h-9' : 'w-12 h-12' }} rounded-lg bg-gray-100 dark:bg-white/[0.04] overflow-hidden shrink-0 cursor-pointer relative">
                @switch($item->file_type)
                    @case('image')
                        <img src="{{ $item->url }}" alt="{{ $item->alt_text ?: $item->name }}" class="w-full h-full object-cover" loading="lazy">
                        @break
                    @case('video')
                        <span class="w-full h-full flex items-center justify-center text-gray-400"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
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
                <button wire:click="preview({{ $item->id }})" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]" title="Preview">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
                <button wire:click="openEdit({{ $item->id }})" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10" title="Edit">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button wire:click="deleteMedia({{ $item->id }})" data-confirm="Delete this file? The stored file is removed too." class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10" title="Delete">
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
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        @foreach($mediaItems as $item)
        <div class="group bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
            {{-- Thumbnail (click to preview) --}}
            <div wire:click="preview({{ $item->id }})" class="aspect-square bg-gray-100 dark:bg-white/[0.04] relative cursor-pointer">
                <span class="absolute inset-0 z-10 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
                @switch($item->file_type)
                    @case('image')
                        <img src="{{ $item->url }}" alt="{{ $item->alt_text ?: $item->name }}" class="w-full h-full object-cover" loading="lazy">
                        @break
                    @case('video')
                        <video src="{{ $item->url }}" class="w-full h-full object-cover" muted preload="metadata"></video>
                        <span class="absolute inset-0 flex items-center justify-center">
                            <span class="w-10 h-10 rounded-full bg-black/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </span>
                        @break
                    @default
                        <div class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-[10px] mt-1 uppercase">{{ pathinfo($item->name, PATHINFO_EXTENSION) ?: 'file' }}</span>
                        </div>
                @endswitch
                <span class="absolute top-2 left-2 text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $typeStyles[$item->file_type] ?? '' }}">{{ ucfirst($item->file_type) }}</span>
            </div>

            {{-- Meta --}}
            <div class="p-3">
                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate" title="{{ $item->name }}">{{ $item->name }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $item->size ?: '—' }}</p>

                <div class="flex items-center gap-1 mt-2 pt-2 border-t border-gray-50 dark:border-white/[0.04]">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $item->publicUrl() }}'); copied='{{ $item->id }}'; setTimeout(()=>copied='',1500)"
                            class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                        <span x-show="copied !== '{{ $item->id }}'">Copy URL</span>
                        <span x-show="copied === '{{ $item->id }}'" x-cloak class="text-emerald-500">Copied!</span>
                    </button>
                    <button wire:click="preview({{ $item->id }})" class="text-[11px] font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Preview</button>
                    <button wire:click="openEdit({{ $item->id }})" class="text-[11px] font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Edit</button>
                    <button wire:click="deleteMedia({{ $item->id }})" data-confirm="Delete this file? The stored file is removed too." class="ml-auto text-gray-300 dark:text-gray-600 hover:text-red-500" title="Delete">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination (12 per page) --}}
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
</x-page-layout>
