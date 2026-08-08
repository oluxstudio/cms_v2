@php
    $avatarColors = ['#6366f1','#8b5cf6','#ec4899','#0ea5e9','#10b981','#f97316','#ef4444','#14b8a6'];
    // Closure, not a named function — a view rendered twice in one process
    // (Livewire updates, tests) would fatally redeclare a plain function.
    $siteAvatarColor = fn (string $name) => $avatarColors[ord($name[0]) % count($avatarColors)];
@endphp

<div class="px-10 py-8">

    {{-- ── Header ── --}}
    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase mb-1">Olux CMS</p>
    <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Dashboard</h1>
    <hr class="mt-5 mb-6 border-gray-200">

    {{-- ── Filters + New Site button ── --}}
    <div class="flex items-center gap-2 mb-7">
        @foreach(['all' => 'All', 'active' => 'Active', 'recent' => 'Recent'] as $key => $label)
            <button
                wire:click="setFilter('{{ $key }}')"
                class="px-4 py-1.5 rounded-full text-sm font-medium border transition-all cursor-pointer"
                style="{{ $filter === $key
                    ? 'background:#111827;color:#fff;border-color:#111827;'
                    : 'background:#fff;color:#374151;border-color:#d1d5db;' }}">
                {{ $label }}
            </button>
        @endforeach

        <button wire:click="$set('showCreate', true)"
                class="ml-auto flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium text-white cursor-pointer"
                style="background:var(--primary)">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Site
        </button>
    </div>

    {{-- ── Create modal ── --}}
    @if($showCreate)
    <div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.45)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-gray-900">Add New Site</h2>
                <button wire:click="$set('showCreate', false)" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form wire:submit="create" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Name</label>
                    <input wire:model="form.name" type="text" placeholder="my-site"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none"/>
                    @error('form.name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Domain</label>
                    <input wire:model="form.domain" type="text" placeholder="example.com"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none"/>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Owner</label>
                    <input wire:model="form.owner" type="text" placeholder="Jane Doe"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none"/>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</label>
                    <textarea wire:model="form.description" rows="3" placeholder="Optional…"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showCreate', false)"
                            class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg cursor-pointer"
                            style="background:var(--primary)">
                        Create Site
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ── Cards ── --}}
    @if(count($sites) === 0)
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4" style="background:#f3f4f6">
                @if($search !== '')
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                @else
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                @endif
            </div>
            @if($search !== '')
                <p class="text-gray-700 font-medium text-sm">No results for "{{ $search }}"</p>
                <p class="text-gray-400 text-xs mt-1">Try a different name, domain, or description.</p>
            @else
                <p class="text-gray-500 text-sm">No sites found. Create your first site!</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach($sites as $site)
            @php
                $color    = $siteAvatarColor($site['name']);
                $initials = strtoupper(substr($site['name'], 0, 2));
                $ago      = \Carbon\Carbon::parse($site['created_at'])->diffForHumans();
            @endphp

            <div wire:click="selected('{{ $site['id'] }}')"
                 class="rounded-xl border border-gray-200 bg-white overflow-hidden hover:shadow-md transition-shadow cursor-pointer group">

                {{-- Body --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold shrink-0"
                             style="background:{{ $color }}">
                            {{ $initials }}
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $ago }}
                        </div>
                    </div>

                    <h3 class="text-gray-900 font-semibold text-base leading-snug mb-1 group-hover:text-indigo-600 transition-colors">
                        {{ ucwords(str_replace('-', ' ', $site['name'])) }}
                    </h3>
                    <p class="text-gray-400 text-sm truncate">{{ $site['domain'] }}</p>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-3 flex items-center gap-4 border-t border-gray-100" style="background:#f9fafb">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        {{ $site['pages_count'] }}
                    </div>

                    <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>

                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
                        </svg>
                        {{ $site['components_count'] }}
                    </div>

                    <span class="ml-auto text-xs font-medium px-2.5 py-0.5 rounded-full border border-gray-200 text-gray-500 truncate max-w-[6rem]">
                        {{ $site['owner'] }}
                    </span>

                    <span class="flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg text-white shrink-0"
                          style="background:var(--primary)">
                        Open
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    @endif

</div>
