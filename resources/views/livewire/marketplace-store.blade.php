@php
    $money = fn ($cents, $cur = 'gbp') => ($cur === 'gbp' ? '£' : strtoupper($cur).' ').number_format($cents / 100, $cents % 100 ? 2 : 0);
@endphp
<x-tri-layout title="Templates" :site-name="$site->name" subtitle="Templates for all your sites. Add one to your library, then use it on any site."
    :labels="['📊 Overview', '🛍 Store', 'ℹ️ Summary']">

    <x-slot:header>
        <div class="flex items-center gap-1 p-1 rounded-full bg-gray-100 dark:bg-white/[0.05]">
            @foreach (['browse' => 'Browse', 'library' => 'My Library ('.$libraryCount.')'] as $tk => $tl)
                <button wire:click="$set('tab', '{{ $tk }}')"
                        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors
                               {{ $tab === $tk ? 'bg-white dark:bg-[#1d1e2a] text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">{{ $tl }}</button>
            @endforeach
        </div>
    </x-slot:header>

    {{-- ══ LEFT rail: stats + the filter column ══ --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 gap-3">
        <x-tile accent="ink" wide :value="$templates->total()" label="Templates in the store"
                icon="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 10h16" sub="published" />
        <x-tile accent="lime" :value="$libraryCount" label="In your library"
                icon="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5z"
                :sub="$libraryCount ? 'ready to use' : 'none yet'" />
        <x-tile accent="sky" :value="$sites->count()" label="Your sites"
                icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" sub="one licence covers all" />
    </div>

    </x-slot:rail>

{{-- ══ CENTER ══ --}}
<div class="max-w-[52rem] mx-auto">

    @if ($tab === 'browse')
        {{-- search + sort --}}
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <div class="flex-1 min-w-[220px]"><x-field.search model="q" placeholder="Search templates…" /></div>
            <select wire:model.live="sort" class="bkf-input !w-auto">
                <option value="popular">Most popular</option>
                <option value="newest">Newest</option>
                <option value="price_asc">Price: low to high</option>
            </select>
        </div>

        {{-- filter bar: dropdowns under the search bar --}}
        <div class="flex flex-wrap items-center gap-2 mb-3" x-data="{ open: null }" @click.outside="open = null" @keydown.escape.window="open = null">
            @php
                $fbBtn = 'fx inline-flex items-center gap-1.5 min-h-[38px] px-3.5 rounded-xl text-[12.5px] font-bold border bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200';
                $fbCaret = '<svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>';
            @endphp

            <div class="relative">
                <button @click="open = open === 'cat' ? null : 'cat'" class="{{ $fbBtn }} {{ $cats ? 'border-gray-900 dark:border-white' : 'border-gray-200 dark:border-white/[0.1]' }}">
                    Category @if ($cats)<span class="px-1.5 rounded-full text-[10px] text-white" style="background:var(--primary)">{{ count($cats) }}</span>@endif {!! $fbCaret !!}
                </button>
                <div x-show="open === 'cat'" x-cloak class="bkf-panel absolute left-0 top-full mt-2 z-30 w-60 p-3">
                    @foreach ($categories as $cat)
                        <label class="bkf-check py-0.5 w-full"><input type="checkbox" wire:model.live="cats" value="{{ $cat }}"> {{ $cat }}</label>
                    @endforeach
                </div>
            </div>

            @if ($allTags->isNotEmpty())
            <div class="relative">
                <button @click="open = open === 'tag' ? null : 'tag'" class="{{ $fbBtn }} {{ $tagSel ? 'border-gray-900 dark:border-white' : 'border-gray-200 dark:border-white/[0.1]' }}">
                    Tags @if ($tagSel)<span class="px-1.5 rounded-full text-[10px] text-white" style="background:var(--primary)">{{ count($tagSel) }}</span>@endif {!! $fbCaret !!}
                </button>
                <div x-show="open === 'tag'" x-cloak class="bkf-panel absolute left-0 top-full mt-2 z-30 w-64 p-3">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($allTags as $tag)
                            <button wire:click="toggleTag('{{ $tag }}')"
                                    class="fx px-2.5 py-1 rounded-full text-[11px] font-bold border {{ in_array($tag, $tagSel, true) ? 'border-transparent' : 'border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-600 dark:text-gray-300' }}"
                                    @if (in_array($tag, $tagSel, true)) style="background:var(--primary);color:var(--on-primary)" @endif>{{ $tag }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <div class="relative">
                <button @click="open = open === 'cr' ? null : 'cr'" class="{{ $fbBtn }} {{ $creatorSel ? 'border-gray-900 dark:border-white' : 'border-gray-200 dark:border-white/[0.1]' }}">
                    Creator @if ($creatorSel)<span class="px-1.5 rounded-full text-[10px] text-white" style="background:var(--primary)">{{ count($creatorSel) }}</span>@endif {!! $fbCaret !!}
                </button>
                <div x-show="open === 'cr'" x-cloak class="bkf-panel absolute left-0 top-full mt-2 z-30 w-60 p-3">
                    @foreach ($creators as $cr)
                        <label class="bkf-check py-0.5 w-full"><input type="checkbox" wire:model.live="creatorSel" value="{{ $cr->id }}"> {{ $cr->name }}</label>
                    @endforeach
                </div>
            </div>

            <div class="relative">
                <button @click="open = open === 'pr' ? null : 'pr'" class="{{ $fbBtn }} {{ $price !== 'any' ? 'border-gray-900 dark:border-white' : 'border-gray-200 dark:border-white/[0.1]' }}">
                    Price @if ($price !== 'any')<span class="px-1.5 rounded-full text-[10px] text-white" style="background:var(--primary)">{{ ucfirst($price) }}</span>@endif {!! $fbCaret !!}
                </button>
                <div x-show="open === 'pr'" x-cloak class="bkf-panel absolute left-0 top-full mt-2 z-30 w-44 p-3">
                    @foreach (['any' => 'Any', 'free' => 'Free', 'paid' => 'Paid'] as $pv => $pl)
                        <label class="bkf-check py-0.5 w-full"><input type="radio" wire:model.live="price" value="{{ $pv }}"> {{ $pl }}</label>
                    @endforeach
                </div>
            </div>

            @if ($cats || $tagSel || $creatorSel || $price !== 'any' || $q !== '')
                <button wire:click="clearFilters" class="fx min-h-[38px] px-3 rounded-xl text-[12.5px] font-bold text-gray-500 dark:text-gray-400 hover:underline">Clear all</button>
            @endif
        </div>

        {{-- count + active filter chips --}}
        <div class="flex flex-wrap items-center gap-2 mb-4 text-[12.5px] text-gray-500 dark:text-gray-400">
            <span>Showing {{ $templates->count() ? $templates->firstItem().'–'.$templates->lastItem() : 0 }} of {{ $templates->total() }}</span>
            @foreach ([...collect($cats)->map(fn ($c) => ['cats', $c, $c]), ...collect($tagSel)->map(fn ($t) => ['tagSel', $t, $t]),
                       ...collect($creatorSel)->map(fn ($c) => ['creatorSel', (string) $c, $creators->firstWhere('id', (int) $c)?->name ?? $c])] as [$prop, $val, $label])
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 dark:bg-white/[0.08] text-gray-700 dark:text-gray-200">
                    {{ $label }}
                    <button wire:click="removeFilter('{{ $prop }}', '{{ $val }}')" class="text-gray-400 hover:text-rose-500" aria-label="Remove filter">✕</button>
                </span>
            @endforeach
            @if ($price !== 'any')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 dark:bg-white/[0.08] text-gray-700 dark:text-gray-200">
                    {{ ucfirst($price) }} <button wire:click="$set('price', 'any')" class="text-gray-400 hover:text-rose-500">✕</button>
                </span>
            @endif
        </div>

        {{-- grid --}}
        <div wire:loading.class="opacity-50" class="grid sm:grid-cols-2 gap-4">
            @forelse ($templates as $t)
                @php $isOwned = $owned->has($t->id) || $t->user_id === auth()->id(); @endphp
                <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm overflow-hidden flex flex-col">
                    <a href="{{ route('marketplace.template', [$site->name, $t->slug]) }}" class="relative block aspect-square overflow-hidden bg-gray-100 dark:bg-white/[0.05]">
                        @if ($t->thumbnail_url)<img src="{{ $t->thumbnail_url }}" alt="" class="absolute inset-0 w-full h-full object-cover object-top">@endif
                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[11px] font-extrabold {{ $t->isFree() ? 'bg-emerald-500 text-white' : 'bg-[#1c1d29] text-white' }}">
                            {{ $t->isFree() ? 'Free' : $money($t->price_cents, $t->currency) }}
                        </span>
                    </a>
                    <div class="p-4 flex flex-col flex-1">
                        <p class="text-sm font-extrabold text-gray-900 dark:text-white">{{ $t->name }}</p>
                        <p class="text-[11px] text-gray-400">by {{ $t->creator?->name ?? 'Olux Studio' }}{{ $t->category ? ' · '.$t->category : '' }}</p>
                        <p class="text-[12.5px] text-gray-600 dark:text-gray-300 mt-1.5 line-clamp-2 flex-1">{{ $t->short_description }}</p>
                        @php
                            $cardPreview = $t->live_preview_url ?: (is_file(public_path('nuxt-preview/'.($t->builtin_key ?: $t->slug).'/index.html'))
                                ? url('nuxt-preview/'.($t->builtin_key ?: $t->slug).'/') : null);
                        @endphp
                        <div class="flex items-center gap-2 mt-3">
                            <a href="{{ route('marketplace.template', [$site->name, $t->slug]) }}" class="fx flex-1 text-center min-h-[40px] leading-[40px] rounded-xl text-[12.5px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">View</a>
                            @if ($cardPreview)
                                <x-preview-button :href="$cardPreview" label="Live preview" small class="flex-1" />
                            @endif
                        </div>
                        @if ($isOwned)<p class="mt-2 text-[11px] font-bold text-emerald-600">In your library ✓</p>@endif
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-[#1d1e2a] rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.1] p-10 text-center">
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">No templates match those filters</p>
                    <button wire:click="clearFilters" class="mt-2 text-[13px] font-bold hover:underline" style="color:var(--primary)">Clear all filters</button>
                </div>
            @endforelse
        </div>

        <div class="mt-5">{{ $templates->links() }}</div>

    @else
        {{-- ══ MY LIBRARY ══ --}}
        <div class="flex items-center gap-2 mb-4">
            @foreach (['all' => 'All', 'purchased' => 'Purchased', 'free' => 'Free', 'updates' => 'Updates available'] as $fk => $fl)
                <button wire:click="$set('libFilter', '{{ $fk }}')"
                        class="fx px-3.5 py-1.5 rounded-full text-[13px] font-semibold border {{ $libFilter === $fk ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900' : 'bg-white dark:bg-[#1d1e2a] border-gray-200 dark:border-white/[0.08] text-gray-500 dark:text-gray-400' }}">{{ $fl }}</button>
            @endforeach
        </div>

        <div class="space-y-3">
            @forelse ($library as $row)
                <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 flex flex-wrap items-center gap-4"
                     x-data="{ picking: false }">
                    <div class="w-20 h-14 rounded-xl overflow-hidden bg-gray-100 dark:bg-white/[0.05] shrink-0">
                        @if ($row->template->thumbnail_url)<img src="{{ $row->template->thumbnail_url }}" class="w-full h-full object-cover object-top" alt="">@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-extrabold text-gray-900 dark:text-white">{{ $row->template->name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $row->entitlement->source === 'purchase' ? 'bg-[#1c1d29] text-white dark:bg-white dark:text-gray-900' : ($row->entitlement->source === 'upload' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700') }}">{{ match ($row->entitlement->source) { 'purchase' => 'Purchased', 'upload' => 'Uploaded', default => 'Free' } }}</span>
                            @if ($row->update_available)<span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-700">Update available</span>@endif
                        </p>
                        <p class="text-[12px] text-gray-400 mt-0.5">by {{ $row->template->creator?->name ?? 'Olux Studio' }} · {{ $row->used_on->isNotEmpty() ? 'Used on: '.$row->used_on->implode(', ') : 'Not used yet' }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ $row->template->status === 'private' ? ($row->template->previewUrl($site->name) ?? '#') : route('marketplace.template', [$site->name, $row->template->slug]) }}" @if ($row->template->status === 'private') target="_blank" rel="noopener" @endif class="fx min-h-[38px] px-3.5 leading-[38px] rounded-xl text-[12.5px] font-bold border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.05] text-gray-700 dark:text-gray-200">Preview</a>
                        <div class="relative">
                            <button @click="picking = ! picking" class="fx min-h-[38px] px-4 rounded-xl text-[12.5px] font-bold" style="background:var(--primary);color:var(--on-primary)">Use on a site</button>
                            <div x-show="picking" x-cloak @click.outside="picking = false" class="bkf-panel absolute right-0 top-full mt-2 z-30 w-56">
                                @forelse ($sites as $s)
                                    <a href="{{ url($s->name.'/design') }}?use={{ $row->template->id }}" class="fx block px-3 py-2 rounded-lg text-[13px] font-bold text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-white/[0.05]">{{ ucfirst($s->name) }}</a>
                                @empty
                                    <p class="px-3 py-2 text-[12px] text-gray-400">No sites yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-dashed border-gray-200 dark:border-white/[0.1] p-10 text-center">
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">Your library is empty</p>
                    <p class="text-[12.5px] text-gray-400 mt-1">Templates you add or buy live here, ready for any of your sites.</p>
                </div>
            @endforelse

            <button wire:click="$set('tab', 'browse')" class="fx w-full rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.12] p-5 text-center text-[13.5px] font-bold text-gray-500 dark:text-gray-300 hover:border-gray-400">
                + Find more templates
            </button>
        </div>
    @endif
</div>

    {{-- ══ RIGHT rail: summary + related ══ --}}
    <x-slot:quick>
        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4 mb-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Your library</h3>
            <p class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ $libraryCount }} {{ Str::plural('template', $libraryCount) }} owned · {{ config('templates.licence_scope') === 'account' ? 'each covers every site in your account' : 'licensed per site' }}.</p>
            <button wire:click="$set('tab', 'library')" class="mt-2 text-[12px] font-bold hover:underline" style="color:var(--primary)">Open My Library →</button>
        </div>

        <div class="rounded-2xl bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm p-4">
            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white mb-2">Related</h3>
            @foreach ([
                ['Your sites', 'apply templates from each site\'s Design page', '/select-site'],
                ['Subscription', 'plans & billing for your account', '/account/subscription'],
            ] as [$rl, $rd, $ru])
                <a href="{{ url($ru) }}" class="fx flex items-center gap-2.5 py-2 {{ $loop->last ? '' : 'border-b border-gray-50 dark:border-white/[0.04]' }}">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[12px] font-bold text-gray-800 dark:text-gray-100">{{ $rl }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">{{ $rd }}</span>
                    </span>
                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </x-slot:quick>
</x-tri-layout>
