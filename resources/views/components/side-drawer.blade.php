{{--
  Right-hand detail drawer — the house lightbox pattern, styled like the
  /connect edit-content panel: a FIXED header strip (title slot + rose ✕),
  an auto-scrolling body, and an optional FIXED footer pinned to the bottom
  (accent top border). The floating chat bubble hides while a drawer is open.

    <x-side-drawer close="close">
        <x-slot:header> …chip, title, meta… </x-slot:header>
        …scrollable content…
        <x-slot:footer> …always-visible actions (notes, save…)… </x-slot:footer>
    </x-side-drawer>
--}}
@props(['close' => 'close', 'width' => 'max-w-2xl'])

<div id="olx-drawer" class="fixed inset-0 z-40 flex justify-end" x-data x-on:keydown.escape.window="$wire.{{ $close }}()">
    <style>
        body:has(#olx-drawer) #bk-chat-fab { display: none; }
        #olx-drawer .drawer-scroll { scrollbar-width: thin; scrollbar-color: #4b5563 rgba(0,0,0,.1); }
        #olx-drawer .drawer-scroll::-webkit-scrollbar { width: 9px; }
        #olx-drawer .drawer-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,.06); }
        #olx-drawer .drawer-scroll::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 8px; border: 1.5px solid rgba(255,255,255,.55); }
    </style>

    <div class="absolute inset-0 bg-black/40" wire:click="{{ $close }}"></div>

    <aside class="relative w-full {{ $width }} h-full bg-white dark:bg-[#1d1e2a] shadow-2xl flex flex-col">
        {{-- ── Fixed header ── --}}
        <header class="shrink-0 flex items-start justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
            <div class="min-w-0 flex-1">{{ $header ?? '' }}</div>
            <button wire:click="{{ $close }}" aria-label="Close" title="Close"
                    class="fx shrink-0 w-11 h-11 rounded-full grid place-items-center text-white shadow-md hover:shadow-lg hover:scale-105 transition-transform"
                    style="background:var(--primary)">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </header>

        {{-- ── Auto-scrolling body ── --}}
        <div class="drawer-scroll flex-1 min-h-0 overflow-y-auto p-6 space-y-6">
            {{ $slot }}
        </div>

        {{-- ── Fixed footer (accent top edge) ── --}}
        @isset($footer)
        <footer class="shrink-0 px-6 py-4 bg-white dark:bg-[#1d1e2a] border-t-2" style="border-color:color-mix(in srgb, var(--primary) 45%, transparent)">
            {{ $footer }}
        </footer>
        @endisset
    </aside>
</div>
