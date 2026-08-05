{{--
    Stat tile — the app-wide insight-card format: warm cream surface, big
    display number, muted caption underneath and a colored accent chip
    (lime / lavender / cocoa / sky). accent="ink" flips the whole card to the
    dark plum hero style. Pass href to make the tile a link.

        <x-tile label="Total visits" :value="$n" sub="across all posts" accent="lime" />
--}}
@props([
    'label' => '',
    'value' => '',
    'sub' => null,
    'accent' => 'lime',
    'href' => null,
])

@php
    $chips = [
        'lime' => 'background:#d9f068;color:#2b3110',
        'lavender' => 'background:#d7c3f5;color:#33245c',
        'cocoa' => 'background:#e6d6c6;color:#4a3628',
        'sky' => 'background:#bfdcf7;color:#173a5e',
        'ink' => 'background:#d9f068;color:#2b3110', // chip ON an ink card
    ];
    $ink = $accent === 'ink';
    $card = $ink
        ? 'bg-[#332433] text-white'
        : 'bg-[#f2efe8] dark:bg-[#282433] text-gray-900 dark:text-white';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => "$card rounded-[1.6rem] p-5 shadow-sm ".($href ? 'hover:shadow-md hover:-translate-y-0.5 transition-all ' : '').'flex flex-col']) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="text-[2.4rem] leading-none font-extrabold tracking-tight tabular-nums">{{ $value }}</p>
        {{ $slot }}
    </div>
    <p class="text-xs mt-2 {{ $ink ? 'text-white/60' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</p>
    @if ($sub !== null && $sub !== '')
        <span class="self-start mt-3 px-2.5 py-1 rounded-full text-[11px] font-bold" style="{{ $chips[$accent] ?? $chips['lime'] }}">{{ $sub }}</span>
    @endif
</{{ $tag }}>
