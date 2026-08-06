{{--
    Reusable ASSET PICKER — one control, two ways to fill it:
      · type/paste a URL straight into the textbox, or
      · "Assets" opens a browser over the site's asset library (public media
        API) — clicking an asset writes its URL into the bound property.

        <x-asset-picker model="nodes.3.value" :site="$site" />
        <x-asset-picker model="coverImage" :site="$site" type="" placeholder="Any asset…" />

    - `model` : the Livewire property path the URL binds to.
    - `type`  : media filter (image | video | document); '' = all assets.
    - Overlay teleports to <body>, so it works inside modals/lightboxes.
    - Pure Alpine + fetch — drop it into ANY Livewire view, no wiring needed.
--}}
@props([
    'model',
    'site',
    'type' => 'image',
    'placeholder' => 'https://… or pick from assets',
])

<div class="flex-1 min-w-[150px]"
     x-data="{
        open: false, q: '', assets: [], loading: false, loaded: false,
        async browse() { this.open = true; if (! this.loaded) await this.load(); },
        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ per_page: 60 });
                @if ($type) params.set('type', '{{ $type }}'); @endif
                if (this.q) params.set('search', this.q);
                const r = await fetch('{{ url('/api/sites/'.$site->name.'/media') }}?' + params);
                this.assets = (await r.json()).data || [];
                this.loaded = true;
            } catch (e) { this.assets = []; }
            this.loading = false;
        },
        pick(url) { $wire.set('{{ $model }}', url); this.open = false; },
     }">

    {{-- URL textbox + browse button, one row --}}
    <div class="flex gap-1.5">
        <input wire:model="{{ $model }}" type="text" placeholder="{{ $placeholder }}"
               class="flex-1 min-w-0 px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
        <button type="button" @click="browse()" title="Pick from the asset library"
                class="shrink-0 px-3 py-2 rounded-lg text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
            🖼 Assets
        </button>
    </div>

    {{-- Browser overlay — teleported so parent modals can't clip it --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4"
             @keydown.escape.window="open = false">
            <div class="lightbox-backdrop absolute inset-0 bg-black/45 backdrop-blur-[2px]" @click="open = false"></div>
            <div class="lightbox-panel relative bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">

                {{-- Header: title + live search + close --}}
                <div class="flex items-center gap-3 px-5 pt-4 pb-3 border-b border-gray-100 dark:border-white/[0.06] shrink-0">
                    <span class="w-9 h-9 rounded-xl grid place-items-center text-base shrink-0 bg-gray-50 dark:bg-white/[0.06]">🖼</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Pick an asset</h3>
                        <p class="text-[11px] text-gray-400">From this site's asset library{{ $type ? ' · '.$type.'s' : '' }}</p>
                    </div>
                    <div class="relative ml-auto w-40 sm:w-52">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" x-model="q" @input.debounce.300ms="load()" placeholder="Search assets…"
                               class="w-full pl-8 pr-2 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                    </div>
                    <button type="button" @click="open = false" title="Close (Esc)"
                            class="shrink-0 w-8 h-8 rounded-full grid place-items-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Asset grid --}}
                <div class="p-4 overflow-y-auto grow">
                    <div x-show="loading" class="py-14 text-center text-sm text-gray-400">Loading assets…</div>
                    <div x-show="! loading && assets.length === 0" x-cloak class="py-14 text-center">
                        <p class="text-sm text-gray-400">No assets found<span x-show="q"> for “<span x-text="q"></span>”</span>.</p>
                        <p class="text-xs text-gray-400 mt-1">Upload files on the Assets page — or paste a URL in the box instead.</p>
                    </div>
                    <div x-show="! loading && assets.length" class="grid grid-cols-3 sm:grid-cols-4 gap-2.5">
                        <template x-for="a in assets" :key="a.id">
                            <button type="button" @click="pick(a.url)" :title="a.name"
                                    class="group relative rounded-xl overflow-hidden border border-gray-100 dark:border-white/[0.06] bg-gray-50 dark:bg-white/[0.03] aspect-square hover:border-indigo-400 hover:ring-2 hover:ring-indigo-500/25 transition-all text-left">
                                <template x-if="a.type === 'image'">
                                    <img :src="a.url" :alt="a.alt || a.name" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                                </template>
                                <template x-if="a.type !== 'image'">
                                    <span class="absolute inset-0 grid place-items-center text-2xl" x-text="a.type === 'video' ? '🎬' : '📄'"></span>
                                </template>
                                <span class="absolute inset-x-0 bottom-0 px-1.5 py-1 text-[10px] font-semibold text-white bg-gradient-to-t from-black/70 to-transparent truncate" x-text="a.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-gray-100 dark:border-white/[0.06] shrink-0 bg-gray-50/70 dark:bg-white/[0.02]">
                    <p class="text-[11px] text-gray-400">Click an asset to use it — or close and paste any URL directly.</p>
                </div>
            </div>
        </div>
    </template>
</div>
