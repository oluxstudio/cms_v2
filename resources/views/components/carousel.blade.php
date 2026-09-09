{{--
    Mobile pane carousel — the house layout for multi-pane admin pages.
    On phones each <x-carousel.slide> is one device-width swipe; a floating
    pill switcher tracks/moves between panes. On lg+ the slides lay out
    side-by-side exactly as before (pass desktop widths on each slide).

    Props: :labels  pill labels (one per slide; ≤1 → no pills, no snapping)
           :start   slide index to land on when the page opens (mobile only)
--}}
@props(['labels' => [], 'start' => 0])
@php $count = count($labels); @endphp
<div {{ $attributes->merge(['class' => 'flex-1 flex flex-row overflow-x-auto lg:overflow-x-visible '.($count > 1 ? 'snap-x snap-mandatory' : '').' lg:snap-none no-scrollbar min-h-0 w-full max-w-[100vw] lg:max-w-none']) }}
     x-data="{ pane: {{ (int) $start }},
               go(i) { this.$refs.panes.scrollTo({ left: i * this.$refs.panes.clientWidth, behavior: 'smooth' }) },
               sync() { this.pane = Math.round(this.$refs.panes.scrollLeft / this.$refs.panes.clientWidth) } }"
     x-ref="panes"
     @if((int) $start > 0)
     x-init="if (window.innerWidth < 1024) {
         const land = () => { $refs.panes.scrollLeft = $refs.panes.clientWidth * {{ (int) $start }}; pane = {{ (int) $start }} };
         $nextTick(land); requestAnimationFrame(land); setTimeout(land, 120);
     }"
     @endif
     x-on:scroll.debounce.100ms="sync()"
     x-on:carousel-go.window="go($event.detail?.i ?? $event.detail)">

    {{ $slot }}

    @if($count > 1)
    {{-- Floating pane switcher (mobile only) --}}
    <div class="lg:hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-40 flex gap-1 p-1 rounded-full
                bg-white/85 dark:bg-[#1d1e2a]/85 backdrop-blur border border-gray-200 dark:border-white/[0.1] shadow-lg">
        @foreach($labels as $i => $label)
            <button type="button" x-on:click="go({{ $i }})"
                    class="px-3 py-1.5 rounded-full text-[11px] font-bold transition-colors"
                    :class="pane === {{ $i }} ? 'bg-indigo-600 text-white' : 'text-gray-500 dark:text-gray-300'">{{ $label }}</button>
        @endforeach
    </div>
    @endif
</div>
