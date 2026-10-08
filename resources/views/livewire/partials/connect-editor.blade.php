{{-- Inline editor for a committed content model (shared by ingestion + content modes). Expects $edit + $site. --}}
@php $t = $edit['type'] ?? null; @endphp

@if ($t === 'component')
    <div class="mt-4 flex items-center justify-between">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Fields</p>
        <button wire:click="addNode" class="text-xs font-semibold" style="color:var(--primary)">+ Add field</button>
    </div>
    @if (! empty($edit['siteProperties']))
        {{-- Site Properties: the same fields as the Properties page, grouped by its tabs. --}}
        @php
            $spFields = collect(\App\Support\SiteProperties::fields())->keyBy('label');
            $spGroups = [];
            foreach ($edit['nodes'] as $i => $node) {
                $def = $spFields->get($node['label']);
                $tab = $def['tab'] ?? null;
                if (! $def && ($m = \App\Support\SiteProperties::repeaterMatch((string) $node['label']))) {
                    $r = \App\Support\SiteProperties::repeaters()[$m[0]];
                    $def = $r['fields'][$m[2]] + ['repeaterRow' => true];
                    $tab = $r['tab'];
                }
                $spGroups[$tab ?? 'variables'][] = [$i, $node, $def];
            }
        @endphp
        <p class="mt-1 text-[10.5px] text-gray-400">Also on the <a href="{{ url($site->name.'/properties') }}" class="font-semibold underline" style="color:var(--primary)">Properties page</a> — changes show in both.</p>
        @php
            // Opened from the preview on a few fields (data-olx-fields): just those, in that order.
            $focus = (array) ($edit['focus'] ?? []);
            $focusRows = [];
            if ($focus !== []) {
                $propFields = \App\Support\SiteProperties::fields();
                foreach ($focus as $fk) {
                    foreach ($edit['nodes'] as $i => $node) {
                        $def = $spFields->get($node['label']);
                        $rep = \App\Support\SiteProperties::repeaterMatch((string) $node['label']);
                        if (($def && ($propFields[$fk]['label'] ?? null) === $node['label']) || ($rep && $rep[0] === $fk)) {
                            $focusRows[] = [$i, $node, $def ?: (\App\Support\SiteProperties::repeaters()[$rep[0]]['fields'][$rep[2]] + ['repeaterRow' => true])];
                        }
                    }
                }
            }
        @endphp
        @if ($focusRows !== [])
            <div class="mt-1.5 space-y-2">
                @foreach ($focusRows as [$i, $node, $def])
                    @include('livewire.partials.connect-node-field', ['i' => $i, 'node' => $node, 'site' => $site, 'def' => $def])
                @endforeach
            </div>
            <button type="button" wire:click="showAllProperties" class="mt-2 text-[11px] font-semibold underline" style="color:var(--primary)">Show all site properties</button>
        @else
        <div class="mt-1.5 space-y-2">
            @foreach (config('site-properties.tabs') as $tk => $tl)
                @continue(empty($spGroups[$tk]))
                <details class="rounded-xl border border-gray-100 dark:border-white/[0.06]" @if ($loop->first) open @endif>
                    <summary class="cursor-pointer select-none px-2.5 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ $tl }} <span class="font-normal normal-case text-gray-400">({{ count($spGroups[$tk]) }})</span></summary>
                    <div class="px-2 pb-2 space-y-2">
                        @foreach ($spGroups[$tk] as [$i, $node, $def])
                            @include('livewire.partials.connect-node-field', ['i' => $i, 'node' => $node, 'site' => $site, 'def' => $def])
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
        @endif
    @else
    @php
        // Repeatable rows ("Slide 1 Image", "Slide 1 Caption"…) group into one
        // card per row, with add / remove / reorder for the whole group.
        $plainNodes = [];
        $rowGroups = [];
        foreach ($edit['nodes'] as $i => $node) {
            if (($node['type'] ?? '') !== 'collection' && preg_match('/^(.+?) (\d+)(?: (.+))?$/', (string) $node['label'], $m)) {
                $rowGroups[$m[1]][(int) $m[2]][] = [$i, $node, $m[3] ?? 'Text'];
            } else {
                $plainNodes[] = [$i, $node];
            }
        }
        foreach ($rowGroups as &$rows) {
            ksort($rows);
        }
        unset($rows);
    @endphp
    <div class="mt-1.5 space-y-2">
        @foreach ($plainNodes as [$i, $node])
            @include('livewire.partials.connect-node-field', ['i' => $i, 'node' => $node, 'site' => $site, 'def' => null])
        @endforeach
        @foreach ($rowGroups as $prefix => $rows)
            <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] p-2" data-node-rows="{{ $prefix }}">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ \Illuminate\Support\Str::plural($prefix) }} <span class="font-normal normal-case text-gray-400">({{ count($rows) }})</span></p>
                @foreach ($rows as $n => $fields)
                    <div class="mt-2 rounded-lg bg-gray-50/70 dark:bg-white/[0.03] p-1.5 space-y-1.5" wire:key="row-{{ \Illuminate\Support\Str::slug($prefix) }}-{{ $n }}">
                        <div class="flex items-center gap-1.5 px-0.5">
                            <span class="flex-1 text-[11px] font-semibold text-gray-600 dark:text-gray-300">{{ $prefix }} {{ $loop->iteration }}</span>
                            <button type="button" wire:click="moveNodeRow(@js($prefix), {{ $n }}, -1)" @disabled($loop->first) class="text-[11px] text-gray-400 disabled:opacity-30" title="Move up">↑</button>
                            <button type="button" wire:click="moveNodeRow(@js($prefix), {{ $n }}, 1)" @disabled($loop->last) class="text-[11px] text-gray-400 disabled:opacity-30" title="Move down">↓</button>
                            @if (count($rows) > 1)
                                <button type="button" wire:click="removeNodeRow(@js($prefix), {{ $n }})" class="text-[11px] text-rose-500">Remove</button>
                            @endif
                        </div>
                        @foreach ($fields as [$i, $node, $field])
                            @include('livewire.partials.connect-node-field', ['i' => $i, 'node' => $node, 'site' => $site, 'def' => null, 'rowField' => $field])
                        @endforeach
                    </div>
                @endforeach
                <button type="button" wire:click="addNodeRow(@js($prefix))" class="mt-2 text-xs font-semibold" style="color:var(--primary)">+ Add {{ strtolower($prefix) }}</button>
                <p class="mt-1 text-[10px] text-gray-400">Save the component to apply added, removed or reordered {{ strtolower(\Illuminate\Support\Str::plural($prefix)) }}.</p>
            </div>
        @endforeach
    </div>
    @endif
    @if (!empty($edit['collection']))
        {{-- Data source backing this component — entries edit in the collection panel --}}
        <div class="mt-4">
            @include('livewire.partials.connect-collection-card', ['card' => $edit['collection'], 'title' => '📦 Data source — '.$edit['collection']['name']])
            <p class="mt-1.5 text-[10px] text-gray-400">These entries feed this section — click one to open it in Collections, or “Edit here” to edit them in this panel.</p>
        </div>
    @endif
    <button wire:click="saveComponent" class="olx-save">Save component</button>

