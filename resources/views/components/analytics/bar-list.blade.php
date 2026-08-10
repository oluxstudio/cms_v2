@props(['items'])
@php
    // $items: [label => count], assumed already sorted desc.
    $max = max(1, ...(count($items) ? array_values($items) : [1]));
@endphp

<div class="space-y-2">
    @foreach ($items as $label => $count)
        <div class="relative flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 overflow-hidden">
            <div class="absolute inset-y-0 left-0 rounded-lg bg-indigo-500/10 dark:bg-indigo-400/15"
                 style="width: {{ max(4, round($count / $max * 100)) }}%"></div>
            <span class="relative z-10 text-sm text-gray-700 dark:text-gray-200 truncate">{{ $label }}</span>
            <span class="relative z-10 text-sm font-semibold text-gray-900 dark:text-white shrink-0">{{ number_format($count) }}</span>
        </div>
    @endforeach
</div>
