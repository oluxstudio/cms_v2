<div class="main-body p-6 space-y-6">

    <x-page-heading segment="collections" />

    {{-- ── Summary Tiles — app tile theme ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-tile accent="ink" :value="$total" label="Total collections" sub="all types" />
        <x-tile accent="lime" :value="$types" label="Types" sub="distinct kinds" />
        <x-tile accent="lavender" :value="$collections->where('type','list')->count()" label="List type" sub="list collections" />
        <x-tile accent="cocoa" :value="$recent" label="Added this week" sub="last 7 days" />
    </div>

    {{-- ── Table Card ── --}}
    <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] overflow-hidden">

        <div class="flex flex-wrap items-center gap-3 p-5 border-b border-gray-100 dark:border-white/[0.05]">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <x-field.text wire:model.live="search" placeholder="Search collections…"
                              class="w-full sm:w-64" style="padding-left:2.25rem" />
            </div>
            <div class="ml-auto flex items-center gap-3">
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $collections->count() }} result{{ $collections->count() !== 1 ? 's' : '' }}</span>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
                <button wire:click="openCreate"
                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                               text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Collection
                </button>
            </div>
        </div>

        @php
            $typeClassFor = fn ($t) => match($t) {
                'grid'  => 'bg-violet-100 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400',
                'table' => 'bg-orange-100 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400',
                default => 'bg-blue-100 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400',
            };
        @endphp

        @if($viewMode === 'grid')
            {{-- ── Grid layout (cards) ── --}}
            @if($collections->isEmpty())
                <div class="px-5 py-16 text-center">
                    <p class="text-sm text-gray-400 dark:text-gray-500">No collections found</p>
                    <button wire:click="openCreate" class="mt-3 text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Create the first collection</button>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
                    @foreach($collections as $collection)
                        <div class="group flex flex-col bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="p-5 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="w-11 h-11 rounded-xl bg-blue-100 dark:bg-blue-500/10 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeClassFor($collection->type) }}">{{ ucfirst($collection->type) }}</span>
                                </div>
                                <p class="mt-3 text-base font-bold text-gray-900 dark:text-white">{{ $collection->name }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ $collection->description ?: 'No description' }}</p>
                                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Created {{ $collection->created_at->format('M d, Y') }}</p>
                            </div>
                            <div class="flex items-center justify-end gap-1 px-3 py-2.5 border-t border-gray-50 dark:border-white/[0.04]">
                                <button wire:click="viewEntries('{{ $collection->id }}')" class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors" title="View entries">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                </button>
                                <button wire:click="openEdit('{{ $collection->id }}')" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="deleteCollection('{{ $collection->id }}')" data-confirm="Delete this collection and all its entries?" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            {{-- ── List & Compact (table; compact drops Description/Created) ── --}}
            @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-5 py-3.5'; @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Name</th>
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Type</th>
                            @unless($compact)
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Description</th>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Created</th>
                            @endunless
                            <th class="w-24 px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @forelse($collections as $collection)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors group">
                                <td class="{{ $pad }} font-medium text-gray-900 dark:text-white">{{ $collection->name }}</td>
                                <td class="{{ $pad }}">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeClassFor($collection->type) }}">
                                        {{ ucfirst($collection->type) }}
                                    </span>
                                </td>
                                @unless($compact)
                                    <td class="{{ $pad }} text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $collection->description ?? '—' }}</td>
                                    <td class="{{ $pad }} text-gray-400 dark:text-gray-500 text-xs whitespace-nowrap">{{ $collection->created_at->format('M d, Y') }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
                                        <button wire:click="viewEntries('{{ $collection->id }}')"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors" title="View entries">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        </button>
                                        <button wire:click="openEdit('{{ $collection->id }}')"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button wire:click="deleteCollection('{{ $collection->id }}')" data-confirm="Delete this collection and all its entries?"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors" title="Delete">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-16 text-center">
                                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">No collections found</p>
                                    <button wire:click="openCreate" class="mt-3 text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Create the first collection</button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Create / Edit Modal ── --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
        <div class="relative bg-white dark:bg-[#1e1f2b] rounded-2xl shadow-2xl w-full max-w-lg
                    border border-gray-200 dark:border-white/[0.08] p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ $editingId ? 'Edit Collection' : 'New Collection' }}
                </h2>
                <button wire:click="$set('showModal', false)"
                        class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <x-field.text label="Collection Name" model="name" placeholder="e.g. Blog Posts" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-field.select label="Display Type" model="type" :empty="null"
                                    :options="['list' => 'List', 'grid' => 'Grid', 'table' => 'Table']" />
                    @error('type') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-field.textarea label="Description (optional)" model="description" rows="3"
                                      placeholder="What is this collection for?" class="resize-none" />
                    @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-2">
                    <x-field.check text="Allow visitor submissions" wire:model.live="allowSubmit"
                                   hint="Visitors on the client site can add items through the public API." />
                    @if ($allowSubmit)
                        <x-field.check model="autoPublish" text="Auto-publish submissions"
                                       hint="Off (recommended): new submissions are held as pending until you approve them here." />
                    @endif
                </div>
            </div>
            <div class="flex gap-3 pt-1">
                <button wire:click="$set('showModal', false)"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 dark:border-white/[0.08]
                               text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">
                    Cancel
                </button>
                <button wire:click="save"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm">
                    {{ $editingId ? 'Save Changes' : 'Create Collection' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Entries Modal ── --}}
    @if($viewing)
    @php $cols = collect($viewing->fields ?? [])->take(6); @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="closeEntries"></div>
        <div class="relative bg-white dark:bg-[#1e1f2b] rounded-2xl shadow-2xl w-full max-w-3xl max-h-[80vh] overflow-hidden flex flex-col
                    border border-gray-200 dark:border-white/[0.08]">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-white/[0.05]">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $viewing->name }} — entries</h2>
                    <p class="text-xs text-gray-400">{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if(!empty($viewing->fields))
                    <button wire:click="openItem" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--primary)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add entry
                    </button>
                    @endif
                    <button wire:click="closeEntries" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Entry editor — built from the collection's field schema --}}
            @if($editingItemId !== null)
            <div class="p-5 border-b border-gray-100 dark:border-white/[0.05] bg-gray-50/70 dark:bg-white/[0.02]">
                <p class="text-xs font-bold uppercase tracking-[.12em] text-gray-400 mb-3">{{ $editingItemId ? 'Edit entry' : 'New entry' }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach(($viewing->fields ?? []) as $f)
                    @php $key = $f['key']; $ftype = $f['type'] ?? 'text'; @endphp
                    <div class="{{ $ftype === 'textarea' ? 'sm:col-span-2' : '' }}">
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">{{ $f['label'] ?? $key }}</label>
                        @if($ftype === 'textarea')
                            <textarea wire:model="itemForm.{{ $key }}" rows="2" class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 resize-none"></textarea>
                        @elseif($ftype === 'select' && !empty($f['options']))
                            <select wire:model="itemForm.{{ $key }}" class="w-full px-3 py-2 pr-7 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                <option value="">—</option>
                                @foreach($f['options'] as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                        @else
                            <input wire:model="itemForm.{{ $key }}" type="{{ in_array($ftype, ['number','url','date','email']) ? $ftype : 'text' }}" class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="flex gap-2 mt-3">
                    <button wire:click="saveItem" class="px-4 py-2 rounded-lg text-xs font-semibold text-white" style="background:var(--primary)">Save entry</button>
                    <button wire:click="cancelItem" class="px-4 py-2 rounded-lg text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300">Cancel</button>
                </div>
            </div>
            @endif
            <div class="overflow-auto p-2">
                @if($cols->isEmpty())
                    <p class="p-6 text-sm text-gray-400 text-center">This collection has no fields defined.</p>
                @elseif($entries->isEmpty())
                    <p class="p-6 text-sm text-gray-400 text-center">No entries yet.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                                @foreach($cols as $f)
                                    <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-3 py-2">{{ $f['label'] ?? $f['key'] }}</th>
                                @endforeach
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            @foreach($entries as $item)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    @foreach($cols as $f)
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200 align-top max-w-[200px] truncate">{{ data_get($item->data, $f['key']) ?: '—' }}</td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <button wire:click="openItem('{{ $item->id }}')"
                                                class="p-1 rounded text-gray-400 hover:text-indigo-500" title="Edit entry">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="deleteItem('{{ $item->id }}')" data-confirm="Delete this entry?"
                                                class="p-1 rounded text-gray-400 hover:text-red-500" title="Delete entry">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ── Delete Modal ── --}}

</div>
