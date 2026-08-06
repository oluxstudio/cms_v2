<x-page-layout title="Pages" subtitle="Create and manage your site's pages.">

    {{-- ── LEFT: statistical overview ── --}}
    <x-slot:stats>
        <x-stat-tile label="Total Pages" :value="$total" color="#6366f1"
            icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z" />
        <x-stat-tile label="New This Week" :value="$thisWeek" color="#10b981"
            icon="M12 4v16m8-8H4" />
        <x-stat-tile label="Avg Keywords" :value="$avgKeywords" color="#f59e0b"
            icon="M7 7h.01M7 3h5c.5 0 1 .2 1.4.6l7 7a2 2 0 010 2.8l-7 7a2 2 0 01-2.8 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
        <x-stat-tile label="Published" :value="$total"
            :sub="$pages->count().' shown'" color="#0ea5e9"
            icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </x-slot:stats>

    {{-- ── MAIN: the pages table ── --}}
    <div class="bg-white dark:bg-[#1e1f2b] rounded-2xl border border-gray-200 dark:border-white/[0.06] overflow-hidden">

        <div class="flex flex-wrap items-center gap-3 p-5 border-b border-gray-100 dark:border-white/[0.05]">
            <div class="relative w-full sm:w-auto">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input wire:model.live="search" type="text" placeholder="Search pages…"
                       class="pl-9 pr-4 py-2 text-sm rounded-xl w-full sm:w-64
                              bg-gray-50 dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.08]
                              text-gray-900 dark:text-white placeholder-gray-400
                              focus:outline-none focus:ring-2 focus:ring-indigo-500/40"/>
            </div>
            <div class="ml-auto flex items-center gap-3">
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $pages->count() }} result{{ $pages->count() !== 1 ? 's' : '' }}</span>
                <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
                <button wire:click="openCreate"
                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                               text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Page
                </button>
            </div>
        </div>

        @if($viewMode === 'grid')
            {{-- ── Grid layout (cards) ── --}}
            @if($pages->isEmpty())
                <div class="px-5 py-16 text-center">
                    <p class="text-sm text-gray-400 dark:text-gray-500">No pages found</p>
                    <button wire:click="openCreate" class="mt-3 text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Create the first page</button>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
                    @foreach($pages as $page)
                        <div class="group flex flex-col bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="p-5 flex-1">
                                <div class="w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <p class="mt-3 text-base font-bold text-gray-900 dark:text-white">{{ $page->name }}</p>
                                <p class="mt-1 inline-block px-2 py-0.5 rounded-md bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $page->url }}</p>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    @foreach(array_slice(array_filter(array_map('trim', explode(',', $page->keywords))), 0, 4) as $kw)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $kw }}</span>
                                    @endforeach
                                </div>
                                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Created {{ $page->created_at->format('M d, Y') }}</p>
                            </div>
                            <div class="flex items-center gap-1 px-3 py-2.5 border-t border-gray-50 dark:border-white/[0.04]">
                                <a href="{{ url('preview/'.$site->name.'/'.ltrim($page->url,'/')).'?preview=1' }}" target="_blank"
                                   class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors" title="Preview page">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ url($site->name.'/pages/'.$page->id.'/builder') }}"
                                   class="p-1.5 rounded-lg text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-500/10 transition-colors" title="Page Builder">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                                </a>
                                <div class="ml-auto flex items-center gap-1">
                                    <button wire:click="openPicker({{ $page->id }})"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors" title="Components ({{ $page->components()->count() }})">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a1 1 0 112 0v1h3a1 1 0 011 1v3h1a1 1 0 110 2h-1v3a1 1 0 01-1 1h-3v1a1 1 0 11-2 0v-1H8a1 1 0 01-1-1v-3H6a1 1 0 110-2h1V6a1 1 0 011-1h3V4z"/>
                                        </svg>
                                    </button>
                                    <button wire:click="openEdit({{ $page->id }})" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors" title="Edit metadata">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button wire:click="deletePage({{ $page->id }})" data-confirm="Delete this page and all its blocks?" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            {{-- ── List & Compact (table; compact drops Keywords/Created and tightens rows) ── --}}
            @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-5 py-3.5'; @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Name</th>
                            <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">URL</th>
                            @unless($compact)
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Keywords</th>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-5 py-3">Created</th>
                            @endunless
                            <th class="w-24 px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @forelse($pages as $page)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors group">
                                <td class="{{ $pad }} font-medium text-gray-900 dark:text-white">{{ $page->name }}</td>
                                <td class="{{ $pad }} text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $page->url }}</td>
                                @unless($compact)
                                    <td class="{{ $pad }}">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice(array_filter(array_map('trim', explode(',', $page->keywords))), 0, 3) as $kw)
                                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-300">{{ $kw }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="{{ $pad }} text-gray-400 dark:text-gray-500 text-xs whitespace-nowrap">{{ $page->created_at->format('M d, Y') }}</td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
                                        <a href="{{ url('preview/'.$site->name.'/'.ltrim($page->url,'/')).'?preview=1' }}" target="_blank"
                                           class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors" title="Preview page">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ url($site->name.'/pages/'.$page->id.'/builder') }}"
                                           class="p-1.5 rounded-lg text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-500/10 transition-colors" title="Page Builder">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                            </svg>
                                        </a>
                                        <button wire:click="openPicker({{ $page->id }})"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors" title="Components ({{ $page->components()->count() }})">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a1 1 0 112 0v1h3a1 1 0 011 1v3h1a1 1 0 110 2h-1v3a1 1 0 01-1 1h-3v1a1 1 0 11-2 0v-1H8a1 1 0 01-1-1v-3H6a1 1 0 110-2h1V6a1 1 0 011-1h3V4z"/>
                                            </svg>
                                        </button>
                                        <button wire:click="openEdit({{ $page->id }})"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors" title="Edit metadata">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button wire:click="deletePage({{ $page->id }})" data-confirm="Delete this page and all its blocks?"
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
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="text-sm text-gray-400 dark:text-gray-500">No pages found</p>
                                    <button wire:click="openCreate" class="mt-3 text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Create the first page</button>
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
                    {{ $editingId ? 'Edit Page' : 'New Page' }}
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
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Page Name</label>
                    <input wire:model="form.name" type="text" placeholder="e.g. Home"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl
                                  bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]
                                  text-gray-900 dark:text-white placeholder-gray-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500/50"/>
                    @error('form.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">URL Path</label>
                    <input wire:model="form.url" type="text" placeholder="e.g. /about"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl font-mono
                                  bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]
                                  text-gray-900 dark:text-white placeholder-gray-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500/50"/>
                    @error('form.url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                        Keywords <span class="normal-case font-normal text-gray-400">(comma separated)</span>
                    </label>
                    <input wire:model="form.keywords" type="text" placeholder="e.g. home, landing, hero"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl
                                  bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]
                                  text-gray-900 dark:text-white placeholder-gray-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500/50"/>
                    @error('form.keywords') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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
                    {{ $editingId ? 'Save Changes' : 'Create Page' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    

    {{-- ═══ COMPONENT PICKER — attach components to a page, filter by tag ═══ --}}
    @if ($pickerPageId !== null && $this->pickerPage)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" wire:click="closePicker"></div>
        <div class="relative bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-6">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Components on “{{ $this->pickerPage->name }}”</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Tick to attach, untick to remove — new attachments append at the end of the page.</p>
                </div>
                <button wire:click="closePicker" class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 shrink-0">✕ Close</button>
            </div>

            {{-- Search + tag filter --}}
            <div class="flex flex-wrap items-center gap-2 mt-4 mb-3">
                <div class="relative flex-1 min-w-[180px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input wire:model.live.debounce.250ms="pickerSearch" type="text" placeholder="Search components…"
                           class="w-full pl-9 pr-3 py-2 text-sm rounded-xl bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08] text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                </div>
            </div>
            @if (count($this->componentTags))
            <div class="flex flex-wrap gap-1.5 mb-4">
                <button wire:click="$set('pickerTag', '')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors {{ $pickerTag === '' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 hover:text-indigo-500' }}">All</button>
                @foreach ($this->componentTags as $tag)
                    <button wire:click="$set('pickerTag', '{{ $tag }}')"
                            class="px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors {{ $pickerTag === $tag ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-500 dark:text-gray-400 hover:text-indigo-500' }}">#{{ $tag }}</button>
                @endforeach
            </div>
            @endif

            {{-- Component list --}}
            <div class="space-y-2">
                @forelse ($this->pickerComponents as $comp)
                @php $attached = $this->pickerPage->components()->where('components.id', $comp->id)->exists(); @endphp
                <label class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border cursor-pointer transition-colors
                              {{ $attached ? 'border-indigo-400 bg-indigo-50/60 dark:bg-indigo-500/10' : 'border-gray-100 dark:border-white/[0.06] bg-gray-50 dark:bg-white/[0.03] hover:border-indigo-300' }}">
                    <input type="checkbox" @checked($attached) wire:click="toggleComponent({{ $comp->id }})"
                           class="w-4 h-4 rounded border-gray-300 text-indigo-600 shrink-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">🧩 {{ $comp->name }}</p>
                        <p class="text-[11px] text-gray-400 truncate">
                            {{ $comp->nodes->count() }} {{ Str::plural('node', $comp->nodes->count()) }}
                            @if ($comp->description) · {{ Str::limit($comp->description, 60) }} @endif
                        </p>
                    </div>
                    @if ($comp->tags)
                    <div class="hidden sm:flex flex-wrap gap-1 justify-end max-w-[40%]">
                        @foreach (array_slice($comp->tags, 0, 3) as $tag)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 dark:text-indigo-300 shrink-0">#{{ $tag }}</span>
                        @endforeach
                    </div>
                    @endif
                </label>
                @empty
                <div class="py-10 text-center">
                    <p class="text-sm text-gray-400">No components match{{ $pickerTag !== '' ? ' the #'.$pickerTag.' tag' : '' }}.</p>
                    <a href="{{ url($site->name.'/components') }}" class="mt-2 inline-block text-xs font-semibold text-indigo-500 hover:underline">Create components →</a>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</x-page-layout>
