{{-- Structured editor for a NESTED collection value. Expects:
       $path     dotted wire path to the value (e.g. "edit.items.3.data.facts")
       $value    the current array/object
       $fieldKey the field's key (labels)
     Renders one of: scalar list · row list (sub-cards) · group (fixed keys).
     Values failing WithNestedFields::nestedEditable must not be passed here. --}}
@php
    $isList = is_array($value) && array_is_list($value);
    $isRows = $isList && is_array($value[0] ?? null);
@endphp

@if ($isList && ! $isRows)
    {{-- scalar list: tags, questions, body paragraphs… --}}
    <div class="space-y-1">
        @foreach ($value as $j => $entry)
            <span class="flex items-center gap-1.5" wire:key="{{ $path }}-{{ $j }}">
                @if (is_string($entry) && mb_strlen($entry) > 70)
                    <textarea wire:model.blur="{{ $path }}.{{ $j }}" rows="4" class="flex-1 min-w-0 w-full mt-0.5 px-2 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 !mt-0"></textarea>
                @else
                    <input wire:model.blur="{{ $path }}.{{ $j }}" class="flex-1 min-w-0 w-full mt-0.5 px-2 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 !mt-0">
                @endif
                <button type="button" wire:click="nestedRemove('{{ $path }}', {{ $j }})"
                        class="shrink-0 w-6 h-6 rounded-lg text-[11px] text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Remove">✕</button>
            </span>
        @endforeach
        <button type="button" wire:click="nestedAdd('{{ $path }}')"
                class="text-[11px] font-semibold" style="color:var(--primary)">+ add {{ \Illuminate\Support\Str::singular(str_replace(['-', '_'], ' ', $fieldKey)) }}</button>
    </div>
@elseif ($isRows)
    {{-- row list: facts, attachments, media… --}}
    <div class="space-y-1.5">
        @foreach ($value as $j => $row)
            <div class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-1.5" wire:key="{{ $path }}-{{ $j }}">
                <div class="flex items-center justify-end gap-1 -mb-0.5">
                    @if ($j > 0)
                        <button type="button" wire:click="nestedMove('{{ $path }}', {{ $j }}, -1)" class="w-5 h-5 rounded text-[11px] text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Move up">↑</button>
                    @endif
                    @if ($j < count($value) - 1)
                        <button type="button" wire:click="nestedMove('{{ $path }}', {{ $j }}, 1)" class="w-5 h-5 rounded text-[11px] text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Move down">↓</button>
                    @endif
                    <button type="button" wire:click="nestedRemove('{{ $path }}', {{ $j }})" class="w-5 h-5 rounded text-[11px] text-gray-400 hover:text-rose-600" title="Remove">✕</button>
                </div>
                @foreach ($row as $sk => $sv)
                    <label class="block mb-1">
                        <span class="text-[10px] text-gray-400">{{ \Illuminate\Support\Str::headline((string) $sk) }}</span>
                        @if (is_string($sv) && mb_strlen($sv) > 70)
                            <textarea wire:model.blur="{{ $path }}.{{ $j }}.{{ $sk }}" rows="4" class="w-full mt-0.5 px-2 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 !mt-0"></textarea>
                        @else
                            <input wire:model.blur="{{ $path }}.{{ $j }}.{{ $sk }}" class="w-full mt-0.5 px-2 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 !mt-0">
                        @endif
                    </label>
                @endforeach
            </div>
        @endforeach
        <button type="button" wire:click="nestedAdd('{{ $path }}')"
                class="text-[11px] font-semibold" style="color:var(--primary)">+ add {{ \Illuminate\Support\Str::singular(str_replace(['-', '_'], ' ', $fieldKey)) }}</button>
    </div>
@else
    {{-- group: a fixed-shape object (cta, logo…) --}}
    <div class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-1.5">
        @foreach ((array) $value as $sk => $sv)
            <label class="block mb-1">
                <span class="text-[10px] text-gray-400">{{ \Illuminate\Support\Str::headline((string) $sk) }}</span>
                <input wire:model.blur="{{ $path }}.{{ $sk }}" class="w-full mt-0.5 px-2 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 !mt-0">
            </label>
        @endforeach
    </div>
@endif
