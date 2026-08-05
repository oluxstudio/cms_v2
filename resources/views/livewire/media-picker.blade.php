{{-- Reusable media-picker modal — open with $dispatch('open-media-picker', { context }) --}}
<div>
@if($open)
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-8"
         x-data x-on:keydown.escape.window="$wire.close()">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="close"></div>

        {{-- Panel --}}
        <div class="relative w-full max-w-3xl max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-[#1e1f2b] border border-gray-200 dark:border-white/[0.08] shadow-2xl overflow-hidden">

            {{-- Header: title + search + type tabs + close --}}
            <div class="shrink-0 p-4 border-b border-gray-100 dark:border-white/[0.06] space-y-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Media library</h3>
                    <div class="ml-auto inline-flex items-center gap-0.5 p-0.5 rounded-lg bg-gray-100 dark:bg-white/[0.05] text-[11px] font-semibold">
                        @foreach(['image' => 'Images', 'video' => 'Videos', 'document' => 'Docs', 'all' => 'All'] as $k => $lbl)
                            <button wire:click="setType('{{ $k }}')"
                                    class="px-2.5 py-1 rounded-md transition-colors {{ $type === $k ? 'bg-white dark:bg-white/[0.12] text-indigo-600 dark:text-indigo-300 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">{{ $lbl }}</button>
                        @endforeach
                    </div>
                    <button wire:click="close" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]" title="Close">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name or alt text…"
                               class="w-full pl-9 pr-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    </div>
                    {{-- In-place upload --}}
                    <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        <span wire:loading.remove wire:target="uploads">Upload</span>
                        <span wire:loading wire:target="uploads">Uploading…</span>
                        <input type="file" wire:model="uploads" multiple class="hidden">
                    </label>
                </div>
                @error('uploads.*') <p class="text-[11px] text-rose-500">{{ $message }}</p> @enderror
            </div>

            {{-- Grid --}}
            <div class="flex-1 overflow-y-auto p-4"
                 x-data="{ dragging: false }"
                 x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false"
                 x-on:drop.prevent="dragging = false; $wire.uploadMultiple('uploads', Array.from($event.dataTransfer.files))"
                 :class="dragging ? 'ring-2 ring-inset ring-indigo-500 rounded-xl' : ''">
                <div wire:loading.delay wire:target="search,setType,pick,uploads" class="text-[11px] text-gray-400 mb-2">Loading…</div>
                @if($items->isEmpty())
                    <div class="py-14 text-center">
                        <p class="text-sm text-gray-400">No {{ $type === 'all' ? 'media' : Str::plural($type) }} found{{ $search ? ' for “'.$search.'”' : '' }}.</p>
                        <p class="text-xs text-gray-400 mt-1">Drop files anywhere in this panel, or use the Upload button.</p>
                    </div>
                @else
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                        @foreach($items as $m)
                            <button wire:key="pick-{{ $m->id }}" wire:click="pick({{ $m->id }})" type="button"
                                    class="group relative aspect-square rounded-xl overflow-hidden border border-gray-200 dark:border-white/[0.08] bg-gray-50 dark:bg-white/[0.03] hover:ring-2 hover:ring-indigo-500 transition-shadow text-left"
                                    title="{{ $m->name }} ({{ $m->size }})">
                                @if($m->file_type === 'image')
                                    <img src="{{ $m->publicUrl() }}" alt="{{ $m->alt_text ?? $m->name }}" loading="lazy" class="w-full h-full object-cover">
                                @else
                                    <span class="w-full h-full flex flex-col items-center justify-center gap-1 text-gray-400">
                                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                            @if($m->file_type === 'video')<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-2.72A.75.75 0 0121.75 8.4v7.2a.75.75 0 01-1.28.62l-4.72-2.72m-9-6h7.5A2.25 2.25 0 0116.5 9.75v4.5a2.25 2.25 0 01-2.25 2.25h-7.5A2.25 2.25 0 014.5 14.25v-4.5A2.25 2.25 0 016.75 7.5z"/>
                                            @else<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m6.75 3H9M21 18.75V16.5M4.5 21h15a.75.75 0 00.75-.75V5.25L15.75 1.5H5.25a.75.75 0 00-.75.75v18c0 .414.336.75.75.75z"/>@endif
                                        </svg>
                                        <span class="text-[9px] font-semibold uppercase">{{ $m->file_type }}</span>
                                    </span>
                                @endif
                                <span class="absolute inset-x-0 bottom-0 px-1.5 py-1 bg-gray-900/70 text-white text-[9px] truncate opacity-0 group-hover:opacity-100 transition-opacity">{{ $m->name }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Footer: pagination + hint --}}
            <div class="shrink-0 px-4 py-3 border-t border-gray-100 dark:border-white/[0.06] flex items-center gap-3">
                <p class="text-[10px] text-gray-400">Tip: you can also type <code class="font-mono text-[10px] bg-gray-100 dark:bg-white/[0.06] px-1 py-0.5 rounded">@media/filename</code> directly in any field.</p>
                <div class="ml-auto text-xs">{{ $items->links('pagination::simple-tailwind') }}</div>
            </div>
        </div>
    </div>
@endif
</div>
