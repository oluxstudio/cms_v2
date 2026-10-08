{{--
  An item opened ON ITS OWN PAGE — the in-page counterpart of x-side-drawer /
  x-lightbox, taking the same props and slots so a screen can switch from an
  overlay to a page without touching its content:

    <x-page-panel close="closePanel" :back="route(...)" title="…">
        <x-slot:header>…</x-slot:header>   (or title / subtitle / icon)
        <x-slot:badge>…</x-slot:badge>
        …content…
        <x-slot:footer>…actions…</x-slot:footer>
    </x-page-panel>

  - `back`  : URL of the page this item belongs to ("← Back" link, wire:navigate).
  - `close` : Livewire method for the same, when there is no URL to go back to.
  - `width`/`maxWidth` are accepted for drop-in compatibility and widen the card.
--}}
@props(['close' => null, 'back' => null, 'backLabel' => 'Back', 'title' => null, 'subtitle' => null, 'icon' => null,
    'width' => null, 'maxWidth' => null, 'drawer' => false])

<section {{ $attributes->merge(['class' => 'w-full max-w-4xl mx-auto']) }}>
    <div class="mb-3">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">← {{ $backLabel }}</a>
        @elseif ($close)
            <button type="button" wire:click="{{ $close }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">← {{ $backLabel }}</button>
        @endif
    </div>

    <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm overflow-hidden">
        @if ($title || $icon || isset($header) || isset($badge))
        <header class="flex items-start gap-3 px-6 pt-5 pb-4 border-b border-gray-100 dark:border-white/[0.06]">
            @if ($icon)
                <span class="w-10 h-10 rounded-xl grid place-items-center text-lg shrink-0 bg-gray-50 dark:bg-white/[0.06]">{{ $icon }}</span>
            @endif
            <div class="min-w-0 flex-1">
                @isset($header)
                    {{ $header }}
                @else
                    <h1 class="text-lg font-bold text-gray-900 dark:text-white">{{ $title }}</h1>
                    @if ($subtitle)<p class="text-xs text-gray-400 mt-0.5">{{ $subtitle }}</p>@endif
                @endisset
            </div>
            @isset($badge)
                <div class="shrink-0">{{ $badge }}</div>
            @endisset
        </header>
        @endif

        <div class="px-6 py-5 space-y-6">{{ $slot }}</div>

        @isset($footer)
        <footer class="sticky bottom-0 px-6 py-4 bg-white dark:bg-[#1d1e2a] border-t-2" style="border-color:color-mix(in srgb, var(--primary) 45%, transparent)">
            {{ $footer }}
        </footer>
        @endisset
    </div>
</section>
