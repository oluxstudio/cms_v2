{{-- Compact stat card — app tile theme (warm surface, big number first,
     caption below, tinted chip). Used via x-page-layout stats slots.
     <x-stat-tile label="Total Pages" :value="$count" sub="2 published" color="#6366f1" icon="M9 12h6..." /> --}}
@props(['label', 'value', 'sub' => null, 'color' => '#6366f1', 'icon' => null, 'bar' => null])

<div class="bg-[#f2efe8] dark:bg-[#282433] rounded-[1.4rem] p-4 shadow-sm fx-in">
    <div class="flex items-start justify-between gap-2">
        <p class="text-3xl font-extrabold tracking-tight tabular-nums text-gray-900 dark:text-white leading-none">{{ $value }}</p>
        @if ($icon)
            <span class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                  style="background:color-mix(in srgb, {{ $color }} 20%, transparent); color:{{ $color }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
            </span>
        @endif
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ $label }}</p>
    @if ($sub)
        <span class="inline-block mt-2 px-2.5 py-1 rounded-full text-[11px] font-bold"
              style="background:color-mix(in srgb, {{ $color }} 20%, transparent); color:{{ $color }}">{{ $sub }}</span>
    @endif
    @isset($bar)
        <div class="h-1.5 rounded-full bg-black/[0.06] dark:bg-white/[0.08] mt-2.5 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-300" style="width:{{ max(0, min(100, (int) $bar)) }}%; background:{{ $color }}"></div>
        </div>
    @endisset
</div>
