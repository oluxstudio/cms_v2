{{-- Collection entry editor (fields from the schema). Vars: $viewing (collection). Used by the
     Collections lightbox and the collection's own page.
     Optional: $oneColumn (fields stacked), $rich (text fields = textarea with an Aa/Rich editor —
     the page must include livewire.partials.olx-rich-assets), $bare (no tinted wrapper / heading),
     $orderedFields (the schema in display order — the collection page passes view-mode order). --}}
@php
    $oneColumn = $oneColumn ?? false;
    $rich = $rich ?? false;
    $bare = $bare ?? false;
@endphp
@if($editingItemId !== null)
<div class="{{ $bare ? '' : 'p-5 border-b border-gray-100 dark:border-white/[0.05] bg-gray-50/70 dark:bg-white/[0.02]' }}">
    @unless ($bare)<p class="text-xs font-bold uppercase tracking-[.12em] text-gray-400 mb-3">{{ $editingItemId ? 'Edit entry' : 'New entry' }}</p>@endunless
    <div class="grid {{ $oneColumn ? 'grid-cols-1' : 'sm:grid-cols-2' }} gap-3">
        @foreach(($orderedFields ?? $viewing->fields ?? []) as $f)
        {{-- Hidden fields stay out of the form (their value is kept as it is). --}}
        @continue(! empty($f['hidden']))
        @if(\App\Support\CollectionAutoFields::valid($f['auto'] ?? null))
            {{-- Filled by the system — shown, never typed in. --}}
            @php $autoNow = $itemForm[$f['key']] ?? ''; @endphp
            <div class="min-w-0 rounded-lg border border-dashed border-gray-300 dark:border-white/15 bg-gray-50 dark:bg-white/[0.03] p-3">
                <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $f['label'] ?? $f['key'] }}</p>
                <p class="mt-1 text-[13px] text-gray-700 dark:text-gray-200 break-words">
                    {{ is_scalar($autoNow) && $autoNow !== '' ? \App\Support\SiteTokens::apply($site, (string) $autoNow) : 'Filled in when you save' }}
                </p>
                <p class="mt-1 text-[11px] text-gray-400">Filled automatically · {{ \App\Support\CollectionAutoFields::label($f['auto']) }}</p>
            </div>
            @continue
        @endif
        @php
            $key = $f['key']; $ftype = $f['type'] ?? 'text';
            $isJson = in_array($key, $itemJsonKeys ?? [], true);
            $isNested = ! $isJson && is_array($itemForm[$key] ?? null);
            // Media: a media-type field, or any field currently holding an asset path/URL.
            $mediaNow = (! $isJson && ! $isNested) ? \App\Support\MediaValue::detect($itemForm[$key] ?? null, $viewing->site_id) : null;
            $isMedia = ! $isJson && ! $isNested && ($mediaNow || \App\Support\MediaValue::isMediaType($ftype, $key));
            // A one-line value would lose its line breaks in an <input> — multi-line values always get a textarea.
            $multiLine = is_string($itemForm[$key] ?? null) && str_contains($itemForm[$key], "\n");
            // textarea2 2 rows · textarea 4 · textarea6 6 · textarea10 10 · textarea15 15
            $taRows = \App\Models\Collection::textareaRows($ftype);
        @endphp
        {{-- Field card (same pattern as the Edit page's field cards): gray-500 border, primary while focused. --}}
        <div class="{{ ! $oneColumn && ($taRows || $isJson || $isNested) ? 'sm:col-span-2' : '' }} min-w-0 rounded-lg border border-gray-200 dark:border-gray-200 bg-white dark:bg-[#1d1e2a] p-3 transition-colors focus-within:border-[var(--primary)]">
            <div class="flex items-center gap-1.5 mb-1.5">
                <span class="flex-1 text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $f['label'] ?? $key }}@if(! empty($f['required']))<span class="text-rose-500" title="Required"> *</span>@endif @if($isJson) <span class="opacity-70">· list (JSON)</span>@endif <span class="font-normal text-gray-400">({{ $ftype ?: 'text' }})</span></span>
            </div>
            @if($isNested)
                @include('livewire.partials.nested-field', ['path' => "itemForm.$key", 'value' => $itemForm[$key], 'fieldKey' => $key, 'siteId' => $viewing->site_id, 'def' => $f])
            @elseif($isJson)
                <textarea wire:model="itemForm.{{ $key }}" rows="4" spellcheck="false" class="w-full px-3 py-2 text-xs font-mono rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 resize-y"></textarea>
            @elseif($isMedia)
                {{-- Asset field: pick from Assets (or paste a path/URL) — previewed below --}}
                <div class="flex items-center gap-2">
                    <input wire:model.live.debounce.500ms="itemForm.{{ $key }}" type="text" list="media-url-options"
                           placeholder="Pick from Assets, or paste a path / URL"
                           class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'collection-item', key: '{{ $key }}' } })"
                            class="shrink-0 px-3 py-2 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]"
                            title="Choose from the asset library">Assets</button>
                </div>
                @if ($mediaNow)
                    <div class="mt-2">@include('livewire.partials.media-preview', ['media' => $mediaNow, 'size' => 'sm'])</div>
                @endif
            @elseif($taRows || $multiLine)
                {{-- Textarea (rows from its type); on the collection page also an Aa / Rich editor --}}
                @if ($rich)
                    <div wire:key="ifr-{{ $editingItemId ?: 'new' }}-{{ $key }}">
                        @include('livewire.partials.rich-text', ['path' => "itemForm.$key", 'value' => $itemForm[$key] ?? '', 'rows' => $taRows ?? 4])
                    </div>
                @else
                    <textarea wire:model="itemForm.{{ $key }}" rows="{{ $taRows ?? 4 }}" class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 resize-y"></textarea>
                @endif
            @elseif(in_array($ftype, ['select', 'radio'], true) && ($opts = \App\Support\CollectionFieldOptions::for($viewing->site_id, $f)) !== [])
                @if ($ftype === 'radio')
                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 pt-1">
                        @foreach($opts as $val => $lab)
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">
                                <input type="radio" wire:model="itemForm.{{ $key }}" value="{{ $val }}" class="accent-[var(--primary)]"> {{ $lab }}
                            </label>
                        @endforeach
                    </div>
                @else
                    <select wire:model="itemForm.{{ $key }}" class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 pr-7">
                        <option value="">—</option>
                        @foreach($opts as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach
                    </select>
                @endif
            @elseif($ftype === 'toggle')
                <div class="pt-1"><x-field.toggle model="itemForm.{{ $key }}" :text="$f['help'] ?? 'On'" /></div>
            @elseif($ftype === 'checkbox')
                <label class="inline-flex items-center gap-2 pt-1 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">
                    <input type="checkbox" wire:model="itemForm.{{ $key }}" class="w-4 h-4 accent-[var(--primary)]"> {{ $f['help'] ?? 'Yes' }}
                </label>
            @elseif($ftype === 'slider')
                @php $smin = $f['min'] ?? 0; $smax = $f['max'] ?? 100; $sstep = $f['step'] ?? 1; @endphp
                <div class="flex items-center gap-3" x-data="{ v: $wire.entangle('itemForm.{{ $key }}') }">
                    <input type="range" min="{{ $smin }}" max="{{ $smax }}" step="{{ $sstep }}" x-model="v" class="flex-1 accent-[var(--primary)]">
                    <span class="w-14 text-right text-sm font-bold text-gray-800 dark:text-gray-100" x-text="v === '' || v === null ? '—' : v"></span>
                </div>
            @else
                {{-- Single-line input: text, number (decimals allowed), email, phone, date, date & time --}}
                <input wire:model="itemForm.{{ $key }}"
                       type="{{ $ftype === 'datetime' ? 'datetime-local' : (in_array($ftype, ['number', 'date', 'email', 'tel', 'url'], true) ? $ftype : 'text') }}"
                       @if ($ftype === 'url') placeholder="https://…" @endif
                       @if (! empty($f['required'])) required aria-required="true" @endif
                       @if ($ftype === 'number') step="{{ $f['step'] ?? 'any' }}" @isset($f['min']) min="{{ $f['min'] }}" @endisset @isset($f['max']) max="{{ $f['max'] }}" @endisset @endif
                       class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
            @endif
            @error('itemForm.'.$key)<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
        </div>
        @endforeach
    </div>
    <datalist id="media-url-options">
        @foreach($this->mediaUrlOptions as $m)<option value="{{ $m['url'] }}">{{ $m['name'] }}</option>@endforeach
    </datalist>
    @unless ($bare)
    <div class="flex gap-2 mt-3">
        <button wire:click="saveItem" class="px-4 py-2 rounded-lg text-xs font-semibold text-white" style="background:var(--primary)">Save entry</button>
        <button wire:click="cancelItem" class="px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300">Cancel</button>
    </div>
    @endunless
</div>
@endif
