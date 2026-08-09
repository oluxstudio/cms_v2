@php $canManage = $this->canManage; @endphp
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

    {{-- Header — title + description --}}
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Components</h1>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Standalone content components — build the nodes once, attach to pages or link collections anywhere.</p>
    </div>

    @if ($errorMessage)
        <p class="mb-4 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-sm text-rose-600 dark:text-rose-400">{{ $errorMessage }}</p>
    @endif

    {{-- Stat tiles — app tile theme --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-tile accent="ink" :value="$this->components->count()" label="components" sub="standalone building blocks" />
        <x-tile accent="lime" :value="$this->components->sum(fn ($c) => $c->nodes->count())" label="nodes" sub="typed content fields" />
        <x-tile accent="lavender" :value="$this->components->filter(fn ($c) => $c->pages->isNotEmpty())->count()" label="attached to pages" sub="in use on the site" />
        <x-tile accent="cocoa" :value="$this->components->filter(fn ($c) => $c->nodes->where('type', 'collection')->isNotEmpty())->count()" label="linked to collections" sub="via collection nodes" />
    </div>

    {{-- List card — toolbar (search · results · layout · new) then the list --}}
    <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] overflow-hidden">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3 p-5 {{ count($this->componentTags) ? '' : 'border-b border-gray-100 dark:border-white/[0.05]' }}">
            <div class="relative w-full sm:w-auto">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search components…"
                       class="pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 w-full sm:w-64">
            </div>
            <div class="ml-auto flex items-center gap-3">
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $this->components->count() }} result{{ $this->components->count() !== 1 ? 's' : '' }}</span>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
                @if ($canManage)
                <button wire:click="open(0)"
                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    New component
                </button>
                @endif
            </div>
        </div>

        {{-- Tag filter chips --}}
        @if (count($this->componentTags))
        <div class="flex flex-wrap items-center gap-2 px-5 pb-4 border-b border-gray-100 dark:border-white/[0.05]">
            <button wire:click="setTag('')" @class([
                'px-3 py-1 rounded-full text-xs font-semibold border transition-colors',
                'bg-indigo-600 text-white border-indigo-600' => $filterTag === '',
                'bg-white dark:bg-white/[0.04] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08] hover:border-indigo-400' => $filterTag !== '',
            ])>All</button>
            @foreach ($this->componentTags as $tag)
            <button wire:click="setTag('{{ $tag }}')" @class([
                'px-3 py-1 rounded-full text-xs font-semibold border transition-colors',
                'bg-indigo-600 text-white border-indigo-600' => $filterTag === $tag,
                'bg-white dark:bg-white/[0.04] text-gray-600 dark:text-gray-300 border-gray-200 dark:border-white/[0.08] hover:border-indigo-400' => $filterTag !== $tag,
            ])>#{{ $tag }}</button>
            @endforeach
        </div>
        @endif

        {{-- Body — grid / list / compact --}}
        @if ($this->components->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <span class="text-3xl mb-3">🧩</span>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ ($search !== '' || $filterTag !== '') ? 'No components match.' : 'No components yet.' }}</p>
            <p class="text-xs text-gray-400 mt-1">Create one, define its nodes, then attach it to pages or link a collection.</p>
        </div>

        @elseif ($viewMode === 'grid')
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 p-5">
            @foreach ($this->components as $c)
            <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border {{ $editingId === $c->id ? 'border-indigo-400 ring-2 ring-indigo-500/20' : 'border-gray-100 dark:border-white/[0.05]' }} shadow-sm p-4 flex flex-col">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-white truncate">🧩 {{ $c->name }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        {{ $c->nodes->count() }} {{ Str::plural('node', $c->nodes->count()) }}
                        · {{ $c->pages->count() }} {{ Str::plural('page', $c->pages->count()) }}
                        @if ($c->nodes->where('type', 'collection')->isNotEmpty())
                            · {{ $c->nodes->where('type', 'collection')->count() }} collection {{ Str::plural('link', $c->nodes->where('type', 'collection')->count()) }}
                        @endif
                    </p>
                </div>
                @if ($c->description)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ Str::limit($c->description, 90) }}</p>
                @endif
                @if ($c->tags)
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach ($c->tags as $tag)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">#{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
                <div class="flex flex-wrap gap-1.5 mt-3 flex-1 content-start">
                    @foreach ($c->nodes->take(6) as $n)
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">
                            {{ $n->label }} <span class="opacity-60">· {{ $n->type }}</span></span>
                    @endforeach
                    @if ($c->nodes->count() > 6)<span class="text-[10px] text-gray-400">+{{ $c->nodes->count() - 6 }}</span>@endif
                </div>
                <div class="flex gap-2 mt-4">
                    <button wire:click="view('{{ $c->id }}')"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">View</button>
                    @if ($canManage)
                    <button wire:click="open('{{ $c->id }}')"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">Edit</button>
                    <button wire:click="deleteComponent('{{ $c->id }}')" data-confirm="Delete “{{ $c->name }}”? It is removed from every page it's attached to."
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-400 hover:text-rose-500 transition-colors">Delete</button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

    @else
        {{-- list & compact (table; compact hides description/tags) --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-5 py-3.5'; @endphp
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Component</th>
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Nodes</th>
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Pages</th>
                            @unless($compact)<th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Tags</th>@endunless
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach ($this->components as $c)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                            <td class="{{ $pad }}">
                                <p class="font-semibold text-gray-900 dark:text-white">🧩 {{ $c->name }}</p>
                                @unless($compact)@if($c->description)<p class="text-xs text-gray-400 mt-0.5 max-w-md truncate">{{ $c->description }}</p>@endif @endunless
                            </td>
                            <td class="{{ $pad }} text-gray-500 dark:text-gray-400">{{ $c->nodes->count() }}</td>
                            <td class="{{ $pad }} text-gray-500 dark:text-gray-400">{{ $c->pages->count() }}</td>
                            @unless($compact)
                            <td class="{{ $pad }}">
                                <div class="flex flex-wrap gap-1">
                                    @foreach (($c->tags ?? []) as $tag)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">#{{ $tag }}</span>@endforeach
                                </div>
                            </td>
                            @endunless
                            <td class="{{ $pad }} text-right whitespace-nowrap">
                                <button wire:click="view('{{ $c->id }}')" class="text-xs font-semibold text-gray-500 hover:text-indigo-600 px-2">View</button>
                                @if ($canManage)
                                <button wire:click="open('{{ $c->id }}')" class="text-xs font-semibold text-gray-500 hover:text-indigo-600 px-2">Edit</button>
                                <button wire:click="deleteComponent('{{ $c->id }}')" data-confirm="Delete “{{ $c->name }}”? It is removed from every page it's attached to."
                                        class="text-xs font-semibold text-gray-400 hover:text-rose-500 px-2">Delete</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
        </div>
        @endif

    </div>{{-- /list card --}}

    {{-- ═══ DETAIL VIEW — every stored fact, on the reusable lightbox ═══ --}}
    @if ($viewingId !== null && $this->viewing)
    @php $v = $this->viewing; @endphp
    <x-lightbox close="closeView" icon="🧩" :title="$v->name" :subtitle="$v->description" max-width="max-w-2xl">
        <x-slot:badge>
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full {{ ($v->source ?? 'app') === 'api' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' }}">
                {{ ($v->source ?? 'app') === 'api' ? '🔌 API' : '🖥 App' }}</span>
        </x-slot:badge>

        {{-- Provenance grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach ([
                ['ID', '#'.$v->id, 'font-mono'],
                ['Created by', $v->creator?->name ?? $v->author ?? '—', ''],
                ['Tags', $v->tags ? '#'.implode(' #', $v->tags) : '—', ''],
                ['Created', $v->created_at->format('M j, Y · g:i A'), ''],
                ['Last modified', $v->updated_at->format('M j, Y · g:i A'), ''],
                ['Created via', ($v->source ?? 'app') === 'api' ? 'API call' : 'App interface', ''],
            ] as [$label, $value, $extra])
            <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-3.5 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate {{ $extra }}" title="{{ $value }}">{{ $value }}</p>
            </div>
            @endforeach
        </div>

        {{-- Nodes --}}
        <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mt-5 mb-2">Nodes ({{ $v->nodes->count() }})</p>
        <div class="rounded-xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider text-gray-400 bg-gray-50 dark:bg-white/[0.03]">
                        <th class="px-3.5 py-2 font-bold">Label</th>
                        <th class="px-3.5 py-2 font-bold">Type</th>
                        <th class="px-3.5 py-2 font-bold">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($v->nodes as $n)
                    <tr class="border-t border-gray-50 dark:border-white/[0.04] align-top">
                        <td class="px-3.5 py-2 text-xs font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ $n->label }}</td>
                        <td class="px-3.5 py-2"><span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">{{ $n->type }}</span></td>
                        <td class="px-3.5 py-2 text-xs text-gray-500 dark:text-gray-400 break-all">
                            @if ($n->type === 'collection')
                                {{ $n->linkedCollection()?->name ?? $n->value }} <span class="text-gray-300">(collection)</span>
                            @else
                                {{ Str::limit((string) $n->value, 90) ?: '—' }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Attachments --}}
        <div class="grid sm:grid-cols-2 gap-4 mt-5">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Attached to pages ({{ $v->pages->count() }})</p>
                @forelse ($v->pages as $p)
                    <p class="text-xs text-gray-600 dark:text-gray-300 py-0.5">{{ $p->name }} <span class="font-mono text-gray-400">{{ $p->url }}</span></p>
                @empty
                    <p class="text-xs text-gray-400">Standalone — not on any page.</p>
                @endforelse
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Linked collections</p>
                @forelse ($v->collections() as $col)
                    <p class="text-xs text-gray-600 dark:text-gray-300 py-0.5">{{ $col->name }}</p>
                @empty
                    <p class="text-xs text-gray-400">None.</p>
                @endforelse
            </div>
        </div>

        @if ($canManage)
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button wire:click="closeView" class="px-4 py-2 rounded-xl text-xs font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08]">Close</button>
                <button wire:click="editFromView"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Edit this component</button>
            </div>
        </x-slot:footer>
        @endif
    </x-lightbox>
    @endif

    {{-- ═══ EDITOR ═══ --}}
    @if ($editingId !== null)
    <x-lightbox close="close" icon="🧩" :title="$editingId ? 'Edit component' : 'New component'"
                subtitle="Nodes are the content fields; attach to pages or link a collection node." max-width="max-w-3xl">
            <form wire:submit="save" class="space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Name</label>
                        <input wire:model="cName" type="text" required placeholder="e.g. Hero banner"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                        @error('cName')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Description <span class="font-normal text-gray-400">(optional)</span></label>
                        <input wire:model="cDescription" type="text" placeholder="What is this component for?"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Tags <span class="font-normal text-gray-400">— comma separated; used to filter the page picker</span></label>
                        <input wire:model="cTags" type="text" placeholder="e.g. hero, marketing, footer"
                               class="w-full px-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                    </div>
                </div>

                {{-- ── Nodes ── --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Nodes — the component's content fields</p>
                    <div class="space-y-2">
                        @foreach ($nodes as $i => $n)
                        <div class="flex flex-wrap items-start gap-2 rounded-xl bg-gray-50 dark:bg-white/[0.04] p-2.5" wire:key="node-{{ $i }}">
                            <input wire:model="nodes.{{ $i }}.label" type="text" placeholder="Label (e.g. Heading)"
                                   class="flex-1 min-w-[130px] px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            <select wire:model.live="nodes.{{ $i }}.type"
                                    class="pr-7 pl-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                @foreach ($this->nodeTypes as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                            </select>
                            @if (($n['type'] ?? 'text') === 'collection')
                                <select wire:model="nodes.{{ $i }}.value"
                                        class="flex-1 min-w-[140px] pr-7 pl-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                    <option value="">— link a collection —</option>
                                    @foreach ($this->siteCollections as $col)<option value="{{ $col->id }}">{{ $col->name }}</option>@endforeach
                                </select>
                            @elseif (($n['type'] ?? 'text') === 'boolean')
                                <select wire:model="nodes.{{ $i }}.value"
                                        class="px-3 py-2 pr-7 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                                    <option value="1">True</option><option value="0">False</option>
                                </select>
                            @elseif (($n['type'] ?? 'text') === 'image')
                                <x-asset-picker model="nodes.{{ $i }}.value" :site="$site" type="image" placeholder="Image URL or pick from assets" />
                            @elseif (($n['type'] ?? 'text') === 'url')
                                <x-asset-picker model="nodes.{{ $i }}.value" :site="$site" type="" placeholder="URL or pick any asset" />
                            @else
                                <input wire:model="nodes.{{ $i }}.value" type="text" placeholder="Value"
                                       class="flex-1 min-w-[140px] px-3 py-2 text-sm rounded-lg bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100">
                            @endif
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" wire:click="moveNode({{ $i }}, -1)" title="Move up" class="w-7 h-7 rounded-lg text-gray-400 hover:text-indigo-500 hover:bg-white dark:hover:bg-white/[0.06]">↑</button>
                                <button type="button" wire:click="moveNode({{ $i }}, 1)" title="Move down" class="w-7 h-7 rounded-lg text-gray-400 hover:text-indigo-500 hover:bg-white dark:hover:bg-white/[0.06]">↓</button>
                                <button type="button" wire:click="removeNode({{ $i }})" title="Remove" class="w-7 h-7 rounded-lg text-gray-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10">✕</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addNode"
                            class="mt-2 w-full py-2 rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-400 hover:text-indigo-500 hover:border-indigo-300 transition-colors">+ Add node</button>
                </div>

                {{-- ── Attach to pages ── --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-gray-400 mb-2">Attached to pages <span class="font-normal normal-case tracking-normal text-gray-400">— optional; a component can stand alone</span></p>
                    @if ($this->sitePages->isEmpty())
                        <p class="text-xs text-gray-400">This site has no pages yet.</p>
                    @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->sitePages as $p)
                        <label class="flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer select-none text-xs font-semibold transition-colors
                                      {{ in_array((string) $p->id, array_map('strval', $pageIds), true) ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300' : 'border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400' }}">
                            <input type="checkbox" wire:model.live="pageIds" value="{{ $p->id }}" class="hidden">
                            {{ $p->name }} <span class="font-mono font-normal opacity-60">{{ $p->url }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" wire:click="close" class="px-4 py-2 rounded-xl text-sm font-medium text-gray-500 border border-gray-200 dark:border-white/[0.08]">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Save component</button>
                </div>
            </form>
    </x-lightbox>
    @endif
</div>
