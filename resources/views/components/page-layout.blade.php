{{--
  Two-column shell for menu pages: a sticky LEFT stats rail + a MAIN section.
  Usage:
    <x-page-layout title="Pages">
        <x-slot:stats> ...stat tiles... </x-slot:stats>
        ...main content (list / table / grid)...
    </x-page-layout>
--}}
@props(['title' => null, 'subtitle' => null])

<div class="min-h-full flex flex-col lg:flex-row">

    {{-- ── LEFT: statistical overview ── --}}
    <aside class="left-bar w-full lg:w-[300px] shrink-0 px-5 py-6 space-y-3.5
                  lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto no-scrollbar
                  border-b lg:border-b-0 lg:border-r border-gray-200/70 dark:border-white/[0.05]">
        @if ($title)
            <div class="px-1 mb-1">
                <h2 class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h2>
                @if ($subtitle)<p class="text-xs text-gray-400 mt-0.5">{{ $subtitle }}</p>@endif
            </div>
        @endif
        {{ $stats ?? '' }}
    </aside>

    {{-- ── MAIN: the essence of the page ── --}}
    <section class="main-body flex-1 min-w-0 px-5 sm:px-6 py-6">
        {{ $slot }}
    </section>
</div>