@elseif ($t === 'collection')
    {{-- Item schema: the fields every item carries; extendable any time --}}
    <div class="mt-4 flex items-center gap-1.5">
        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider shrink-0">Item fields</span>
        <span class="text-[11px] text-gray-400 truncate flex-1">{{ implode(' · ', $edit['schema']) ?: 'none yet' }}</span>
    </div>
    {{-- New field = label + type + default; the default is written onto EVERY existing item and used for new ones --}}
    <div class="mt-1 space-y-1">
        <div class="flex items-center gap-1.5">
            <input wire:model="newField.label" wire:keydown.enter="addCollectionField" placeholder="Field label, e.g. Photo"
                   class="olx-in !mt-0 flex-1 min-w-0">
            <select wire:model.live="newField.type" class="olx-in !mt-0 !w-24 shrink-0">
                @foreach (\App\Livewire\ConnectReviewPage::ITEM_FIELD_TYPES as $ft)
                    <option value="{{ $ft }}">{{ $ft }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-1.5">
            <input wire:model="newField.default" wire:keydown.enter="addCollectionField"
                   placeholder="Default value (applied to all items)" class="olx-in !mt-0 flex-1 min-w-0">
            @if (($newField['type'] ?? '') === 'image')
                <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'connect-new-field' } })"
                        class="shrink-0 px-1.5 py-1 rounded-lg text-[10px] font-semibold text-gray-500 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]">Assets</button>
            @endif
            <button wire:click="addCollectionField" class="shrink-0 text-xs font-semibold px-2 py-1.5 rounded-lg" style="color:var(--primary)">+ Add field</button>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Items ({{ count($edit['items']) }})</p>
        <button wire:click="addItem" class="text-xs font-semibold" style="color:var(--primary)">+ Add item</button>
    </div>
    {{-- Entries as collapsible cards: thumbnail + headline closed, full
         fields open. New/duplicated entries pop open automatically. --}}
    <div class="mt-1.5 space-y-2" data-items-list>
        @foreach ($edit['items'] as $i => $item)
            @php
                $imgKeys = ['img', 'image', 'photo', 'src', 'cover', 'avatar', 'poster'];
                $thumbKey = collect($edit['schema'])->first(fn ($k) => in_array(strtolower($k), $imgKeys, true));
                // A declared image field wins; a gallery (images) shows its first picture.
                $thumbKey = collect($edit['schema'])->first(fn ($k) => in_array($edit['fieldDefs'][$k]['type'] ?? '', ['image', 'images'], true)) ?? $thumbKey;
                $thumbVal = $thumbKey ? ($item['data'][$thumbKey] ?? '') : '';
                $thumbRaw = is_array($thumbVal) ? (string) ($thumbVal[0] ?? '') : (string) $thumbVal;
                $thumb = $thumbRaw !== '' && (is_array($thumbVal) || ! is_array($item['data'][$thumbKey] ?? null))
                    ? (str_starts_with($thumbRaw, '/assets/') ? \App\Models\Media::resolveRef($site->id, '@media/'.basename($thumbRaw)) : \App\Models\Media::resolveRef($site->id, $thumbRaw))
                    : '';
                $headKey = collect($edit['schema'])->first(fn ($k) => ! in_array(strtolower($k), $imgKeys, true) && is_string($item['data'][$k] ?? null) && trim((string) $item['data'][$k]) !== '');
                $headline = $headKey ? \Illuminate\Support\Str::limit((string) $item['data'][$headKey], 46) : 'New entry';
            @endphp
            {{-- Keyed per collection + entry: without it Livewire morphs one
                 collection's cards into the next one's (Contact Info → Hero
                 Words), leaving inputs bound to the OLD field — blank, and
                 saving under the wrong key. --}}
            <div data-item-row wire:key="olx-item-{{ $edit['id'] ?? 'new' }}-{{ $item['id'] ?? 'n'.$i }}" x-data="{ open: {{ empty($item['id']) ? 'true' : 'false' }} }" @olx-expand="open = true"
                 class="rounded-xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                {{-- Card header: click to open/close --}}
                <div class="flex items-center gap-2 px-2 py-1.5 cursor-pointer select-none hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                     @click="open = ! open">
                    <span class="text-[10px] font-bold text-gray-300 dark:text-gray-500 w-4 shrink-0">{{ $i + 1 }}</span>
                    @if ($thumb)
                        <img src="{{ $thumb }}" alt="" class="w-8 h-8 rounded-lg object-cover shrink-0 border border-gray-100 dark:border-white/[0.08]" onerror="this.style.display='none'">
                    @endif
                    <span class="flex-1 min-w-0 text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $headline }}</span>
                    <span class="flex items-center gap-1 shrink-0" @click.stop>
                        @if ($i > 0)
                            <button wire:click="moveItem({{ $i }}, -1)" class="w-6 h-6 rounded-lg text-[12px] text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.08]" title="Move up">↑</button>
                        @endif
                        @if ($i < count($edit['items']) - 1)
                            <button wire:click="moveItem({{ $i }}, 1)" class="w-6 h-6 rounded-lg text-[12px] text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/[0.08]" title="Move down">↓</button>
                        @endif
                        <button wire:click="duplicateItem({{ $i }})" class="w-6 h-6 rounded-lg text-[12px] text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-white/[0.08]" title="Duplicate entry">⧉</button>
                        <button wire:click="removeItem({{ $i }})" data-confirm="Remove this entry?" class="w-6 h-6 rounded-lg text-[12px] text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Remove entry">✕</button>
                    </span>
                    <svg class="w-3 h-3 shrink-0 opacity-50 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </div>

                {{-- Card body: the fields --}}
                <div x-show="open" x-collapse x-cloak class="px-2 pb-2 pt-1 border-t border-gray-50 dark:border-white/[0.04] space-y-1.5">
                    @foreach ($edit['schema'] as $key)
                        @continue(! empty($edit['fieldDefs'][$key]['hidden']))
                        @if (\App\Support\CollectionAutoFields::valid($edit['fieldDefs'][$key]['auto'] ?? null))
                            {{-- System-filled (a slug, dates, entry number …) — shown, never typed in --}}
                            @php $autoVal = $item['data'][$key] ?? ''; @endphp
                            <div class="block" wire:key="olx-field-{{ $edit['id'] ?? 'new' }}-{{ $i }}-{{ $key }}">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $edit['fieldDefs'][$key]['label'] ?: \Illuminate\Support\Str::headline($key) }}</span>
                                <span class="block mt-0.5 px-2 py-1.5 rounded-lg text-[12px] text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-white/[0.04] border border-dashed border-gray-200 dark:border-white/10 break-all">{{ is_scalar($autoVal) && $autoVal !== '' ? \App\Support\SiteTokens::apply($site, (string) $autoVal) : 'Filled in when you save' }}</span>
                                <span class="block text-[10px] text-gray-400 mt-0.5">Filled automatically · {{ \App\Support\CollectionAutoFields::label($edit['fieldDefs'][$key]['auto']) }}</span>
                            </div>
                            @continue
                        @endif
                        @php
                            $val = $item['data'][$key] ?? '';
                            $fdef = $edit['fieldDefs'][$key] ?? null;
                            $ftype = $fdef['type'] ?? 'text';
                            $required = (bool) ($fdef['required'] ?? false);
                            // A nested list (list/rows/group, a gallery, tags) — edit structurally even when empty.
                            $isNested = in_array($ftype, ['list', 'rows', 'group', 'images', 'tags'], true) && ($val === '' || $val === [] || is_array($val));
                            $isImg = $ftype === 'image' || in_array(strtolower($key), $imgKeys, true);
                            // Declared textareas always get the multi-line editor; others when the value is long.
                            $taRows = \App\Models\Collection::textareaRows($ftype);
                            $isLong = $taRows || (! \App\Models\Collection::isBooleanType($ftype) && is_string($val) && (mb_strlen($val) > 70 || str_contains($val, "\n")));
                            $isJson = is_array($val);
                            $kind = match ($ftype) { 'images' => 'gallery', 'tags' => 'tags', 'group' => 'group', 'list', 'rows' => 'list', default => null };
                            $isEmpty = $val === '' || $val === [] || $val === null;
                        @endphp
                        {{-- Keyed by wire path: a reorder changes the index, so the input must rebind. --}}
                        <label class="block" wire:key="olx-field-{{ $edit['id'] ?? 'new' }}-{{ $i }}-{{ $key }}">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $fdef['label'] ?? \Illuminate\Support\Str::headline($key) }}@if ($required)<span class="text-rose-500" title="Required"> *</span>@endif @if ($isNested && $kind) <span class="font-normal normal-case">· {{ $kind }}</span>@endif @if ($required && $isEmpty)<span class="font-normal normal-case text-rose-500">· required</span>@endif</span>
                            @if ($isNested && ($val === '' || $val === [] || \App\Livewire\Concerns\WithNestedFields::nestedEditable($val)))
                                @include('livewire.partials.nested-field', ['path' => "edit.items.$i.data.$key", 'value' => $val === '' ? [] : $val, 'fieldKey' => $key, 'def' => $fdef])
                            @elseif ($isJson && \App\Livewire\Concerns\WithNestedFields::nestedEditable($val))
                                @include('livewire.partials.nested-field', ['path' => "edit.items.$i.data.$key", 'value' => $val, 'fieldKey' => $key])
                            @elseif ($isJson)
                                {{-- irregular/deep value — protected raw preview --}}
                                <span class="block mt-0.5 px-2 py-1.5 rounded-lg text-[11px] font-mono text-gray-400 bg-gray-50 dark:bg-white/[0.04] truncate">{{ \Illuminate\Support\Str::limit(json_encode($val, JSON_UNESCAPED_UNICODE), 60) }}</span>
                                <a href="{{ route('collections.show', [$site->name, $edit['id'] ?? '']) }}" target="_blank" class="text-[10px] font-semibold text-indigo-500 hover:underline">Edit this list in Collections ↗</a>
                            @elseif (\App\Models\Collection::isBooleanType($ftype))
                                {{-- Yes/no: the whole row is the label, so the switch toggles on click --}}
                                <span class="flex items-center gap-2.5 mt-1 cursor-pointer">
                                    <input type="checkbox" wire:model.live="edit.items.{{ $i }}.data.{{ $key }}" class="{{ $ftype === 'toggle' ? 'sr-only' : 'w-4 h-4 accent-[var(--primary)]' }}">
                                    @if ($ftype === 'toggle')<span class="bkf-switch"></span>@endif
                                    <span class="text-[12px] font-semibold text-gray-700 dark:text-gray-200">{{ filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'On' : 'Off' }}</span>
                                </span>
                            @elseif ($ftype === 'select' && ($opts = \App\Support\CollectionFieldOptions::for($site->id, $fdef ?? [])) !== [])
                                <select wire:model.live="edit.items.{{ $i }}.data.{{ $key }}" class="olx-in !mt-0.5" @if ($required) required @endif>
                                    <option value="">—</option>
                                    @foreach ($opts as $val => $lab)
                                        <option value="{{ $val }}">{{ empty($fdef['optionsFrom']) ? ucfirst($lab) : $lab }}</option>
                                    @endforeach
                                </select>
                            @elseif ($ftype === 'datetime')
                                <input type="datetime-local" wire:model.blur="edit.items.{{ $i }}.data.{{ $key }}" class="olx-in !mt-0.5" @if ($required) required @endif>
                            @elseif ($ftype === 'media')
                                <span class="flex items-center gap-1.5 mt-0.5">
                                    <input wire:model.blur="edit.items.{{ $i }}.data.{{ $key }}" class="olx-in !mt-0 flex-1 min-w-0" placeholder="Choose an image, audio or video">
                                    <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'nested-media', path: 'edit.items.{{ $i }}.data.{{ $key }}' } })"
                                            class="shrink-0 px-1.5 py-1 rounded-lg text-[10px] font-semibold text-gray-500 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]"
                                            title="Choose from the asset library">Assets</button>
                                </span>
                            @elseif ($isLong)
                                @include('livewire.partials.rich-text', ['path' => "edit.items.$i.data.$key", 'value' => $val, 'rows' => $taRows ?? 4])
                            @else
                                <span class="flex items-center gap-1.5 mt-0.5">
                                    @if ($isImg && $thumbKey === $key && $thumb)
                                        <img src="{{ $thumb }}" alt="" class="w-7 h-7 rounded-md object-cover shrink-0" onerror="this.style.display='none'">
                                    @endif
                                    <input wire:model.blur="edit.items.{{ $i }}.data.{{ $key }}" class="olx-in !mt-0 flex-1 min-w-0"
                                           @if ($ftype === 'url') type="url" placeholder="https://…" @elseif (in_array($ftype, ['number', 'date', 'email', 'tel'], true)) type="{{ $ftype }}" @endif
                                           @if ($required) required aria-required="true" @endif>
                                    @if ($isImg)
                                        <button type="button" @click="$dispatch('open-media-picker', { context: { scope: 'connect', itemIndex: {{ $i }}, itemKey: '{{ $key }}' } })"
                                                class="shrink-0 px-1.5 py-1 rounded-lg text-[10px] font-semibold text-gray-500 dark:text-gray-300 bg-gray-100 dark:bg-white/[0.06] hover:bg-gray-200 dark:hover:bg-white/[0.1]"
                                                title="Choose from the asset library">Assets</button>
                                    @endif
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <button wire:click="saveCollection" class="olx-save">Save collection</button>

