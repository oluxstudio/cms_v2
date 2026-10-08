{{-- One nested value's input, chosen by what the value IS (MediaValue::kind):
     yes/no → checkbox · number → number · asset → picker + preview · long text → textarea · else input.
     Vars: $path (wire path), $value, $siteId. --}}
@php
    // A declared sub-field type ($leafDef, from @olux-field media.type …) wins over the guess.
    $leafDef ??= null;
    $leafType = $leafDef['type'] ?? null;
    $leafKind = \App\Support\MediaValue::kind($value, $siteId ?? null);
    $leafMedia = $leafKind === 'media' ? \App\Support\MediaValue::detect($value, $siteId ?? null) : null;
    $leafCls = 'w-full px-2 py-1.5 text-xs rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100';
@endphp
@if ($leafType === 'select' && ($leafOpts = \App\Support\CollectionFieldOptions::for($siteId ?? null, $leafDef ?? [])) !== [])
    <select wire:model.live="{{ $path }}" class="{{ $leafCls }}">
        <option value="">—</option>
        @foreach ($leafOpts as $val => $lab)
            <option value="{{ $val }}">{{ empty($leafDef['optionsFrom']) ? ucfirst($lab) : $lab }}</option>
        @endforeach
    </select>
@elseif (in_array($leafType, ['image', 'media'], true))
    @php $lm = filled($value) ? \App\Support\MediaValue::detect($value, $siteId ?? null) : null; @endphp
    <div class="flex items-center gap-1.5">
        @if ($lm && $lm['kind'] === 'image')
            <img src="{{ $lm['url'] }}" alt="" class="w-9 h-9 rounded-md object-cover border border-gray-100 dark:border-white/[0.08] shrink-0" onerror="this.style.visibility='hidden'">
        @elseif ($lm)
            <span class="w-9 h-9 rounded-md bg-gray-100 dark:bg-white/[0.06] text-[9px] font-bold uppercase text-gray-500 flex items-center justify-center shrink-0">{{ $lm['kind'] === 'document' ? 'file' : $lm['kind'] }}</span>
        @endif
        <input wire:model.live.debounce.500ms="{{ $path }}" class="{{ $leafCls }} min-w-0" placeholder="{{ $leafType === 'image' ? 'Choose an image' : 'Choose an audio or video file' }}">
        <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'nested-media', path: '{{ $path }}'{!! $leafType === 'image' ? ", type: 'image'" : '' !!} } })"
                class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1]" title="Choose from Assets">Assets</button>
    </div>
@elseif ($leafType === 'datetime')
    <input type="datetime-local" wire:model.blur="{{ $path }}" class="{{ $leafCls }}">
@elseif (in_array($leafType, ['url', 'email', 'tel', 'date', 'number'], true))
    {{-- a declared typed sub-field: link / email / phone / date / number input --}}
    <input type="{{ $leafType }}" wire:model.blur="{{ $path }}" class="{{ $leafCls }}"
           @if ($leafType === 'url') placeholder="https://…" @elseif ($leafType === 'number') step="any" @endif>
@elseif ($leafType && \App\Models\Collection::textareaRows($leafType))
    <textarea wire:model.blur="{{ $path }}" rows="{{ \App\Models\Collection::textareaRows($leafType) }}" class="{{ $leafCls }}"></textarea>
@elseif ($leafKind === 'boolean')
    <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-200 py-1">
        <input type="checkbox" wire:model="{{ $path }}" class="w-4 h-4 accent-[var(--primary)]"> Yes
    </label>
@elseif ($leafKind === 'number')
    <input type="number" step="any" wire:model.blur="{{ $path }}" class="{{ $leafCls }}">
@elseif ($leafKind === 'media')
    <div class="flex items-center gap-1.5">
        @if ($leafMedia['kind'] === 'image')
            <img src="{{ $leafMedia['url'] }}" alt="" class="w-9 h-9 rounded-md object-cover border border-gray-100 dark:border-white/[0.08] shrink-0" onerror="this.style.visibility='hidden'">
        @else
            <span class="w-9 h-9 rounded-md bg-gray-100 dark:bg-white/[0.06] text-[9px] font-bold uppercase text-gray-500 flex items-center justify-center shrink-0">{{ $leafMedia['kind'] === 'document' ? 'file' : $leafMedia['kind'] }}</span>
        @endif
        <input wire:model.live.debounce.500ms="{{ $path }}" class="{{ $leafCls }} min-w-0">
        <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'nested-media', path: '{{ $path }}', type: '{{ $leafMedia['kind'] === 'document' ? 'document' : $leafMedia['kind'] }}' } })"
                class="shrink-0 px-2 py-1.5 rounded-lg text-[11px] font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1]" title="Choose from Assets">Assets</button>
    </div>
@elseif ($leafKind === 'long-text')
    <textarea wire:model.blur="{{ $path }}" rows="4" class="{{ $leafCls }}"></textarea>
@else
    <input wire:model.blur="{{ $path }}" class="{{ $leafCls }}">
@endif
