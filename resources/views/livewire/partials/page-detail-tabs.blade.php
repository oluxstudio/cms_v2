{{-- Page details tabs: one card, three segments with an icon, a hint and a live badge. --}}
@php
    $items = [
        'edit' => ['Page', 'Name, address & visibility', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', null],
        'meta' => ['Page attributes & meta tags', 'Search, social & custom tags', 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', $seoOk.'/'.$seoTotal],
        'sources' => ['Sources', 'Collections, posts & products', 'M4 7v10c0 2 3.6 3 8 3s8-1 8-3V7M4 7c0 2 3.6 3 8 3s8-1 8-3M4 7c0-2 3.6-3 8-3s8 1 8 3m0 5c0 2-3.6 3-8 3s-8-1-8-3', $sources ?: null],
    ];
@endphp
<div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-1.5 grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)_minmax(0,1fr)] gap-1.5" role="tablist">
    @foreach ($items as $key => [$label, $hint, $icon, $badge])
        @php $on = $tab === $key; @endphp
        <button type="button" wire:click="setTab('{{ $key }}')" role="tab" aria-selected="{{ $on ? 'true' : 'false' }}"
                class="relative flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-left transition-colors
                       {{ $on ? 'shadow-sm' : 'hover:bg-gray-50 dark:hover:bg-white/[0.04]' }}"
                @if ($on) style="background:color-mix(in srgb, var(--primary) 10%, transparent); box-shadow: inset 0 0 0 1.5px var(--primary)" @endif>
            <span class="relative w-9 h-9 shrink-0 rounded-lg grid place-items-center {{ $on ? '' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400' }}"
                  @if ($on) style="background:var(--primary);color:var(--on-primary)" @endif>
                <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                @if ($badge)
                    <span class="absolute -top-1.5 -right-2 px-1.5 py-px rounded-full text-[9.5px] font-bold leading-tight bg-gray-900 text-white dark:bg-white dark:text-gray-900 ring-2 ring-white dark:ring-[#1d1e2a]">{{ $badge }}</span>
                @endif
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-[13px] font-bold leading-tight truncate {{ $on ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-200' }}" title="{{ $label }}">{{ $label }}</span>
                <span class="block text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $hint }}</span>
            </span>
        </button>
    @endforeach
</div>
