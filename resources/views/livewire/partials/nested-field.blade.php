{{-- Structured editor for a NESTED collection value. Expects:
       $path     dotted wire path to the value (e.g. "edit.items.3.data.facts")
       $value    the current array/object
       $fieldKey the field's key (labels)
       $def      optional schema entry {type: list|rows|group|images, fields: [sub keys]} —
                 shapes an EMPTY value (no rows yet to infer from)
       $siteId   optional — resolves media (falls back to $site->id)
     Renders one of: media gallery · scalar list · row list (sub-cards) · group (fixed keys).
     Every leaf picks its input from its value (partials.nested-leaf: media, number, yes/no, text).
     Values failing WithNestedFields::nestedEditable must not be passed here. --}}
@php
    $def ??= null;
    $defType = $def['type'] ?? null;
    // Sub-fields arrive as plain keys (Connect editor) or as full field
    // definitions (Collections page) — accept both.
    $rawSub = (array) ($def['fields'] ?? []);
    $subKeys = array_values(array_filter(array_map(fn ($x) => is_array($x) ? ($x['key'] ?? null) : $x, $rawSub)));
    // typed sub-fields (declared with @olux-field media.type …): key => {type,label,options,show}
    $subDefs = (array) ($def['subDefs'] ?? collect($rawSub)->filter(fn ($x) => is_array($x) && filled($x['key'] ?? null))
        ->mapWithKeys(fn ($x) => [$x['key'] => array_intersect_key($x, array_flip(['type', 'label', 'options', 'optionsFrom', 'show']))])->all());
    if ($subDefs !== []) {
        $subKeys = array_values(array_unique(array_merge(array_keys($subDefs), $subKeys)));
    }
    $value = is_array($value) ? $value : [];
    $isList = array_is_list($value) && $defType !== 'group';
    $isRows = $isList && (is_array($value[0] ?? null) || ($value === [] && $defType === 'rows'));
    // Sub-field keys for the first row of an empty rows list (single-quoted: lives in a wire:click attribute).
    $safeKeys = array_values(array_filter($subKeys, fn ($k) => is_string($k) && preg_match('/^[A-Za-z0-9_-]{1,60}$/', $k)));
    $addArgs = $isRows && ($value === [] || $subDefs !== []) && $safeKeys !== [] ? ", ['".implode("','", $safeKeys)."']" : '';
    $siteId ??= isset($site) ? $site->id : null;
    // "+ Add …" button label: the field name made singular, except words that
    // don't singularise (media → "medium" reads wrong) or would go odd.
    $addWords = strtolower(trim(str_replace(['-', '_'], ' ', \Illuminate\Support\Str::snake((string) $fieldKey, ' '))));
    $addNoun = in_array($addWords, ['media', 'audio', 'video', 'data', 'info', 'news', 'series', 'content', 'lyrics', 'gallery', 'speakers info'], true)
        ? $addWords : \Illuminate\Support\Str::singular($addWords);
    // A list of assets (all entries media) → a gallery editor.
    $isGallery = $isList && ! $isRows && (\App\Support\MediaValue::isMediaList($value, $siteId) || ($value === [] && in_array($defType, ['images', 'gallery', 'media'], true)));
@endphp

@if ($isGallery)
    {{-- media gallery: thumbnails · ◀ ▶ reorder · ✕ remove · + add from Assets --}}
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
        @foreach ($value as $j => $entry)
            @php $gm = \App\Support\MediaValue::detect($entry, $siteId); @endphp
            <div class="group relative rounded-xl border border-gray-100 dark:border-white/[0.08] overflow-hidden bg-gray-50 dark:bg-white/[0.04]" wire:key="{{ $path }}-g-{{ $j }}-{{ md5((string) $entry) }}">
                <div class="aspect-square flex items-center justify-center">
                    @if ($gm && $gm['kind'] === 'image')
                        <img src="{{ $gm['url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.visibility='hidden'">
                    @elseif ($gm && $gm['kind'] === 'video')
                        <video src="{{ $gm['url'] }}#t=0.5" preload="metadata" muted class="w-full h-full object-cover bg-black"></video>
                    @else
                        <span class="text-[10px] font-bold uppercase text-gray-500 px-1 text-center break-all">{{ $gm['kind'] ?? 'file' }}<br><span class="font-normal normal-case">{{ \Illuminate\Support\Str::limit($gm['name'] ?? (string) $entry, 18) }}</span></span>
                    @endif
                </div>
                <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-black/55 px-1 py-0.5 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="flex">
                        @if ($j > 0)<button type="button" wire:click="nestedMove('{{ $path }}', {{ $j }}, -1)" class="w-5 h-5 text-[11px] text-white" title="Move earlier">◀</button>@endif
                        @if ($j < count($value) - 1)<button type="button" wire:click="nestedMove('{{ $path }}', {{ $j }}, 1)" class="w-5 h-5 text-[11px] text-white" title="Move later">▶</button>@endif
                    </span>
                    <button type="button" wire:click="nestedRemove('{{ $path }}', {{ $j }})" class="w-5 h-5 text-[11px] text-white hover:text-rose-300" title="Remove">✕</button>
                </div>
            </div>
        @endforeach
        <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'nested-media', path: '{{ $path }}', append: true } })"
                class="aspect-square rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.12] text-[11px] font-bold flex flex-col items-center justify-center gap-0.5 hover:border-indigo-300" style="color:var(--primary)">
            <span class="text-lg leading-none">+</span> Add from Assets
        </button>
    </div>
