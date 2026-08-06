@php $canManage = $this->canManage; @endphp
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Components</h1>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">Standalone content components — build the nodes once, attach to pages or link collections anywhere.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search components…"
                       class="pl-9 pr-4 py-2 text-sm rounded-xl bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 w-56">
            </div>
            @if ($canManage)
            <button wire:click="open(0)"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New component
            </button>
            @endif
        </div>
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

    {{-- Components list --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->components as $c)
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
            {{-- Tag chips --}}
            @if ($c->tags)
            <div class="flex flex-wrap gap-1.5 mt-2">
                @foreach ($c->tags as $tag)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">#{{ $tag }}</span>
                @endforeach
            </div>
            @endif
            {{-- Node chips preview --}}
            <div class="flex flex-wrap gap-1.5 mt-3 flex-1 content-start">
                @foreach ($c->nodes->take(6) as $n)
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400">
                        {{ $n->label }} <span class="opacity-60">· {{ $n->type }}</span></span>
                @endforeach
                @if ($c->nodes->count() > 6)<span class="text-[10px] text-gray-400">+{{ $c->nodes->count() - 6 }}</span>@endif
            </div>
            @if ($canManage)
            <div class="flex gap-2 mt-4">
                <button wire:click="open({{ $c->id }})"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-white/[0.08] text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition-colors">Edit</button>
                <button wire:click="deleteComponent({{ $c->id }})" wire:confirm="Delete “{{ $c->name }}”? It is removed from every page it's attached to."
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-400 hover:text-rose-500 transition-colors">Delete</button>
            </div>
            @endif
        </div>
        @empty
        <div class="sm:col-span-2 lg:col-span-3 flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05]">
            <span class="text-3xl mb-3">🧩</span>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ $search !== '' ? 'No components match.' : 'No components yet.' }}</p>
            <p class="text-xs text-gray-400 mt-1">Create one, define its nodes, then attach it to pages or link a collection.</p>
        </div>
        @endforelse
    </div>

    {{-- ═══ EDITOR ═══ --}}
    @if ($editingId !== null)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" wire:click="close"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-3xl max-h-[88vh] overflow-y-auto p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white mb-4">{{ $editingId ? 'Edit component' : 'New component' }}</h2>
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
        </div>
    </div>
    @endif
</div>
