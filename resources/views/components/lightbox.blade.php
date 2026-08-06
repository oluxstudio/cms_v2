{{--
    Reusable app lightbox — ONE modal shell for every card/dialog in the app.

        <x-lightbox close="closeView" icon="🧩" :title="$name" :subtitle="$desc" max-width="max-w-2xl">
            body content…
            <x-slot:badge>…header-right chip…</x-slot>
            <x-slot:footer>…sticky footer buttons…</x-slot>
        </x-lightbox>

    - `close`  : Livewire method called by backdrop click, ✕ and Escape.
    - `header` : optional slot replacing the title/subtitle block.
    - Body scrolls on its own; header/footer stay pinned.
    - Entrance animation lives in app.css (.lightbox-backdrop / .lightbox-panel).
--}}
@props([
    'close' => null,
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'maxWidth' => 'max-w-2xl',
])

<div class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @if ($close) x-data @keydown.escape.window="$wire.{{ $close }}()" @endif>

    <div class="lightbox-backdrop absolute inset-0 bg-black/45 backdrop-blur-[2px]"
         @if ($close) wire:click="{{ $close }}" @endif></div>

    <div {{ $attributes->merge(['class' => "lightbox-panel relative bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] rounded-2xl shadow-2xl w-full {$maxWidth} max-h-[88vh] flex flex-col overflow-hidden"]) }}>

        @if ($title || $icon || isset($header))
        <div class="flex items-start gap-3 px-6 pt-5 pb-4 border-b border-gray-100 dark:border-white/[0.06] shrink-0">
            @if ($icon)
                <span class="w-10 h-10 rounded-xl grid place-items-center text-lg shrink-0 bg-gray-50 dark:bg-white/[0.06]">{{ $icon }}</span>
            @endif
            <div class="min-w-0 flex-1">
                @isset($header)
                    {{ $header }}
                @else
                    <h2 class="text-base font-bold text-gray-900 dark:text-white truncate">{{ $title }}</h2>
                    @if ($subtitle)<p class="text-xs text-gray-400 mt-0.5 truncate">{{ $subtitle }}</p>@endif
                @endisset
            </div>
            @isset($badge)
                <div class="shrink-0">{{ $badge }}</div>
            @endisset
            @if ($close)
            <button type="button" wire:click="{{ $close }}" title="Close (Esc)"
                    class="shrink-0 w-8 h-8 rounded-full grid place-items-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            @endif
        </div>
        @endif

        <div class="px-6 py-5 overflow-y-auto grow">{{ $slot }}</div>

        @isset($footer)
        <div class="px-6 py-4 border-t border-gray-100 dark:border-white/[0.06] shrink-0 bg-gray-50/70 dark:bg-white/[0.02]">
            {{ $footer }}
        </div>
        @endisset
    </div>
</div>
