@props([
    'current' => 'list',
    'modes'   => ['grid', 'list', 'compact'],
])

@php
    $icons = [
        'grid' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>',
        'list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6h12M8 12h12M8 18h12M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'compact' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 9h16M4 13h16M4 17h16"/>',
    ];
    $labels = ['grid' => 'Grid', 'list' => 'List', 'compact' => 'Compact'];
@endphp

<div class="inline-flex items-center gap-0.5 p-0.5 rounded-xl bg-gray-100 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.06]">
    @foreach($modes as $mode)
        <button type="button"
                wire:click="setViewMode('{{ $mode }}')"
                title="{{ $labels[$mode] ?? ucfirst($mode) }} view"
                @class([
                    'w-8 h-8 flex items-center justify-center rounded-lg transition-colors',
                    'bg-white dark:bg-white/[0.10] text-indigo-600 dark:text-indigo-300 shadow-sm' => $current === $mode,
                    'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200' => $current !== $mode,
                ])>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                {!! $icons[$mode] ?? $icons['list'] !!}
            </svg>
        </button>
    @endforeach
</div>
