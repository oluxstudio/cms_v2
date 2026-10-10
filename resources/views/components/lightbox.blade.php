{{--
    Reusable app lightbox — ONE modal shell for every card/dialog in the app.

        <x-lightbox close="closeView" icon="🧩" :title="$name" :subtitle="$desc" max-width="max-w-2xl">
            body content…
            <x-slot:badge>…header-right chip…</x-slot>
            <x-slot:footer>…sticky footer buttons…</x-slot>
        </x-lightbox>

    - `close`  : Livewire method called by backdrop click, ✕ and Escape.
    - `header` : optional slot replacing the title/subtitle block.
    - `drawer` : render as a RIGHT-SIDE panel (item detail views) instead of a
                 centred modal — grey overlay over the page, panel slides in
                 from the right, full height. Same slots/props otherwise.
    - Body scrolls on its own; header/footer stay pinned.
    - Entrance animation lives in app.css (.lightbox-backdrop / .lightbox-panel / .lightbox-drawer).
--}}
@props([
    'close' => null,
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'maxWidth' => 'max-w-2xl',
    'drawer' => false,
])

@php
    $wrapClass = $drawer
        ? 'fixed inset-0 z-50 flex justify-end'
        : 'fixed inset-0 z-50 flex items-center justify-center p-4';
    $backdropClass = $drawer
        ? 'lightbox-backdrop absolute inset-0 bg-gray-900/40'
        : 'lightbox-backdrop absolute inset-0 bg-black/45 backdrop-blur-[2px]';
    $panelClass = $drawer
        ? "lightbox-drawer relative bg-white dark:bg-[#1d1e2a] border-l-2 border-gray-300 dark:border-white/[0.18] shadow-2xl w-full {$maxWidth} h-full flex flex-col overflow-hidden"
        : "lightbox-panel relative bg-white dark:bg-[#1d1e2a] border-2 border-gray-300 dark:border-white/[0.18] rounded-2xl shadow-2xl ring-1 ring-black/5 w-full {$maxWidth} max-h-[88vh] flex flex-col overflow-hidden";
@endphp

<div class="{{ $wrapClass }}"
     @if ($close) x-data @keydown.escape.window="$wire.{{ $close }}()" @endif>

    <div class="{{ $backdropClass }}"
         @if ($close) wire:click="{{ $close }}" @endif></div>

    <div {{ $attributes->merge(['class' => $panelClass]) }}>

        @if ($title || $icon || isset($header))
        {{-- Header: tinted with the theme colour, accent bar on top, bold title --}}
        <div class="relative flex items-start gap-3 px-6 pt-5 pb-4 border-b-2 border-gray-200 dark:border-white/[0.12] shrink-0"
             style="background: color-mix(in srgb, var(--primary) 9%, transparent)">
            <span class="absolute inset-x-0 top-0 h-1" style="background: var(--primary)" aria-hidden="true"></span>
            @if ($icon)
                <span class="w-11 h-11 rounded-xl grid place-items-center text-xl shrink-0 bg-white dark:bg-[#1d1e2a] border-2 shadow-sm"
                      style="border-color: color-mix(in srgb, var(--primary) 45%, transparent)">{{ $icon }}</span>
            @endif
            <div class="min-w-0 flex-1">
                @isset($header)
                    {{ $header }}
                @else
                    <h2 class="text-lg font-extrabold tracking-tight text-gray-900 dark:text-white truncate">{{ $title }}</h2>
                    @if ($subtitle)<p class="text-[13px] text-gray-600 dark:text-gray-300 mt-0.5">{{ $subtitle }}</p>@endif
                @endisset
            </div>
            @isset($badge)
                <div class="shrink-0">{{ $badge }}</div>
            @endisset
            @if ($close)
            <button type="button" wire:click="{{ $close }}" title="Close (Esc)"
                    aria-label="Close"
                    class="shrink-0 w-9 h-9 rounded-full grid place-items-center border-2 border-gray-300 dark:border-white/[0.2] bg-white dark:bg-[#1d1e2a] text-gray-800 dark:text-gray-100 shadow-sm hover:text-white hover:border-transparent hover:bg-[var(--primary)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)] transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            @endif
        </div>
        @endif

        <div class="px-6 py-5 overflow-y-auto grow">{{ $slot }}</div>

        @isset($footer)
        <div class="px-6 py-4 border-t-2 border-gray-200 dark:border-white/[0.12] shrink-0 bg-gray-50/70 dark:bg-white/[0.02]">
            {{ $footer }}
        </div>
        @endisset
    </div>
</div>
