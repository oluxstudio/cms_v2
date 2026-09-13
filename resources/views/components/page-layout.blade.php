{{--
  Two-column shell for menu pages: a sticky LEFT stats rail + a MAIN section.
  Usage:
    <x-page-layout title="Pages">
        <x-slot:stats> ...stat tiles... </x-slot:stats>
        ...main content (list / table / grid)...
    </x-page-layout>
--}}
@props(['title' => null, 'subtitle' => null])

<div class="min-h-full flex flex-col">
    {{-- Mobile: swipe between Overview (stats) and the main content, landing
         on the content. Desktop (lg+): the classic side-by-side layout. --}}
    <x-carousel :labels="['📊 Overview', '📋 '.($title ?: 'Content')]" :start="1">

        {{-- ── LEFT: statistical overview ── --}}
        <x-carousel.slide class="left-bar lg:!w-[300px] lg:shrink-0 px-5 py-6 pb-24 lg:pb-6 space-y-3.5
                      max-h-full overflow-y-auto
                      lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto no-scrollbar
                      lg:border-r border-gray-200/70 dark:border-white/[0.05]">
            @if ($title)
                <div class="px-1 mb-4">
                    <h2 class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h2>
                    @if ($subtitle)<p class="text-xs font-medium text-gray-600 dark:text-gray-300 mt-0.5">{{ $subtitle }}</p>@endif
                </div>
            @endif
            {{ $stats ?? '' }}
        </x-carousel.slide>

        {{-- ── MAIN: the essence of the page, in a centered column ── --}}
        <x-carousel.slide class="main-body lg:flex-1 px-5 sm:px-6 py-6 pb-24 lg:pb-6 max-h-full overflow-y-auto lg:overflow-y-visible no-scrollbar">
            <div class="max-w-[50rem] mx-auto">
                {{ $slot }}
            </div>
        </x-carousel.slide>
    </x-carousel>
</div>
