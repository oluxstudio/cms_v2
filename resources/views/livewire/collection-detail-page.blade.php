@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $iconBtn = $btnSolid.' w-8 h-8 shrink-0';
    // Entries, a new entry and the field editor each open on their own page.
    $onSubPage = $panelId !== null || $editingFields;
@endphp

<x-tri-layout :title="$viewing->name" :subtitle="$stats['total'].' '.Str::plural('entry', $stats['total']).' · '.Str::headline($viewing->type ?: 'collection')"
    :site-name="$site->name" :labels="['📊 Overview', '🗂️ Entries', '📌 Used by']" quick-width="lg:!w-[300px] xl:!w-[320px]">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('collections', $site->name) }}" wire:navigate class="{{ $btnSolid }} text-sm px-4 py-2.5">← All collections</a>
            @if ($canManage)
                <a href="{{ $this->pageUrl('new') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add entry
                </a>
            @endif
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail ══ --}}
    <x-slot:rail>
        <div class="grid grid-cols-2 gap-3">
            <x-tile accent="ink" wide :value="$stats['total']" label="Entries" :sub="$stats['published'].' live on the site'"
                icon="M4 6h16M4 12h16M4 18h16" />
            <x-tile accent="lime" :value="$stats['published']" label="Published" icon="M5 13l4 4L19 7" />
            <x-tile accent="cocoa" :value="$stats['drafts']" label="Drafts" sub="hidden from the site" icon="M15.2 4.8l4 4L8 20H4v-4L15.2 4.8z" />
            <x-tile accent="sky" :value="$stats['fields']" label="Fields" icon="M4 7h16M4 12h10M4 17h7" />
            <x-tile accent="lavender" :value="count($usedBy)" label="Blocks using it" icon="M4 5h7v7H4zM13 5h7v7h-7zM4 14h7v7H4zM13 14h7v7h-7z" />
        </div>
    </x-slot:rail>

    @unless ($onSubPage)
    <div class="max-w-[52rem] mx-auto space-y-4">
        <div class="{{ $panel }} overflow-hidden">
            @if (! $fields)
                <p class="p-8 text-sm text-gray-500 text-center">This collection has no fields yet — add them from the Collections page or the Edit page.</p>
            @elseif ($entries->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sm text-gray-500">No entries yet.</p>
                    @if ($canManage)<a href="{{ $this->pageUrl('new') }}" wire:navigate class="inline-block mt-3 text-sm font-bold" style="color:var(--primary)">+ Add the first entry</a>@endif
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/[0.05]">
                    @foreach ($entries as $i => $item)
                        @php $img = $image($item); $isEditing = $panelId === $item->id; @endphp
                        <li wire:key="entry-{{ $item->id }}" class="flex items-center gap-3 px-4 py-3 {{ $isEditing ? 'bg-indigo-50/60 dark:bg-indigo-500/10' : '' }}">
                            <span class="w-6 text-[11px] font-bold text-gray-400 text-right shrink-0">{{ $i + 1 }}</span>
                            <div class="w-11 h-11 rounded-xl bg-gray-100 dark:bg-white/[0.05] overflow-hidden shrink-0 flex items-center justify-center">
                                @if ($img)
                                    <img src="{{ $img }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                                @else
                                    <span class="text-[11px] font-bold text-gray-400">{{ Str::upper(Str::substr($label($item), 0, 2)) }}</span>
                                @endif
                            </div>
                            <a href="{{ $this->entryUrl($item->id) }}" wire:navigate class="min-w-0 flex-1 text-left" title="Open this entry">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $label($item) }}</span>
                                <span class="block text-[10.5px] text-gray-400" title="Created {{ $item->created_at?->format('j M Y, H:i') }}">Updated {{ $item->updated_at?->diffForHumans() ?? '—' }}</span>
                                <span class="block text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                                    {{ collect($fields)->reject(fn ($k) => in_array($k, ['name', 'title', 'label'], true))->take(3)
                                        ->map(fn ($k) => is_scalar(data_get($item->data, $k)) ? Str::limit(strip_tags(\App\Support\SiteTokens::apply($site, (string) data_get($item->data, $k))), 40) : (is_array(data_get($item->data, $k)) ? count(data_get($item->data, $k)).' items' : ''))
                                        ->filter()->implode(' · ') }}
                                </span>
                            </a>
                            @if ($canManage)
                                <button wire:click="toggleStatus('{{ $item->id }}')"
                                        class="shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full {{ $item->status === 'published' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700 dark:bg-white/[0.1] dark:text-gray-200' }}"
                                        title="{{ $item->status === 'published' ? 'Live — click to make it a draft' : 'Draft — click to publish' }}">{{ $item->status === 'published' ? 'Live' : 'Draft' }}</button>
                                <span class="hidden sm:flex items-center gap-1.5 shrink-0">
                                    <button wire:click="moveItem('{{ $item->id }}', -1)" @disabled($loop->first) class="{{ $iconBtn }} disabled:opacity-30" title="Move up">↑</button>
                                    <button wire:click="moveItem('{{ $item->id }}', 1)" @disabled($loop->last) class="{{ $iconBtn }} disabled:opacity-30" title="Move down">↓</button>
                                </span>
                                <a href="{{ $this->entryUrl($item->id, true) }}" wire:navigate class="{{ $iconBtn }}" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button wire:click="deleteItem('{{ $item->id }}')" data-confirm="Delete “{{ $label($item) }}”? It disappears from the collection and every block that shows it — you can restore it from Deleted entries."
                                        class="{{ $iconBtn }} hover:!text-red-600 hover:!border-red-200" title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @if ($canManage && $entries->count() > 1)
            <p class="text-[11.5px] text-gray-500 dark:text-gray-400 px-2">The order here is the collection's own order. Each block can also show its own selection and order — set that on the Edit page.</p>
        @endif

        {{-- Soft-deleted entries: gone from the site, restorable here --}}
        @if ($trashed->isNotEmpty())
            <div class="{{ $panel }} overflow-hidden">
                <button type="button" wire:click="$toggle('showTrash')" class="w-full flex items-center justify-between px-5 py-3.5 text-left">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">Deleted entries <span class="font-normal text-gray-400">({{ $trashed->count() }})</span></span>
                    <span class="text-gray-400 text-lg leading-none">{{ $showTrash ? '−' : '+' }}</span>
                </button>
                @if ($showTrash)
                    <ul class="divide-y divide-gray-100 dark:divide-white/[0.05] border-t border-gray-100 dark:border-white/[0.05]">
                        @foreach ($trashed as $t)
                            <li wire:key="trash-{{ $t->id }}" class="flex items-center gap-3 px-5 py-3">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-500 dark:text-gray-400 line-through truncate">{{ $label($t) }}</span>
                                    <span class="block text-[10.5px] text-gray-400">Deleted {{ $t->deleted_at?->format('j M Y, H:i') }} · created {{ $t->created_at?->format('j M Y') }}</span>
                                </span>
                                @if ($canManage)
                                    <button wire:click="restoreItem('{{ $t->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">Restore</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if ($canManage)
            {{-- ── Grouped components (the collection's members) ── --}}
        <div class="{{ $panel }} p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <p class="text-xs font-bold uppercase tracking-[.12em] text-gray-400">Components <span class="text-gray-300 dark:text-gray-600">({{ $members->count() }})</span></p>
                <div class="flex items-center gap-2" x-data="{ pick: '' }">
                    <input wire:model.live.debounce.300ms="memberSearch" type="text" placeholder="Search components…"
                           class="px-2.5 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200 placeholder-gray-400 w-40">
                    <select x-model="pick" class="px-2.5 py-1.5 text-xs rounded-lg bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-700 dark:text-gray-200">
                        <option value="">Add a component…</option>
                        @foreach($available as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <button x-on:click="if(pick){ $wire.addComponent(pick); pick='' }"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--primary)">Add</button>
                </div>
            </div>
            @if($available->isEmpty() && $memberSearch !== '')
                <p class="text-[11px] text-gray-400 mb-2">No standalone components match "{{ $memberSearch }}".</p>
            @endif

            @if($members->isEmpty())
                <p class="text-xs text-gray-400">No components yet — add existing ones above, or set a component's collection on the Components page.</p>
            @else
                <div class="space-y-1.5">
                    @foreach($members as $i => $m)
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-100 dark:border-white/[0.06]">
                        <div class="flex flex-col leading-none">
                            <button wire:click="moveComponent('{{ $m->id }}', -1)" @if($i === 0) disabled @endif class="text-gray-400 hover:text-gray-700 disabled:opacity-30">▲</button>
                            <button wire:click="moveComponent('{{ $m->id }}', 1)" @if($i === $members->count() - 1) disabled @endif class="text-gray-400 hover:text-gray-700 disabled:opacity-30">▼</button>
                        </div>
                        <span class="flex-1 text-sm text-gray-800 dark:text-gray-200 truncate">{{ $m->name }}
                            <span class="text-xs text-gray-400">· {{ $m->nodes_count }} {{ Str::plural('node', $m->nodes_count) }}</span>
                        </span>
                        <button wire:click="removeComponent('{{ $m->id }}')" class="text-xs font-semibold text-rose-500 hover:text-rose-600">Remove</button>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
        @endif
    </div>

    @endunless

    {{-- ══ Edit fields: its own page ══ --}}
    @if ($editingFields)
        @php
            $inp = 'w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100';
            $autoOptions = \App\Support\CollectionAutoFields::options($site);
        @endphp
        <x-page-panel :back="$this->pageUrl()" :back-label="'Back to '.$viewing->name">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">Fields · {{ $viewing->name }}</p>
                <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Edit fields</h2>
                <p class="text-[11.5px] text-gray-500 dark:text-gray-400">Choose how each field is entered. Entries keep their values when you change a type.</p>
            </x-slot:header>

            <div class="space-y-3">
                @foreach ($fieldRows as $i => $row)
                    <div wire:key="fr-{{ $i }}-{{ $row['key'] ?: 'new' }}" class="rounded-2xl border border-gray-100 dark:border-white/[0.08] p-3.5 space-y-2.5">
                        <div class="flex items-end gap-2">
                            <label class="block flex-1 min-w-0">
                                <span class="text-[11px] font-bold text-gray-500">Name</span>
                                <input type="text" wire:model="fieldRows.{{ $i }}.label" class="{{ $inp }}" placeholder="e.g. Rating">
                            </label>
                            <span class="flex items-center gap-1 pb-0.5">
                                <button type="button" wire:click="moveFieldRow({{ $i }}, -1)" @disabled($loop->first) class="{{ $iconBtn }} disabled:opacity-30" title="Move up">↑</button>
                                <button type="button" wire:click="moveFieldRow({{ $i }}, 1)" @disabled($loop->last) class="{{ $iconBtn }} disabled:opacity-30" title="Move down">↓</button>
                                <button type="button" wire:click="removeFieldRow({{ $i }})" class="{{ $iconBtn }} hover:!text-red-600" title="Remove field (stored values are kept)">✕</button>
                            </span>
                        </div>
                        @error("fieldRows.$i.label")<p class="text-[11px] text-red-500">{{ $message }}</p>@enderror
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="text-[11px] font-bold text-gray-500">Type</span>
                                <select wire:model.live="fieldRows.{{ $i }}.type" class="{{ $inp }}">
                                    @foreach (\App\Livewire\CollectionDetailPage::FIELD_TYPES as $tk => $tl)<option value="{{ $tk }}">{{ $tl }}</option>@endforeach
                                    {{-- A template's structured field keeps its type (it can't be picked for a new field). --}}
                                    @isset(\App\Livewire\CollectionDetailPage::KEPT_TYPES[$row['type']])<option value="{{ $row['type'] }}">{{ \App\Livewire\CollectionDetailPage::KEPT_TYPES[$row['type']] }}</option>@endisset
                                </select>
                            </label>
                            <label class="flex items-center gap-2 pt-5 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" wire:model="fieldRows.{{ $i }}.required" @disabled(($row['auto'] ?? '') !== '') class="w-4 h-4 accent-[var(--primary)] disabled:opacity-40"> Required
                            </label>
                        </div>
                        @if (in_array($row['type'], ['select', 'radio'], true))
                            <label class="block">
                                <span class="text-[11px] font-bold text-gray-500">Options <span class="font-normal text-gray-400">(separate with commas)</span></span>
                                <input type="text" wire:model="fieldRows.{{ $i }}.options" class="{{ $inp }}" placeholder="Small, Medium, Large">
                            </label>
                            @error("fieldRows.$i.options")<p class="text-[11px] text-red-500">{{ $message }}</p>@enderror
                        @elseif (in_array($row['type'], ['number', 'slider'], true))
                            <div class="grid grid-cols-3 gap-2">
                                <label class="block"><span class="text-[11px] font-bold text-gray-500">Min</span><input type="number" step="any" wire:model="fieldRows.{{ $i }}.min" class="{{ $inp }}" placeholder="{{ $row['type'] === 'slider' ? '0' : 'none' }}"></label>
                                <label class="block"><span class="text-[11px] font-bold text-gray-500">Max</span><input type="number" step="any" wire:model="fieldRows.{{ $i }}.max" class="{{ $inp }}" placeholder="{{ $row['type'] === 'slider' ? '100' : 'none' }}"></label>
                                <label class="block"><span class="text-[11px] font-bold text-gray-500">Step</span><input type="number" step="any" wire:model="fieldRows.{{ $i }}.step" class="{{ $inp }}" placeholder="{{ $row['type'] === 'slider' ? '1' : 'any' }}"></label>
                            </div>
                            @error("fieldRows.$i.max")<p class="text-[11px] text-red-500">{{ $message }}</p>@enderror
                            @error("fieldRows.$i.step")<p class="text-[11px] text-red-500">{{ $message }}</p>@enderror
                            <p class="text-[10.5px] text-gray-400">Step 0.1 or 0.5 allows decimals like 4.5. Leave blank for any number.</p>
                        @endif
                        @if ($row['type'] === 'slug')
                            {{-- A slug is made from other fields of the entry (dates as Y-m-d), unique in this collection --}}
                            <div>
                                <span class="text-[11px] font-bold text-gray-500">Made from</span>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1.5">
                                    @foreach ($fieldRows as $j => $other)
                                        @continue($j === $i || ($other['key'] ?? '') === '' || in_array($other['type'] ?? '', ['slug', 'rows', 'group', 'list', 'images', 'tags', 'json', 'array'], true))
                                        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200 cursor-pointer">
                                            <input type="checkbox" wire:model.live="fieldRows.{{ $i }}.slugFrom" value="{{ $other['key'] }}" class="w-4 h-4 accent-[var(--primary)]"> {{ $other['label'] ?: $other['key'] }}
                                        </label>
                                    @endforeach
                                </div>
                                @error("fieldRows.$i.slugFrom")<p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>@enderror
                                @php $slugFrom = array_values(array_intersect(collect($fieldRows)->pluck('key')->all(), (array) ($row['slugFrom'] ?? []))); @endphp
                                <p class="mt-1.5 text-[10.5px] text-gray-400">
                                    Filled automatically when an entry is saved{{ $slugFrom ? ' — e.g. '.collect($slugFrom)->map(fn ($k) => in_array(collect($fieldRows)->firstWhere('key', $k)['type'] ?? '', ['date', 'datetime'], true) ? '2026-10-18' : Str::slug(collect($fieldRows)->firstWhere('key', $k)['label'] ?? $k))->implode('-') : '' }}.
                                    Lower-case words joined by dashes, dates as year-month-day; a number is added if another entry already has it.
                                </p>
                            </div>
                        @endif
                        {{-- Who fills it: a person, or the system (dates, who, entry number, a Site Property) --}}
                        <div class="grid grid-cols-2 gap-2">
                            @if ($row['type'] === 'slug')
                            <span class="block text-[11px] text-gray-400 pt-5">Filled by the system</span>
                            @else
                            <label class="block">
                                <span class="text-[11px] font-bold text-gray-500">Filled by</span>
                                <select wire:model.live="fieldRows.{{ $i }}.auto" class="{{ $inp }}">
                                    <option value="">A person (typed in)</option>
                                    <optgroup label="The system">
                                        @foreach (\App\Support\CollectionAutoFields::SOURCES as $sk => $sl)<option value="{{ $sk }}">{{ $sl }}</option>@endforeach
                                    </optgroup>
                                    <optgroup label="Site properties & variables">
                                        @foreach (array_diff_key($autoOptions, \App\Support\CollectionAutoFields::SOURCES) as $sk => $sl)<option value="{{ $sk }}">{{ Str::after($sl, 'Site property: ') }}</option>@endforeach
                                    </optgroup>
                                </select>
                            </label>
                            @endif
                            <label class="flex items-center gap-2 pt-5 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" wire:model="fieldRows.{{ $i }}.hidden" class="w-4 h-4 accent-[var(--primary)]"> Hide in edit form
                            </label>
                        </div>
                        @if (($row['auto'] ?? '') !== '' && $row['type'] !== 'slug' && ! str_starts_with($row['auto'], 'slug:'))
                            <p class="text-[10.5px] text-gray-400">Filled automatically whenever an entry is saved — shown read-only in the form.{{ str_starts_with($row['auto'], 'property:') ? ' Always shows the current value from Site Properties.' : '' }}</p>
                        @elseif (! empty($row['hidden']))
                            <p class="text-[10.5px] text-gray-400">Not shown in the edit form. Its value is kept and still appears on the website.</p>
                        @endif
                    </div>
                @endforeach
                <button type="button" wire:click="addFieldRow" class="{{ $btnSolid }} w-full text-sm py-2.5">+ Add field</button>
            </div>

            <x-slot:footer>
                <div class="flex items-center gap-2">
                    <span class="flex-1"></span>
                    <button wire:click="closeFields" class="{{ $btnSolid }} text-sm px-4 py-2.5">Cancel</button>
                    <button wire:click="saveFields" class="inline-flex items-center text-sm font-bold px-5 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">Save fields</button>
                </div>
            </x-slot:footer>
        </x-page-panel>
    @endif

    {{-- ══ An entry's own page: view ⇄ edit, fields in one column ══ --}}
    @if ($panelId !== null)
        <x-page-panel :back="$this->pageUrl()" :back-label="'Back to '.$viewing->name">
            <x-slot:header>
                <p class="text-[11px] font-bold uppercase tracking-[.14em]" style="color:var(--primary)">{{ $panelMode === 'edit' ? ($panelId ? 'Edit entry' : 'New entry') : 'Entry' }} · {{ $viewing->name }}</p>
                <h2 class="text-lg font-extrabold text-gray-900 dark:text-white truncate">{{ $panelItem ? $label($panelItem) : 'New entry' }}</h2>
                @if ($panelItem)
                    <p class="text-[11.5px] text-gray-500 dark:text-gray-400">
                        Created {{ $panelItem->created_at?->format('j M Y, H:i') }} · Updated {{ $panelItem->updated_at?->format('j M Y, H:i') }}
                        · <span class="font-semibold {{ $panelItem->status === 'published' ? 'text-emerald-600' : 'text-gray-500' }}">{{ $panelItem->status === 'published' ? 'Live' : 'Draft' }}</span>
                    </p>
                @endif
            </x-slot:header>

            @if ($panelMode === 'edit')
                {{-- Fields in the same order as view mode (CollectionEntryLayout), judged on the stored entry so nothing jumps while typing. --}}
                @include('livewire.partials.collection-item-form', ['viewing' => $viewing, 'oneColumn' => true, 'rich' => true, 'bare' => true,
                    'orderedFields' => \App\Support\CollectionEntryLayout::sortFields((array) ($viewing->fields ?? []), (array) ($panelItem?->data ?? []), $site)])
            @elseif ($panelItem)
                @php
                    // Group the entry's fields by what they hold, for a readable layout:
                    // media → gallery on top · short values → fact tiles · word lists → chips · long text → blocks · nested → cards.
                    // (App\Support\CollectionEntryLayout — the edit form uses the same order.)
                    $sections = \App\Support\CollectionEntryLayout::sections((array) ($viewing->fields ?? []), (array) ($panelItem->data ?? []), $site);
                    $plain = fn ($v) => trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/h[1-6])\s*/?>#i', "\n", (string) $v))));
                @endphp

                <div class="space-y-5">
                    {{-- Media first: the entry's pictures / video --}}
                    @foreach ($sections['media'] as $m)
                        <section>
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $m['name'] }}</h3>
                            @include('livewire.partials.field-value', ['value' => $m['value'], 'siteId' => $viewing->site_id])
                        </section>
                    @endforeach

                    {{-- Short values as tiles --}}
                    @if ($sections['facts'])
                        <section class="grid grid-cols-2 gap-2.5">
                            @foreach ($sections['facts'] as $fact)
                                @php $fv = $fact['value']; $isLink = is_string($fv) && preg_match('#^(https?://|/)\S+$#', $fv); $isMail = is_string($fv) && filter_var($fv, FILTER_VALIDATE_EMAIL); @endphp
                                <div class="rounded-2xl bg-gray-50 dark:bg-white/[0.04] border border-gray-100 dark:border-white/[0.06] px-3.5 py-2.5 min-w-0 {{ ($isLink && mb_strlen($fv) > 28) ? 'col-span-2' : '' }}">
                                    <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-400">{{ $fact['name'] }}</p>
                                    <p class="mt-0.5 text-[14.5px] font-semibold text-gray-900 dark:text-white break-words">
                                        @if (\App\Models\Collection::isBooleanType($fact['type']) || is_bool($fv))
                                            <span class="{{ filter_var($fv, FILTER_VALIDATE_BOOLEAN) ? 'text-emerald-600' : 'text-gray-500' }}">{{ filter_var($fv, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No' }}</span>
                                        @elseif ($isMail)
                                            <a href="mailto:{{ $fv }}" class="hover:underline" style="color:var(--primary)">{{ $fv }}</a>
                                        @elseif ($isLink)
                                            <a href="{{ $fv }}" target="_blank" rel="noopener" class="hover:underline" style="color:var(--primary)">{{ Str::limit(preg_replace('#^https?://(www\.)?#', '', $fv), 60) }} ↗</a>
                                        @else
                                            {{ $plain($fv) }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </section>
                    @endif

                    {{-- Word lists as chips (tech, tags, features…) --}}
                    @foreach ($sections['chips'] as $c)
                        <section>
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $c['name'] }}</h3>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($c['items'] as $chip)
                                    <span class="text-[12.5px] font-semibold px-3 py-1 rounded-full bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200">{{ $plain($chip) }}</span>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    {{-- Longer text --}}
                    @foreach ($sections['text'] as $t)
                        <section>
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">{{ $t['name'] }}</h3>
                            <p class="text-[14.5px] leading-relaxed text-gray-800 dark:text-gray-100 whitespace-pre-line">{{ $plain($t['value']) }}</p>
                        </section>
                    @endforeach

                    {{-- Nested data (rows / groups) --}}
                    @foreach ($sections['nested'] as $n)
                        <section>
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $n['name'] }}</h3>
                            <div class="text-sm text-gray-800 dark:text-gray-100">@include('livewire.partials.field-value', ['value' => $n['value'], 'siteId' => $viewing->site_id])</div>
                        </section>
                    @endforeach

                    @if ($sections['empty'])
                        <p class="text-[11.5px] text-gray-400 border-t border-gray-100 dark:border-white/[0.06] pt-3">Empty: {{ implode(', ', $sections['empty']) }}</p>
                    @endif
                </div>
            @endif

            <x-slot:footer>
                <div class="flex items-center gap-2">
                    @if ($panelMode === 'edit')
                        <span class="flex-1"></span>
                        <button wire:click="cancelItem" class="{{ $btnSolid }} text-sm px-4 py-2.5">Cancel</button>
                        <button wire:click="saveItem" class="inline-flex items-center text-sm font-bold px-5 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">Save entry</button>
                    @elseif ($panelItem && $canManage)
                        <button wire:click="deleteItem('{{ $panelItem->id }}')" data-confirm="Delete this entry? You can restore it from Deleted entries."
                                class="{{ $btnSolid }} text-sm px-4 py-2.5 !text-rose-600">Delete</button>
                        <span class="flex-1"></span>
                        <a href="{{ $this->pageUrl() }}" wire:navigate class="{{ $btnSolid }} text-sm px-4 py-2.5">Close</a>
                        <a href="{{ $this->entryUrl($panelItem->id, true) }}" wire:navigate class="inline-flex items-center text-sm font-bold px-5 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">Edit</a>
                    @else
                        <span class="flex-1"></span>
                        <button wire:click="closePanel" class="{{ $btnSolid }} text-sm px-4 py-2.5">Close</button>
                    @endif
                </div>
            </x-slot:footer>
        </x-page-panel>
    @endif

    {{-- ══ RIGHT rail ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Used by</h3>
            @forelse ($usedBy as $b)
                <a href="{{ $b['url'] }}" class="block py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $b['name'] }} <span class="font-normal text-gray-400">· {{ $b['role'] }}</span></span>
                    <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $b['summary'] }}</span>
                </a>
            @empty
                <p class="text-[12.5px] text-gray-500">No block shows this collection yet.</p>
            @endforelse
        </div>
        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-1.5">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white">Fields</h3>
                @if ($canManage)<a href="{{ $this->pageUrl('fields') }}" wire:navigate class="text-xs font-bold" style="color:var(--primary)">Edit fields</a>@endif
            </div>
            <div class="flex flex-wrap gap-1.5">
                @forelse ($viewing->fields ?? [] as $f)
                    <span class="text-[11.5px] font-semibold px-2.5 py-1 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-700 dark:text-gray-200">{{ $f['label'] ?? $f['key'] ?? $f['name'] ?? '' }} <span class="font-normal text-gray-400">{{ Str::before(\App\Livewire\CollectionDetailPage::FIELD_TYPES[$f['type'] ?? 'text'] ?? ($f['type'] ?? 'text'), ' (') }}</span></span>
                @empty
                    <p class="text-[12.5px] text-gray-500">None yet.</p>
                @endforelse
            </div>
        </div>
        <div class="{{ $panel }} p-5">
            <h3 class="text-[15px] font-bold text-gray-900 dark:text-white mb-1">Related</h3>
            @foreach ([
                ['All collections', 'fields, pages and settings', route('collections', $site->name)],
                ['Edit site', 'blocks that show these entries', url($site->name.'/connect')],
                ['Assets', 'images for your entries', url($site->name.'/media')],
            ] as [$rl, $rd, $ru])
                <a href="{{ $ru }}" wire:navigate class="flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $rd }}</span>
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>

    {{-- Asset library dialog for the entry editor's photo fields --}}
    <livewire:media-picker :site-id="$site->id" />
    {{-- Aa / Rich editor for text fields in the side panel --}}
    @include('livewire.partials.olx-rich-assets')
</x-tri-layout>
