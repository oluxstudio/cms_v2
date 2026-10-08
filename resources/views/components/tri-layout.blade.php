{{--
  Dashboard-style THREE-PANE shell — the house layout for admin pages:
  header row, then left rail (tiles) | center (main) | right rail (quick).
  Mobile: the panes become a swipe carousel landing on the center.

    <x-tri-layout title="Posts" subtitle="…" :site-name="$site->name">
        <x-slot:header> …right side of the header row (status pill…) </x-slot:header>
        <x-slot:rail>   …left tiles </x-slot:rail>
        <x-slot:quick>  …right rail (defaults to the Quick access links) </x-slot:quick>
        …center content…
    </x-tri-layout>
--}}
@props(['title' => null, 'subtitle' => null, 'siteName' => null, 'labels' => null, 'quickWidth' => 'lg:!w-[270px] xl:!w-[290px]'])

<div {{ $attributes->merge(['class' => 'min-h-full flex flex-col']) }}>

    {{-- ── Header row ── --}}
    @if ($title || isset($header))
    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-5 shrink-0">
        <div>
            @if ($title)<h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>@endif
            @if ($subtitle)<p class="mt-1 text-sm text-gray-400">{{ $subtitle }}</p>@endif
        </div>
        {{ $header ?? '' }}
    </div>
    @endif

    {{-- ── Three panes: mobile swipe carousel · desktop side-by-side ── --}}
    <x-carousel :labels="$labels ?? ['📊 Overview', '📋 '.($title ?: 'Content'), '⚡ Quick access']" :start="1">

        {{-- LEFT rail: tiles --}}
        <x-carousel.slide class="lg:!w-[25rem] lg:shrink-0 px-5 pb-24 lg:pb-6 space-y-4 max-h-full overflow-y-auto
                      lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto no-scrollbar">
            {{ $rail ?? '' }}
        </x-carousel.slide>

        {{-- CENTER: the page's essence --}}
        {{-- Every middle rail is capped at 56rem and centred (pages may cap narrower).
             Applied to the slot's top-level elements — no wrapper, so h-full
             centres (Messages) keep their height; fixed overlays are skipped. --}}
        <x-carousel.slide class="lg:flex-1 px-3 lg:px-5 pb-24 lg:pb-8 overflow-y-auto no-scrollbar main-body [&>*:not(.fixed)]:mx-auto [&>*:not(.fixed)]:max-w-[56rem]">
            {{ $slot }}
        </x-carousel.slide>

        {{-- RIGHT rail: quick access (default) or page-specific content --}}
        <x-carousel.slide class="{{ $quickWidth }} lg:shrink-0 flex flex-col px-5 pb-24 lg:pb-6 gap-3
                      max-h-full overflow-y-auto lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-9rem)] no-scrollbar">
            @isset($quick)
                {{ $quick }}
            @else
                @include('partials.quick-access', ['siteName' => $siteName])
            @endisset
        </x-carousel.slide>
    </x-carousel>
</div>
