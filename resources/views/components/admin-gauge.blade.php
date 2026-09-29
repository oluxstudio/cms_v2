{{--
  Semicircle gauge (sample "Analytics" card): coloured segments along a
  half ring with the headline percentage in the middle.

    <x-admin-gauge :segments="[[62, 'var(--primary)'], [20, '#e8d48a'], [18, '#e88f8f']]" value="62%" caption="Live" />
--}}
@props(['segments' => [], 'value' => '', 'caption' => ''])

@php
    $total = max(1, array_sum(array_map(fn ($s) => max(0, $s[0]), $segments)));
    $gap = count(array_filter($segments, fn ($s) => $s[0] > 0)) > 1 ? 1.2 : 0;
    $start = 0;
@endphp

<div {{ $attributes->merge(['class' => 'relative w-full max-w-[15rem] mx-auto']) }}>
    <svg viewBox="0 0 200 110" class="w-full h-auto" role="img" aria-label="{{ $value }} {{ $caption }}">
        <path d="M 16 100 A 84 84 0 0 1 184 100" pathLength="100" fill="none"
              stroke="currentColor" class="text-gray-200 dark:text-white/10" stroke-width="16" stroke-linecap="round" />
        @foreach ($segments as [$amount, $color])
            @php $len = max(0, $amount) / $total * 100; @endphp
            @if ($len > 0)
                <path d="M 16 100 A 84 84 0 0 1 184 100" pathLength="100" fill="none"
                      stroke="{{ $color }}" stroke-width="16" stroke-linecap="round"
                      stroke-dasharray="{{ max(0.01, $len - $gap) }} 100" stroke-dashoffset="{{ -$start }}" />
                @php $start += $len; @endphp
            @endif
        @endforeach
    </svg>
    <div class="absolute inset-x-0 bottom-0 text-center">
        <p class="font-display text-[1.9rem] leading-none font-extrabold text-gray-900 dark:text-white">{{ $value }}</p>
        <p class="text-[12px] text-gray-500 dark:text-gray-400 mt-1">{{ $caption }}</p>
    </div>
</div>
