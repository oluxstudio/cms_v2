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
{{-- gap-4 / lg:gap-6: breathing room between panes on every viewport. Slide
     positions are read from each pane's offsetLeft, so the gap never breaks
     the swipe/landing math. --}}
<div {{ $attributes->merge(['class' => 'flex-1 flex flex-row gap-4 lg:gap-6 overflow-x-auto lg:overflow-x-visible '.($count > 1 ? 'snap-x snap-mandatory' : '').' lg:snap-none no-scrollbar min-h-0 w-full max-w-[100vw] lg:max-w-none']) }}
     x-data="{ pane: {{ (int) $start }},
               slideX(i) { const el = this.$refs.panes.children[i]; return el ? el.offsetLeft : 0 },
               go(i) { this.pane = i; this.$refs.panes.scrollTo({ left: this.slideX(i), behavior: 'smooth' }) },
               sync() {
                   const x = this.$refs.panes.scrollLeft; let best = 0, dist = Infinity;
                   for (let i = 0; i < {{ (int) max($count, 1) }}; i++) {
                       const d = Math.abs(this.slideX(i) - x);
                       if (d < dist) { dist = d; best = i }
                   }
                   if (best !== this.pane) this.pane = best;
               } }"
     x-ref="panes"
     @if((int) $start > 0)
     x-init="if (window.innerWidth < 1024) {
         pane = {{ (int) $start }};
         const land = (n) => {
             const want = slideX({{ (int) $start }});
             $refs.panes.scrollLeft = want;
             // Layout may not be settled yet (want=0 or scroll clamped) — retry.
             if (n > 0 && (want === 0 || Math.abs($refs.panes.scrollLeft - want) > 2)) {
                 requestAnimationFrame(() => land(n - 1));
             }
         };
         $nextTick(() => land(30)); setTimeout(() => land(10), 200);
     }"
     @endif
     x-on:scroll="sync()"
     x-on:scrollend="sync()"
     x-on:carousel-go.window="go($event.detail?.i ?? $event.detail)">

    {{ $slot }}

    @if($count > 1)
    {{-- Floating pane switcher (mobile only) — high-contrast against the page,
         live-tracks the scroll position as the slides move. --}}
    <div class="lg:hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center gap-1.5 p-1.5 rounded-full
                bg-gray-900/95 text-white dark:bg-white/95 dark:text-gray-900
                backdrop-blur shadow-xl shadow-gray-900/30 ring-1 ring-white/15 dark:ring-gray-900/10">
        @foreach($labels as $i => $label)
            @php
                // "📊 This week" → icon on top, tiny label beneath, in a circle.
                [$navIcon, $navText] = array_pad(explode(' ', $label, 2), 2, '');
            @endphp
            <button type="button" x-on:click="go({{ $i }})"
                    class="w-16 h-16 shrink-0 rounded-full flex flex-col items-center justify-center gap-0.5 transition-colors overflow-hidden"
                    :class="pane === {{ $i }}
                        ? 'bg-indigo-500 text-white shadow'
                        : 'text-white/60 dark:text-gray-500'">
                <span class="text-base leading-none">{{ $navIcon }}</span>
                <span class="text-[9px] font-bold leading-none max-w-[3.6rem] truncate">{{ $navText }}</span>
            </button>
        @endforeach
    </div>
    @endif
</div>
