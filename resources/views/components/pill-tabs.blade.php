{{--
  Pill tabs that never hide a tab: the strip scrolls sideways (swipe, trackpad,
  mouse wheel) and arrow buttons appear at whichever end has more tabs, with a
  soft fade. The active tab scrolls itself into view. Selection lives in the
  PARENT Alpine scope (default variable `tab`), so the page keeps control:

    <div x-data="{ tab: 'brand' }">
        <x-pill-tabs :tabs="['brand' => 'Brand', 'hours' => 'Hours']" :dots="['hours']" />
        <section x-show="tab === 'brand'">…</section>
    </div>

  - tabs: key => label
  - state: name of the parent Alpine variable holding the active key (default "tab")
  - dots: keys that get a red dot (e.g. tabs with validation errors)
--}}
@props(['tabs' => [], 'state' => 'tab', 'dots' => []])

@php $dots = array_flip((array) $dots); @endphp
<div {{ $attributes->merge(['class' => 'relative mb-5']) }}
     x-data="{
        canL: false, canR: false,
        upd() { const s = this.$refs.strip; if (! s) return; this.canL = s.scrollLeft > 4; this.canR = s.scrollLeft + s.clientWidth < s.scrollWidth - 4 },
        by(dir) { this.$refs.strip.scrollBy({ left: dir * Math.max(120, this.$refs.strip.clientWidth * 0.6), behavior: 'smooth' }) },
        reveal() { this.$nextTick(() => { const a = this.$refs.strip.querySelector('[aria-selected=true]'); if (a) a.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' }) }) },
     }"
     x-init="$nextTick(() => { upd(); reveal() }); new ResizeObserver(() => upd()).observe($refs.strip); $watch('{{ $state }}', () => reveal())">

    <div x-ref="strip" role="tablist"
         @scroll.passive="upd()"
         @wheel="if ((canL || canR) && Math.abs($event.deltaY) > Math.abs($event.deltaX)) { $event.preventDefault(); $refs.strip.scrollLeft += $event.deltaY }"
         class="flex gap-1 p-1 rounded-full bg-white/70 dark:bg-white/[0.05] shadow-sm overflow-x-auto no-scrollbar scroll-smooth"
         :style="`mask-image: linear-gradient(to right, ${canL ? 'transparent, #000 2.75rem' : '#000'}, ${canR ? '#000 calc(100% - 2.75rem), transparent' : '#000'}); -webkit-mask-image: linear-gradient(to right, ${canL ? 'transparent, #000 2.75rem' : '#000'}, ${canR ? '#000 calc(100% - 2.75rem), transparent' : '#000'})`">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" @click="{{ $state }} = @js($key)" :aria-selected="{{ $state }} === @js($key)"
                    class="shrink-0 inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-semibold whitespace-nowrap transition-colors"
                    :class="{{ $state }} === @js($key) ? 'shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
                    :style="{{ $state }} === @js($key) ? 'background:var(--foreground);color:var(--background)' : ''">
                {{ $label }}
                @if (isset($dots[$key]))<span class="w-2 h-2 rounded-full bg-rose-500" aria-label="needs attention"></span>@endif
            </button>
        @endforeach
    </div>

    {{-- Arrows: only when there's more in that direction --}}
    <button type="button" x-show="canL" x-cloak x-transition.opacity @click="by(-1)" aria-label="Scroll tabs left"
            class="absolute left-0.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full grid place-items-center shadow-md
                   bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border border-gray-100 dark:border-white/10 hover:scale-105 transition-transform">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <button type="button" x-show="canR" x-cloak x-transition.opacity @click="by(1)" aria-label="Scroll tabs right"
            class="absolute right-0.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full grid place-items-center shadow-md
                   bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border border-gray-100 dark:border-white/10 hover:scale-105 transition-transform">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </button>
</div>