@else

@if ($isList && ! $isRows)
    {{-- scalar list: tags, questions, body paragraphs… --}}
    <div class="space-y-1">
        @foreach ($value as $j => $entry)
            <span class="flex items-center gap-1.5" wire:key="{{ $path }}-{{ $j }}">
                <span class="flex-1 min-w-0">@include('livewire.partials.nested-leaf', ['path' => $path.'.'.$j, 'value' => $entry, 'siteId' => $siteId])</span>
                <button type="button" wire:click="nestedRemove('{{ $path }}', {{ $j }})"
                        class="shrink-0 w-6 h-6 rounded-lg text-[11px] text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Remove">✕</button>
            </span>
        @endforeach
        <button type="button" wire:click="nestedAdd('{{ $path }}'{{ $addArgs }})"
                class="inline-flex items-center gap-1 mt-0.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border border-dashed hover:bg-[color-mix(in_srgb,var(--primary)_8%,transparent)]"
                style="color:var(--primary);border-color:color-mix(in srgb,var(--primary) 45%,transparent)"><span aria-hidden="true">+</span> Add {{ $addNoun }}</button>
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
                @php
                    // declared sub-fields first (in order), then any other keys the row carries
                    $rowKeys = $subDefs !== [] ? array_values(array_unique(array_merge(array_keys($subDefs), array_keys((array) $row)))) : array_keys((array) $row);
                @endphp
                @foreach ($rowKeys as $sk)
                    @php
                        $sd = $subDefs[$sk] ?? null;
                        $sv = $row[$sk] ?? '';
                        // conditional sub-field: show=type:audio|video
                        $shown = empty($sd['show']) || in_array((string) ($row[$sd['show']['field']] ?? ''), (array) $sd['show']['in'], true);
                    @endphp
                    @if ($shown)
                        <label class="block mb-1" wire:key="{{ $path }}-{{ $j }}-{{ $sk }}">
                            <span class="text-[10px] text-gray-400">{{ $sd['label'] ?? \Illuminate\Support\Str::headline((string) $sk) }}</span>
                            @include('livewire.partials.nested-leaf', ['path' => $path.'.'.$j.'.'.$sk, 'value' => $sv, 'siteId' => $siteId, 'leafDef' => $sd])
                        </label>
                    @endif
                @endforeach
            </div>
        @endforeach
        <button type="button" wire:click="nestedAdd('{{ $path }}'{{ $addArgs }})"
                class="inline-flex items-center gap-1 mt-0.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border border-dashed hover:bg-[color-mix(in_srgb,var(--primary)_8%,transparent)]"
                style="color:var(--primary);border-color:color-mix(in srgb,var(--primary) 45%,transparent)"><span aria-hidden="true">+</span> Add {{ $addNoun }}</button>
    </div>
@else
    {{-- group: a fixed-shape object (cta, logo…) --}}
    <div class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-1.5">
        @foreach ((array) $value as $sk => $sv)
            <label class="block mb-1">
                <span class="text-[10px] text-gray-400">{{ \Illuminate\Support\Str::headline((string) $sk) }}</span>
                @include('livewire.partials.nested-leaf', ['path' => $path.'.'.$sk, 'value' => $sv, 'siteId' => $siteId])
            </label>
        @endforeach
    </div>
@endif
@endif
