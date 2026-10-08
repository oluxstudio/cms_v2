{{-- A collection shown as a grid of its entries inside a component panel.
     Vars: $card (id, name, count, total, shown, filtered, summary, fields, items[id,label,img]), $title (heading).
     Inside a component the grid previews exactly what the block shows; the
     "Items in this block" form edits that selection (edit.queries.{id}). --}}
@php
    $sourceUrl = route('collections.show', [$site->name, $card['id']]);
    $hasQuery = isset($edit['queries'][$card['id']]);
    $qp = 'edit.queries.'.$card['id'];
    $q = $edit['queries'][$card['id']] ?? [];
    $fp = 'edit.fills.'.$card['id'];
    $fill = $edit['fills'][$card['id']] ?? [];
    $fieldLabel = fn ($k) => \Illuminate\Support\Str::headline($k);
@endphp
@assets
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
@endassets
<div class="rounded-xl border border-gray-100 dark:border-white/[0.06] p-2.5" data-collection-card="{{ $card['id'] }}">
    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $title }} ({{ $card['count'] }})</p>
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 mt-0.5 mb-1">
        @isset($card['summary'])
            <p class="min-w-0 text-[10.5px] {{ ($card['filtered'] ?? false) ? 'font-semibold' : 'text-gray-400' }}" @if ($card['filtered'] ?? false) style="color:var(--primary)" @endif>{{ $card['summary'] }}</p>
        @endisset
        <span class="flex items-center gap-2.5 shrink-0 ml-auto">
            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener"
               class="text-xs font-semibold" style="color:var(--primary)" title="Open {{ $card['name'] }} on the Collections page (new tab)">Source ↗</a>
            <button wire:click="addToLinkedCollection('{{ $card['id'] }}')"
                    class="text-xs font-semibold" style="color:var(--primary)" title="Add a new entry to this list">+ Add</button>
            <button wire:click="select('collection', '{{ $card['id'] }}')"
                    class="text-xs font-semibold text-gray-500 dark:text-gray-400" title="Edit every entry here in the panel">Edit here</button>
        </span>
    </div>

    @if ($card['items'])
        {{-- Vertical list. In a block: drag ⠿ (or ↑ ↓) to reorder, ✕ to remove from this block only. --}}
        <ul class="mt-1.5 space-y-1"
            @if ($hasQuery)
                x-data
                x-init="
                    const boot = () => {
                        if (! window.Sortable) return setTimeout(boot, 120);
                        if ($el._olxSortable) return;
                        $el._olxSortable = window.Sortable.create($el, {
                            handle: '[data-drag]', animation: 140, ghostClass: 'opacity-40',
                            onEnd: (e) => {
                                if (e.oldIndex === e.newIndex) return;
                                const ids = [...$el.querySelectorAll(':scope > li[data-id]')].map(li => li.dataset.id);
                                // Put the row back — Livewire re-renders the saved order.
                                $el.insertBefore(e.item, $el.children[e.oldIndex + (e.oldIndex > e.newIndex ? 1 : 0)] || null);
                                $wire.blockReorder('{{ $card['id'] }}', ids);
                            },
                        });
                    };
                    boot();"
            @endif>
            @foreach ($card['items'] as $ci => $entry)
                <li wire:key="cc-{{ $card['id'] }}-{{ $entry['id'] }}" data-id="{{ $entry['id'] }}"
                    class="group flex items-center gap-2 rounded-lg border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-white/[0.03] px-1.5 py-1 hover:border-indigo-300 dark:hover:border-indigo-500/40 transition-colors">
                    @if ($hasQuery)
                        <span data-drag class="cursor-grab active:cursor-grabbing select-none text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 px-0.5 text-sm leading-none" title="Drag to reorder in this block">⠿</span>
                    @endif
                    @if ($entry['img'])
                        <img src="{{ $entry['img'] }}" alt="" class="w-7 h-7 rounded-md object-cover shrink-0" onerror="this.style.display='none'">
                    @endif
                    <a href="{{ $sourceUrl.'/entries/'.$entry['id'] }}" target="_blank" rel="noopener"
                       class="min-w-0 flex-1 text-[12px] font-semibold text-gray-700 dark:text-gray-200 truncate hover:underline"
                       title="Open “{{ $entry['label'] }}” in {{ $card['name'] }} (new tab)">{{ $entry['label'] }}</a>
                    @if ($hasQuery)
                        <span class="flex items-center shrink-0">
                            <button type="button" wire:click="blockMove('{{ $card['id'] }}', '{{ $entry['id'] }}', -1)" @disabled($loop->first)
                                    class="w-6 h-6 text-[12px] font-bold text-gray-400 hover:text-gray-900 dark:hover:text-white disabled:opacity-25" title="Move up in this block">↑</button>
                            <button type="button" wire:click="blockMove('{{ $card['id'] }}', '{{ $entry['id'] }}', 1)" @disabled($loop->last && ($card['shown'] ?? 0) <= count($card['items']))
                                    class="w-6 h-6 text-[12px] font-bold text-gray-400 hover:text-gray-900 dark:hover:text-white disabled:opacity-25" title="Move down in this block">↓</button>
                            <button type="button" wire:click="{{ ($card['pickMode'] ?? false) ? 'blockUnpick' : 'blockHide' }}('{{ $card['id'] }}', '{{ $entry['id'] }}')"
                                    class="w-6 h-6 text-[12px] font-bold text-gray-400 hover:text-rose-600" title="Remove from this block (stays in {{ $card['name'] }})">✕</button>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($hasQuery && ! ($card['manual'] ?? true))
            <p class="mt-1 text-[10px] text-gray-400">Sorted automatically — dragging or ↑ ↓ switches this block to your own order.</p>
        @endif
        @if (($card['shown'] ?? 0) > count($card['items']))
            <p class="mt-1 text-[10px] text-gray-400">+ {{ $card['shown'] - count($card['items']) }} more</p>
        @endif
    @elseif ($card['pickMode'] ?? false)
        <p class="mt-1 text-[10.5px] text-gray-400">No entries chosen yet — add them from {{ $card['name'] }} below.</p>
    @elseif (($card['total'] ?? 0) > 0)
        <p class="mt-1 text-[10.5px] text-amber-600">No entries match these settings — this block will be empty.</p>
    @else
        <p class="mt-1 text-[10.5px] text-gray-400">No entries yet — use + Add.</p>
    @endif

    {{-- Choose this block's entries from its source (pick mode) --}}
    @if ($hasQuery)
        @if ($card['pickMode'] ?? false)
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($card['available'] ?? [])
                    <select x-data x-on:change="if ($event.target.value) { $wire.blockPick('{{ $card['id'] }}', $event.target.value); $event.target.value = '' }"
                            class="min-w-0 flex-1 rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.04] px-2 py-1.5 text-[12px] text-gray-700 dark:text-gray-200">
                        <option value="">+ Add from {{ $card['name'] }}…</option>
                        @foreach ($card['available'] as $av)<option value="{{ $av['id'] }}">{{ $av['label'] }}</option>@endforeach
                    </select>
                @else
                    <span class="text-[10.5px] text-gray-400">Every entry of {{ $card['name'] }} is in this block.</span>
                @endif
                <button type="button" wire:click="blockPickMode('{{ $card['id'] }}', false)"
                        class="text-[10.5px] font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 underline"
                        title="Stop choosing — show {{ $card['name'] }} by order, search and filters">Show by filters instead</button>
            </div>
        @else
            <button type="button" wire:click="blockPickMode('{{ $card['id'] }}', true)"
                    class="mt-2 w-full rounded-lg border border-dashed border-gray-300 dark:border-white/15 px-2 py-1.5 text-[11.5px] font-semibold hover:border-[var(--primary)]"
                    style="color:var(--primary)" title="Pick exactly which {{ $card['name'] }} entries this block shows">Choose entries from {{ $card['name'] }}</button>
        @endif
    @endif

    @if ($hasQuery && isset($edit['fills'][$card['id']]))
        {{-- Add entries by rule: CollectionQuery::select() → this block's own sortable list --}}
        @php $fff = $fill['filter_field'] ?? ''; $fillList = 'olx-fill-vals-'.$card['id']; @endphp
        <div x-data="{ open: false }" class="mt-2 rounded-lg border border-dashed border-gray-200 dark:border-white/[0.1] px-2 py-1.5">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-[11px] font-bold" style="color:var(--primary)">
                <span>＋ Add entries by rule</span>
                <span class="text-gray-400" x-text="open ? '−' : '+'"></span>
            </button>
            <div x-show="open" x-cloak class="mt-2 space-y-2">
                <div class="grid grid-cols-[1fr_4.5rem] gap-2">
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Take</span>
                        <select wire:model.live="{{ $fp }}.take" class="olx-in">
                            <option value="first">The first</option>
                            <option value="last">The last</option>
                            <option value="random">A random</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">How many</span>
                        <input type="number" min="1" max="100" placeholder="All" wire:model.live.debounce.400ms="{{ $fp }}.limit" class="olx-in">
                    </label>
                </div>
                @if ($card['fields'] ?? [])
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Where the column</span>
                        <select wire:model.live="{{ $fp }}.filter_field" class="olx-in">
                            <option value="">— any entry —</option>
                            @foreach ($card['fields'] as $fk)<option value="{{ $fk }}">{{ $fieldLabel($fk) }}</option>@endforeach
                        </select>
                    </label>
                    @if ($fff !== '')
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2">
                            <select wire:model.live="{{ $fp }}.filter_op" class="olx-in">
                                <option value="is">is exactly</option>
                                <option value="contains">contains</option>
                            </select>
                            <input type="text" placeholder="value, e.g. Men" list="{{ $fillList }}" wire:model.live.debounce.400ms="{{ $fp }}.filter_value" class="olx-in">
                        </div>
                        @if ($card['values'][$fff] ?? [])
                            <datalist id="{{ $fillList }}">@foreach ($card['values'][$fff] as $v)<option value="{{ $v }}"></option>@endforeach</datalist>
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($card['values'][$fff], 0, 12) as $v)
                                    @php $on = mb_strtolower($fill['filter_value'] ?? '') === mb_strtolower($v); @endphp
                                    <button type="button" wire:click="$set('{{ $fp }}.filter_value', @js($v))"
                                            class="px-2 py-0.5 rounded-full text-[10.5px] font-semibold border {{ $on ? 'text-white border-transparent' : 'text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.1]' }}"
                                            @if ($on) style="background:var(--primary)" @endif>{{ $v }}</button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endif
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Search</span>
                        <input type="search" placeholder="any field contains…" wire:model.live.debounce.400ms="{{ $fp }}.search" class="olx-in">
                    </label>
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Sorted by</span>
                        <select wire:model.live="{{ $fp }}.sort" class="olx-in">
                            <option value="manual">{{ $card['name'] }}’s order</option>
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            @if ($card['fields'] ?? [])<option value="field">A field…</option>@endif
                        </select>
                    </label>
                </div>
                @if (($fill['sort'] ?? '') === 'field' && ($card['fields'] ?? []))
                    <div class="grid grid-cols-2 gap-2">
                        <select wire:model.live="{{ $fp }}.field" class="olx-in">
                            <option value="">Field…</option>
                            @foreach ($card['fields'] as $fk)<option value="{{ $fk }}">{{ $fieldLabel($fk) }}</option>@endforeach
                        </select>
                        <select wire:model.live="{{ $fp }}.dir" class="olx-in">
                            <option value="asc">A → Z</option>
                            <option value="desc">Z → A</option>
                        </select>
                    </div>
                @endif
                @php
                    $matches = $this->fillMatches($card['id']);
                    $n = (int) ($fill['limit'] ?? 0);
                    $will = $n > 0 ? min($n, $matches) : $matches;
                    $takeWord = ['first' => 'first', 'last' => 'last', 'random' => 'random'][$fill['take'] ?? 'first'] ?? 'first';
                @endphp
                <p class="text-[10.5px] {{ $matches ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600' }}">
                    @if ($matches)
                        {{ $matches }} {{ \Illuminate\Support\Str::plural('entry', $matches) }} match → adds the <b>{{ $takeWord }} {{ $will }}</b>.
                    @else
                        No {{ $card['name'] }} entries match this rule.
                    @endif
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="blockFill('{{ $card['id'] }}', 'replace')" @disabled(! $matches)
                            class="px-3 py-1.5 rounded-lg text-[11.5px] font-bold disabled:opacity-40" style="background:var(--primary);color:var(--on-primary,#fff)"
                            title="This block shows exactly these entries">Replace the list</button>
                    <button type="button" wire:click="blockFill('{{ $card['id'] }}', 'append')" @disabled(! $matches)
                            class="px-3 py-1.5 rounded-lg text-[11.5px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 disabled:opacity-40"
                            title="Keep what's there and add these at the end">Add to the list</button>
                </div>
                <p class="text-[10px] text-gray-400">The result becomes this block’s own list — drag ⠿ to reorder, ✕ to remove. {{ $card['name'] }} itself isn’t changed. Saved with “Save component”.</p>
            </div>
        </div>
    @endif

    @if ($hasQuery && ! ($card['pickMode'] ?? false) && (($card['hidden'] ?? []) || ($card['customOrder'] ?? false)))
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10.5px]">
            @foreach ($card['hidden'] ?? [] as $h)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-white/[0.06] pl-2 pr-1 py-0.5 text-gray-600 dark:text-gray-300" wire:key="ch-{{ $card['id'] }}-{{ $h['id'] }}">
                    <span class="line-through truncate max-w-[7rem]">{{ $h['label'] }}</span>
                    <button type="button" wire:click="blockUnhide('{{ $card['id'] }}', '{{ $h['id'] }}')" class="font-bold" style="color:var(--primary)" title="Show in this block again">Show</button>
                </span>
            @endforeach
            @if ($card['customOrder'] ?? false)
                <button type="button" wire:click="blockResetOrder('{{ $card['id'] }}')" class="font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 underline">Reset order</button>
            @endif
        </div>
    @endif

    @if ($hasQuery && ! ($card['pickMode'] ?? false))
        {{-- Which entries THIS block shows — saved with the component. --}}
        <div x-data="{ open: {{ ($card['filtered'] ?? false) ? 'true' : 'false' }} }" class="mt-2 border-t border-gray-100 dark:border-white/[0.06] pt-2">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                <span>Items in this block</span>
                <span class="text-gray-400" x-text="open ? '−' : '+'"></span>
            </button>
            <div x-show="open" x-cloak class="mt-2 space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Number to show</span>
                        <span class="flex gap-1">
                            <select wire:model.live="{{ $qp }}.take" class="olx-in !w-auto" title="Which ones">
                                <option value="first">First</option>
                                <option value="last">Last</option>
                                <option value="random">Random</option>
                            </select>
                            <input type="number" min="1" max="100" placeholder="All" wire:model.live.debounce.400ms="{{ $qp }}.limit" class="olx-in min-w-0">
                        </span>
                    </label>
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Sort by</span>
                        <select wire:model.live="{{ $qp }}.sort" class="olx-in">
                            <option value="manual">Manual order</option>
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            @if ($card['fields'] ?? [])<option value="field">A field…</option>@endif
                        </select>
                    </label>
                </div>
                @if (($q['sort'] ?? '') === 'field' && ($card['fields'] ?? []))
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block">
                            <span class="text-[10.5px] text-gray-400">Field</span>
                            <select wire:model.live="{{ $qp }}.field" class="olx-in">
                                <option value="">Choose…</option>
                                @foreach ($card['fields'] as $fk)<option value="{{ $fk }}">{{ $fieldLabel($fk) }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-[10.5px] text-gray-400">Direction</span>
                            <select wire:model.live="{{ $qp }}.dir" class="olx-in">
                                <option value="asc">A → Z / low → high</option>
                                <option value="desc">Z → A / high → low</option>
                            </select>
                        </label>
                    </div>
                @endif
                @php $mode = $q['mode'] ?? 'default'; @endphp
                <label class="block">
                    <span class="text-[10.5px] text-gray-400">Show</span>
                    <select wire:model.live="{{ $qp }}.mode" class="olx-in">
                        <option value="default">The block’s default entries</option>
                        <option value="all">All entries</option>
                        <option value="match">Entries matching a search or column…</option>
                    </select>
                </label>
                @if ($mode === 'match')
                    <label class="block">
                        <span class="text-[10.5px] text-gray-400">Search (any field contains)</span>
                        <input type="search" placeholder="e.g. wedding" wire:model.live.debounce.400ms="{{ $qp }}.search" class="olx-in">
                    </label>
                    @if ($card['fields'] ?? [])
                        @php $listId = 'olx-vals-'.$card['id']; $ff = $q['filter_field'] ?? ''; @endphp
                        <label class="block">
                            <span class="text-[10.5px] text-gray-400">Only where the column</span>
                            <select wire:model.live="{{ $qp }}.filter_field" class="olx-in">
                                <option value="">— any column —</option>
                                @foreach ($card['fields'] as $fk)<option value="{{ $fk }}">{{ $fieldLabel($fk) }}</option>@endforeach
                            </select>
                        </label>
                        <div class="grid grid-cols-[6.5rem_1fr] gap-2">
                            <select wire:model.live="{{ $qp }}.filter_op" class="olx-in" @disabled($ff === '')>
                                <option value="contains">contains</option>
                                <option value="is">is exactly</option>
                            </select>
                            <input type="text" placeholder="{{ $ff === '' ? 'choose a column first' : 'value' }}" list="{{ $listId }}"
                                   wire:model.live.debounce.400ms="{{ $qp }}.filter_value" class="olx-in" @disabled($ff === '')>
                        </div>
                        @if ($ff !== '' && ($card['values'][$ff] ?? []))
                            <datalist id="{{ $listId }}">
                                @foreach ($card['values'][$ff] as $v)<option value="{{ $v }}"></option>@endforeach
                            </datalist>
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($card['values'][$ff], 0, 12) as $v)
                                    <button type="button" wire:click="$set('{{ $qp }}.filter_value', @js($v))"
                                            class="px-2 py-0.5 rounded-full text-[10.5px] font-semibold border {{ mb_strtolower($q['filter_value'] ?? '') === mb_strtolower($v) ? 'text-white border-transparent' : 'text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.1]' }}"
                                            @if (mb_strtolower($q['filter_value'] ?? '') === mb_strtolower($v)) style="background:var(--primary)" @endif>{{ $v }}</button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endif
                <p class="text-[10px] text-gray-400">Applies to this block only — drag ⠿ (or ↑ ↓) to reorder and ✕ to remove entries here without changing {{ $card['name'] }}; other blocks and pages still get every entry. Saved with “Save component”.</p>
            </div>
        </div>
    @endif
</div>
