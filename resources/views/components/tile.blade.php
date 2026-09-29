{{--
    Stat tile — app-wide horizontal insight card (sample: tinted icon square ·
    muted label over a bold value · trend pill on the right). Colors ride the
    site theme variables; accent="ink" is the FEATURED card (solid dark ink).
    `wide` makes the tile span the full rail width in a 2-col grid.

        <x-tile label="Available Balance" value="$27,980.24" sub="+13%" accent="lime" wide
                icon="M3 12l9-9 9 9M5 10v10h14V10" href="…" />
--}}
@props([
    'label' => '',
    'value' => '',
    'sub' => null,
    'accent' => 'lime',
    'href' => null,
    'icon' => 'M3 13.5L9 7.5l4 4L21 3.5M21 3.5h-5m5 0v5M4 20h16', // default: trend line
    'wide' => false,
])

@php
    // [icon-square bg, icon color, pill bg, pill color]
    $tints = [
        'lime' => ['#dcfce7', '#15803d'],
        'lavender' => ['#ede9fe', '#6d28d9'],
        'cocoa' => ['#fef3c7', '#92400e'],
        'sky' => ['#e0f2fe', '#0369a1'],
        'rose' => ['#ffe4e6', '#be123c'],
    ];
    $featured = $accent === 'ink';
    [$iconBg, $iconFg] = $tints[$accent] ?? $tints['lime'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'rounded-2xl px-4 py-4 shadow-sm flex flex-col min-w-0 '
        .($wide ? 'col-span-full ' : '')
        .($featured ? '' : 'bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] ')
        .($href ? 'hover:shadow-md hover:-translate-y-0.5 transition-all ' : '')]) }}
    @if($featured && ! $attributes->has('style')) style="background:#1c1d29;color:#fff" @endif>

    {{-- top row: icon square + trend/status pill --}}
    <span class="flex items-center justify-between gap-2">
        <span class="shrink-0 w-10 h-10 rounded-xl grid place-items-center"
              style="{{ $featured ? 'background:rgba(255,255,255,.14);color:var(--tile-icon, var(--primary))' : "background:{$iconBg};color:{$iconFg}" }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
        </span>
        @if ($sub !== null && $sub !== '')
            <span class="shrink min-w-0 px-2.5 py-1 rounded-full text-[11px] font-bold leading-tight truncate
                         {{ $featured ? '' : 'bg-[#1c1d29] text-white dark:bg-white dark:text-gray-900' }}"
                  @if($featured) style="background:rgba(255,255,255,.92);color:#1c1d29" @endif
                  title="{{ $sub }}">{{ $sub }}</span>
        @endif
        {{ $slot }}
    </span>

    {{-- label over value --}}
    <span class="block mt-2.5 text-[13px] font-semibold truncate {{ $featured ? 'opacity-80' : 'text-gray-500 dark:text-gray-400' }}" title="{{ $label }}">{{ $label }}</span>
    <span class="font-display block mt-0.5 text-[1.55rem] leading-tight font-extrabold tracking-tight tabular-nums truncate {{ $featured ? '' : 'text-gray-900 dark:text-white' }}"
          title="{{ $value }}">{{ $value }}</span>
</{{ $tag }}>