@elseif ($t === 'form')
    <label class="block mt-4">
        <span class="text-[11px] text-gray-400">Title</span>
        <input wire:model="edit.title" class="olx-in">
    </label>
    <label class="block mt-2">
        <span class="text-[11px] text-gray-400">Submit endpoint</span>
        <input type="url" wire:model="edit.endpoint" class="olx-in" placeholder="Blank = capture in CRM (form responses)">
        <span class="text-[10px] text-gray-400">Leave blank so submissions are saved as form responses in this CMS.</span>
    </label>

    <div class="mt-3 flex items-center justify-between">
        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Fields</p>
        <button wire:click="addFormField" class="text-xs font-semibold" style="color:var(--primary)">+ Add field</button>
    </div>
    <div class="mt-1.5 space-y-2">
        @foreach ($edit['fields'] as $i => $field)
            <div class="rounded-lg border border-gray-100 dark:border-white/[0.06] p-2">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] text-gray-400">#{{ $i + 1 }}</span>
                    <button wire:click="removeFormField({{ $i }})" class="text-[11px] text-rose-500">Remove</button>
                </div>
                <input wire:model="edit.fields.{{ $i }}.label" class="olx-in" placeholder="Label">
                <div class="grid grid-cols-2 gap-1.5 mt-1">
                    <input wire:model="edit.fields.{{ $i }}.key" class="olx-in" placeholder="key">
                    <select wire:model="edit.fields.{{ $i }}.type" class="olx-in">
                        @foreach (['text', 'email', 'tel', 'textarea', 'number', 'select', 'checkbox', 'date'] as $ft)
                            <option value="{{ $ft }}">{{ $ft }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-1.5 mt-1 text-[11px] text-gray-500">
                    <input type="checkbox" wire:model="edit.fields.{{ $i }}.required"> required
                </label>
            </div>
        @endforeach
    </div>
    <button wire:click="saveForm" class="olx-save">Save form</button>
    <a href="{{ route('site.forms', ['siteID' => $site->name]) }}" wire:navigate
       class="mt-2 block text-center text-xs font-semibold" style="color:var(--primary)">
        Open in Forms (details + responses) →
    </a>

@elseif ($t === 'post')
    <label class="block mt-4"><span class="text-[11px] text-gray-400">Title</span>
        <input wire:model="edit.title" class="olx-in"></label>
    <label class="block mt-2"><span class="text-[11px] text-gray-400">Intro</span>
        <textarea wire:model="edit.excerpt" rows="2" class="olx-in"></textarea></label>
    <div class="block mt-2" wire:key="post-body-{{ $edit['id'] ?? 'new' }}">
        <span class="text-[11px] text-gray-400">Content</span>
        @include('livewire.partials.rich-text', ['path' => 'edit.body', 'value' => $edit['body'] ?? '', 'blocks' => true, 'rows' => 8])
    </div>
    <button wire:click="savePost" class="olx-save">Save post</button>
    <a href="{{ route('site.posts', ['siteID' => $site->name]) }}?post={{ $edit['id'] ?? '' }}" wire:navigate
       class="mt-2 block text-center text-xs font-semibold" style="color:var(--primary)">
        Open in Posts →
    </a>
@endif
