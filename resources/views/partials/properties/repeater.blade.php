{{-- Repeating rows (phones, emails, closures, accreditations). Vars: $key, $r (def), $rows, $site, $panel. --}}
@php
    $ghost = 'fx inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 hover:border-gray-400';
    $list = $rows[$key] ?? [];
@endphp
<div class="{{ $panel }} p-6">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="text-[16px] font-bold text-gray-900 dark:text-white">{{ $r['title'] }}</h2>
            @if (! empty($r['help']))<p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $r['help'] }}</p>@endif
        </div>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.08] text-gray-600 dark:text-gray-300 shrink-0">{{ count($list) }}</span>
    </div>
    <div class="space-y-3">
        @forelse ($list as $i => $row)
            <div class="flex flex-wrap @xl:flex-nowrap items-start gap-2 rounded-2xl @xl:rounded-none bg-gray-50/60 @xl:bg-transparent dark:bg-white/[0.02] @xl:dark:bg-transparent p-3 @xl:p-0" wire:key="{{ $key }}-{{ $i }}">
                @foreach ($r['fields'] as $sub => $sf)
                    <div class="{{ $sf['input'] === 'image' ? 'w-full @xl:w-auto @xl:flex-[2]' : 'w-full @xl:flex-1' }} min-w-0">
                        @include('partials.properties.field', [
                            'f' => $sf + ['display' => $sf['label']], 'model' => "rows.$key.$i.$sub", 'upload' => "rows.$key.$i.$sub",
                            'value' => $row[$sub] ?? '', 'hideLabel' => $i > 0 && $sf['input'] !== 'image',
                        ])
                    </div>
                @endforeach
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" title="Remove"
                        class="w-8 h-8 grid place-items-center rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1d1e2a] text-gray-500 hover:text-rose-500 shrink-0 {{ $i === 0 ? '@xl:mt-5' : '' }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @empty
            <p class="text-sm text-gray-400 py-1">None yet.</p>
        @endforelse
    </div>
    <button type="button" wire:click="addRow('{{ $key }}')" class="{{ $ghost }} mt-4">{{ $r['add'] }}</button>
</div>
