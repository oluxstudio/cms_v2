{{--
  Search input — magnifier on the left, live-debounced Livewire binding,
  clear ✕ when non-empty:  <x-field.search model="search" placeholder="Search invoices…" />
--}}
@props(['model' => null, 'placeholder' => 'Search…', 'live' => true, 'label' => null, 'hint' => null])

<x-field.wrapper :label="$label" :hint="$hint" {{ $attributes->only('class') }}>
    <div class="bkf-search" x-data="{ v: @if($model) $wire.entangle('{{ $model }}') @else '' @endif }">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="search" x-model{{ $live ? '.debounce.400ms' : '' }}="v"
               placeholder="{{ $placeholder }}"
               {{ $attributes->except('class')->merge(['class' => 'bkf-input pr-9']) }}>
        <button type="button" x-show="v" x-cloak @click="v = ''"
                class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 grid place-items-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-white/[0.08]"
                aria-label="Clear search">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>
</x-field.wrapper>
