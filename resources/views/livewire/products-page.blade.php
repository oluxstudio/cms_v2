@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $st = $this->storeStats;
    $products = $this->products;
    $low = \App\Livewire\ProductsPage::LOW_STOCK;
    $filters = [
        'all' => ['All', $st['total']],
        'active' => ['Active', $st['active']],
        'hidden' => ['Hidden', $st['hidden']],
        'out' => ['Out of stock', $st['out']],
        'low' => ['Low stock', $st['low']],
    ];
    // [label, classes] for a product's stock badge.
    $stockBadge = fn ($p) => match (true) {
        $p->inventory === null => ['Unlimited', 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'],
        $p->inventory === 0 => ['Out of stock', 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
        $p->inventory <= $low => [$p->inventory.' left', 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        default => [$p->inventory.' in stock', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
    };
    $activeBadge = fn ($on) => $on
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
        : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300';
    $productUrl = fn ($p) => route('site.store.product', [$site->name, $p->id]);
    $bestSeller = $st['top'][0] ?? null;
    $attention = $st['out'] || $st['noImage']->isNotEmpty() || $st['noPrice']->isNotEmpty() || ! $site->stripeReady() || $st['pendingReviews'];
    $iconBag = 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z';
@endphp
<x-tri-layout title="Store" :site-name="$site->name"
    :subtitle="$st['total'].'/'.$this->productLimit.' products on your plan · reviews '.($this->storeReviewsOn ? 'on' : 'off')"
    :labels="['📊 Overview', '🛍️ Products', '⚡ Summary']">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="toggleStoreReviews" title="Master switch — individual products can still be toggled on their own page"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition-colors {{ $this->storeReviewsOn ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-400 dark:border-emerald-400/20' : 'bg-white dark:bg-[#1d1e2a] text-gray-500 border-gray-200 dark:border-white/[0.1] dark:text-gray-400' }}">
                Reviews: {{ $this->storeReviewsOn ? 'ON' : 'OFF' }}
            </button>
            <a href="{{ url($site->name.'/store') }}" target="_blank" class="{{ $btnSolid }} text-xs px-3.5 py-2">View storefront ↗</a>
        </div>
    </x-slot:header>

    {{-- ── LEFT rail: the store at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="$st['total']" label="Products" :sub="$st['active'].' active · '.$st['hidden'].' hidden'"
                icon="{{ $iconBag }}" />
        <x-tile accent="{{ $st['out'] ? 'rose' : 'lime' }}" :value="$st['out']" label="Out of stock" :sub="$st['out'] ? 'needs restocking' : 'all stocked'"
                icon="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.6a1 1 0 00-.7.3l-2.4 2.4a1 1 0 01-.7.3h-3.2a1 1 0 01-.7-.3l-2.4-2.4a1 1 0 00-.7-.3H4"
                wire:click="setFilter('out')" class="cursor-pointer" />
        <x-tile accent="cocoa" :value="$st['low']" label="Low stock" :sub="$st['low'] ? $low.' or fewer left' : 'nothing running low'"
                icon="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"
                wire:click="setFilter('low')" class="cursor-pointer" />
        <x-tile accent="lime" :value="$st['monthRevenue']" label="Revenue this month" sub="paid orders"
                icon="M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8c1.11 0 2.08.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-tile accent="{{ $st['toFulfil'] ? 'rose' : 'sky' }}" :value="$st['toFulfil']" label="Orders to fulfil" :sub="$st['toFulfil'] ? 'paid, not yet shipped' : 'all caught up'"
                icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                :href="route('site.orders', $site->name).'?status=unfulfilled'" />
        <x-tile accent="lavender" :value="$bestSeller['name'] ?? '—'" label="Best seller" :sub="$bestSeller ? $bestSeller['units'].' sold · 30 days' : 'no sales yet'"
                icon="M11.05 2.93c.3-.92 1.6-.92 1.9 0l1.52 4.67a1 1 0 00.95.69h4.91c.97 0 1.37 1.24.59 1.81l-3.97 2.89a1 1 0 00-.36 1.12l1.52 4.67c.3.92-.76 1.69-1.54 1.12l-3.97-2.89a1 1 0 00-1.18 0l-3.97 2.89c-.78.57-1.84-.2-1.54-1.12l1.52-4.67a1 1 0 00-.36-1.12L2.07 10.1c-.78-.57-.38-1.81.59-1.81h4.91a1 1 0 00.95-.69l1.53-4.67z"
                :href="$bestSeller && $bestSeller['id'] ? route('site.store.product', [$site->name, $bestSeller['id']]) : null" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">

    @unless($site->stripeReady())
    <div class="px-4 py-3 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400">
        Stripe isn't connected — buyers can't pay yet. Connect it on <a href="{{ route('site.payments', $site->name) }}" class="font-semibold underline">Payments</a>
        or on <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="font-semibold underline">Payments</a>.
    </div>
    @endunless

    {{-- ── Toolbar: search · category · sort · layout · new ── --}}
    <div class="{{ $panel }} !rounded-2xl p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[12rem]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <x-field.text wire:model.live.debounce.300ms="search" placeholder="Search products, categories…" class="w-full" style="padding-left:2.25rem" />
            </div>
            @if($this->categories !== [])
            <select wire:model.live="categoryFilter" class="bkf-input !w-auto text-[13px]" title="Category">
                <option value="">All categories</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }} ({{ $st['categories'][$cat] ?? 0 }})</option>
                @endforeach
            </select>
            @endif
            <select wire:model.live="sort" class="bkf-input !w-auto text-[13px]" title="Order">
                <option value="newest">Newest</option>
                <option value="best">Best selling</option>
                <option value="price_asc">Price: low → high</option>
                <option value="price_desc">Price: high → low</option>
                <option value="stock">Stock: lowest first</option>
            </select>
            <x-layout-switcher :modes="$layoutModes" :current="$viewMode" />
            <button wire:click="create"
                    class="inline-flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm" style="background:var(--primary);color:var(--on-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New product
            </button>
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            @foreach ($filters as $key => [$label, $n])
                <button type="button" wire:click="setFilter('{{ $key }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-semibold border transition-colors
                        {{ $filter === $key
                            ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white'
                            : 'bg-white dark:bg-[#1d1e2a] text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.06]' }}">
                    {{ $label }} <span class="opacity-60">{{ $n }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if($products->isEmpty())
        <div class="{{ $panel }} px-6 py-16 text-center">
            <span class="mx-auto mb-3 w-14 h-14 rounded-2xl grid place-items-center bg-gray-100 dark:bg-white/[0.06]">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconBag }}"/></svg>
            </span>
            @if($st['total'] === 0)
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">No products yet</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">Add what you sell — a name, a price and a photo is enough. Stock, categories and tags are optional.</p>
                <button wire:click="create" class="inline-flex mt-4 text-sm font-bold px-4 py-2.5 rounded-xl" style="background:var(--primary);color:var(--on-primary)">Add your first product</button>
            @else
                <p class="text-[15px] font-bold text-gray-900 dark:text-white">Nothing matches</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try another search, category or filter.</p>
                <button type="button" x-on:click="$wire.set('search', ''); $wire.set('categoryFilter', ''); $wire.setFilter('all')" class="{{ $btnSolid }} mt-4 text-sm px-4 py-2">Show all products</button>
            @endif
        </div>
    @elseif($viewMode === 'grid')
        {{-- ── Cards ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($products as $p)
                @php [$stockLabel, $stockClass] = $stockBadge($p); @endphp
                <div class="group flex flex-col {{ $panel }} !rounded-2xl overflow-hidden hover:shadow-md transition-shadow" wire:key="prod-{{ $p->id }}">
                    <a href="{{ $productUrl($p) }}" wire:navigate class="block flex-1">
                        <div class="aspect-[4/3] bg-gray-100 dark:bg-white/[0.04] relative">
                            @if($p->image_url)
                                <img src="{{ $p->image_url }}" alt="" class="w-full h-full object-cover" loading="lazy">
                            @else
                                <div class="w-full h-full grid place-items-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconBag }}"/></svg>
                                </div>
                            @endif
                            <span class="absolute top-2 left-2 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $activeBadge($p->is_active) }}">{{ $p->is_active ? 'Active' : 'Hidden' }}</span>
                            <span class="absolute top-2 right-2 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $stockClass }}">{{ $stockLabel }}</span>
                        </div>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate group-hover:underline">{{ $p->name }}</h3>
                                <span class="text-sm font-extrabold tabular-nums shrink-0 {{ $p->price_cents > 0 ? 'text-gray-900 dark:text-white' : 'text-rose-500' }}">{{ $p->formattedPrice() }}</span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                @if($p->category)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $p->category }}</span>@endif
                                @if((int) $p->sold_units > 0)<span class="text-[10px] font-semibold text-gray-400">{{ (int) $p->sold_units }} sold</span>@endif
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5 line-clamp-2 min-h-[2.5em]">{{ $p->description ?: 'No description yet.' }}</p>
                        </div>
                    </a>
                    <div class="flex items-center gap-1.5 px-3 py-2.5 border-t border-gray-100 dark:border-white/[0.05]">
                        <button wire:click="edit('{{ $p->id }}')" class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-bold" style="background:var(--primary);color:var(--on-primary)">Edit</button>
                        <button wire:click="toggleActive('{{ $p->id }}')" class="{{ $btnSolid }} text-[12px] px-3 py-1.5">{{ $p->is_active ? 'Hide' : 'Show' }}</button>
                        <span class="ml-auto flex items-center gap-0.5">
                            <button wire:click="show('{{ $p->id }}')" title="Quick view & restock"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5c4.48 0 8.27 2.94 9.54 7-1.27 4.06-5.06 7-9.54 7-4.48 0-8.27-2.94-9.54-7z"/></svg>
                            </button>
                            <button wire:click="delete('{{ $p->id }}')" data-confirm="Delete “{{ $p->name }}”? This can't be undone." title="Delete"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ── List & Compact (table) ── --}}
        @php $compact = $viewMode === 'compact'; $pad = $compact ? 'px-4 py-2' : 'px-4 py-3'; @endphp
        <div class="{{ $panel }} !rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/[0.05] text-left text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3">Stock</th>
                            @unless($compact)
                                <th class="px-4 py-3 text-right">Sold</th>
                                <th class="px-4 py-3">Status</th>
                            @endunless
                            <th class="w-36 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                        @foreach($products as $p)
                            @php [$stockLabel, $stockClass] = $stockBadge($p); @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors" wire:key="row-{{ $p->id }}">
                                <td class="{{ $pad }}">
                                    <a href="{{ $productUrl($p) }}" wire:navigate class="flex items-center gap-2.5 min-w-0">
                                        @unless($compact)
                                            @if($p->image_url)
                                                <img src="{{ $p->image_url }}" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0" loading="lazy">
                                            @else
                                                <span class="w-9 h-9 rounded-lg grid place-items-center shrink-0 bg-gray-100 dark:bg-white/[0.06] text-gray-400">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconBag }}"/></svg>
                                                </span>
                                            @endif
                                        @endunless
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-gray-900 dark:text-white truncate hover:underline">{{ $p->name }}</span>
                                            @unless($compact)<span class="block text-[11px] text-gray-400 truncate max-w-[16rem]">{{ $p->category ?: 'No category' }}</span>@endunless
                                        </span>
                                    </a>
                                </td>
                                <td class="{{ $pad }} text-right font-bold tabular-nums text-gray-900 dark:text-white whitespace-nowrap">{{ $p->formattedPrice() }}</td>
                                <td class="{{ $pad }}"><span class="text-[11px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap {{ $stockClass }}">{{ $stockLabel }}</span></td>
                                @unless($compact)
                                    <td class="{{ $pad }} text-right tabular-nums text-gray-500 dark:text-gray-300">{{ (int) $p->sold_units }}</td>
                                    <td class="{{ $pad }}"><span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $activeBadge($p->is_active) }}">{{ $p->is_active ? 'Active' : 'Hidden' }}</span></td>
                                @endunless
                                <td class="{{ $pad }}">
                                    <div class="flex items-center gap-1 justify-end">
                                        <button wire:click="show('{{ $p->id }}')" title="Quick view & restock" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5c4.48 0 8.27 2.94 9.54 7-1.27 4.06-5.06 7-9.54 7-4.48 0-8.27-2.94-9.54-7z"/></svg>
                                        </button>
                                        <button wire:click="edit('{{ $p->id }}')" title="Edit" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="toggleActive('{{ $p->id }}')" class="px-2 py-1 rounded-lg text-[11px] font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100">{{ $p->is_active ? 'Hide' : 'Show' }}</button>
                                        <button wire:click="delete('{{ $p->id }}')" data-confirm="Delete “{{ $p->name }}”? This can't be undone." title="Delete"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($products->hasPages())
        <div>{{ $products->links() }}</div>
    @endif

    {{-- Product detail — read-only right drawer --}}
    @if($this->viewedProduct)
    @php $vp = $this->viewedProduct; @endphp
    <x-lightbox close="closeView" :drawer="true" max-width="max-w-md" icon="🛍️"
                :title="$vp->name" :subtitle="$vp->formattedPrice()" wire:key="product-view-{{ $vp->id }}">
        <x-slot:badge>
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $activeBadge($vp->is_active) }}">
                {{ $vp->is_active ? 'Active' : 'Hidden' }}
            </span>
        </x-slot:badge>

        <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/[0.04] mb-4">
            @if($vp->image_url)
                <img src="{{ $vp->image_url }}" alt="" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center text-5xl">🛍️</div>
            @endif
        </div>

        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-400">Price</span>
                <span class="font-extrabold text-gray-900 dark:text-white">{{ $vp->formattedPrice() }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-400">Stock</span>
                <span class="font-semibold {{ $vp->inventory === 0 ? 'text-rose-500' : '' }}">
                    {{ $vp->inventory === null ? 'Unlimited' : ($vp->inventory === 0 ? 'Out of stock' : $vp->inventory.' in stock') }}
                </span>
            </div>
            @if($vp->inventory !== null)
            <div class="flex items-center gap-2">
                <input type="number" min="1" wire:model="restockQty" placeholder="Qty"
                       class="w-20 px-2.5 py-1.5 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                <button wire:click="addStock('{{ $vp->id }}')"
                        class="px-3 py-1.5 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold">＋ Add stock</button>
            </div>
            @endif
            @if($vp->category || $vp->tags)
                <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                    @if($vp->category)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $vp->category }}</span>@endif
                    @foreach($vp->tags ?? [] as $tag)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
            @if($vp->description)
                <div class="pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                    <p class="text-gray-500 dark:text-gray-400 leading-relaxed">{{ $vp->description }}</p>
                </div>
            @endif
            <a href="{{ $productUrl($vp) }}" wire:navigate class="block pt-2 text-xs font-semibold" style="color:var(--primary)">Open the product page — history, sales & reviews →</a>
        </div>

        <x-slot:footer>
            <div class="flex gap-2">
                <button wire:click="edit('{{ $vp->id }}')" class="flex-1 py-2.5 rounded-xl text-sm font-semibold" style="background:var(--primary);color:var(--on-primary)">Edit product</button>
                <button wire:click="toggleActive('{{ $vp->id }}')" class="{{ $btnSolid }} px-4 py-2.5 text-sm">{{ $vp->is_active ? 'Hide' : 'Show' }}</button>
            </div>
        </x-slot:footer>
    </x-lightbox>
    @endif

    {{-- Create / edit — right-side drawer (same shell as the bookings panels) --}}
    @if($showForm)
    <x-lightbox close="closeForm" :drawer="true" max-width="max-w-md" icon="🛍️"
                :title="$editingId ? 'Edit product' : 'New product'"
                :subtitle="$editingId ? $name : 'Name, price and photo — details under More options'"
                wire:key="product-form-{{ $editingId ?? 'new' }}">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <x-field.text label="Name" model="name" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-field.text label="Price ({{ \App\Support\Money::symbol($this->currency) }})" model="price" type="number" step="0.01" min="0" placeholder="9.99" />
                    @error('price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Image</label>
                    <div class="flex items-center gap-3">
                        @if($imageUrl)
                            <img src="{{ \App\Models\Media::resolveRef($site->id, $imageUrl) }}" alt="" class="w-14 h-14 rounded-xl object-cover shrink-0">
                        @else
                            <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-white/[0.06] flex items-center justify-center text-xl">🛍️</div>
                        @endif
                        <x-asset-picker model="imageUrl" :site="$site" type="image" placeholder="Image URL, or pick from assets" />
                    </div>
                    @error('imageUrl') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <x-field.check model="is_active" text="Active (visible in storefront)" />

                <div class="olx-adv-lead">More options</div>
                <x-panel-group label="Description & stock" hint="storefront text, inventory">
                    <x-field.textarea label="Description" model="description" rows="3" class="resize-none" />
                    <x-field.text label="Inventory (blank = ∞)" model="inventory" type="number" min="0" placeholder="Unlimited" />
                </x-panel-group>
                <x-panel-group label="Organisation" hint="category & tags for the shop">
                    <div>
                        <label class="bkf-label">Category</label>
                        <input wire:model="category" list="product-categories" placeholder="e.g. Styling"
                               class="bkf-input">
                        <datalist id="product-categories">
                            @foreach($this->categories as $cat)<option value="{{ $cat }}">@endforeach
                        </datalist>
                    </div>
                    <x-field.text label="Tags" model="tagsInput" placeholder="curly, colour-safe, heat"
                                  hint="Comma-separated — used for search and shop filters." />
                </x-panel-group>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 py-2.5 text-sm font-semibold rounded-xl transition-colors" style="background:var(--primary);color:var(--on-primary)">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create product' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                    <button type="button" wire:click="closeForm" class="{{ $btnSolid }} px-4 py-2.5 text-sm">Cancel</button>
                </div>
            </form>
    </x-lightbox>
    @endif
    </div>

    {{-- Toast --}}
    <div class="fixed bottom-6 right-6 z-[60]"
         x-data="{ toast:'', toastType:'success' }"
         x-init="
            $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
            $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
         ">
        <div x-show="toast" x-cloak
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 translate-y-4"
             class="flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium"
             :class="toastType === 'success' ? 'bg-gray-900 text-white' : 'bg-red-600 text-white'">
            <span x-text="toast"></span>
        </div>
    </div>

    {{-- ══ RIGHT rail: sales summary · needs attention · top products · related ══ --}}
    <x-slot:quick>
        @php $dMax = max(1, max($st['daily'])); @endphp
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-1" style="color:var(--primary)">Sales · last 30 days</p>
            <p class="font-display text-2xl font-extrabold tracking-tight tabular-nums text-gray-900 dark:text-white">{{ $st['revenue30'] }}</p>
            <p class="text-[12px] text-gray-500 dark:text-gray-400 mb-3">
                @if($st['revDelta'] !== null)
                    <span class="font-bold {{ $st['revDelta'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ $st['revDelta'] >= 0 ? '▲' : '▼' }} {{ abs($st['revDelta']) }}%</span> vs the 30 days before
                @else
                    paid orders only
                @endif
            </p>
            <div class="flex items-end gap-[2px] h-12" aria-hidden="true">
                @foreach($st['daily'] as $cents)
                    <span class="flex-1 rounded-sm" style="height:{{ $cents > 0 ? max(10, round($cents / $dMax * 100)) : 4 }}%;background:{{ $cents > 0 ? 'var(--primary)' : 'rgba(148,163,184,.25)' }}"></span>
                @endforeach
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                @foreach([[$st['orders30'], 'orders'], [$st['units30'], 'units'], [$st['aov30'], 'avg order']] as [$v, $l])
                    <div class="rounded-xl bg-gray-50 dark:bg-white/[0.04] px-2 py-2">
                        <span class="block text-[13px] font-extrabold tabular-nums text-gray-900 dark:text-white truncate">{{ $v }}</span>
                        <span class="block text-[10px] text-gray-500 dark:text-gray-400">{{ $l }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if($attention)
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2.5">
                @unless($site->stripeReady())
                    <a href="{{ route('site.payments', $site->name) }}" wire:navigate class="block rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10 hover:ring-2 hover:ring-rose-200 dark:hover:ring-rose-500/30">
                        <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">Payments not connected</p>
                        <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70">Buyers can't check out yet — connect Stripe →</p>
                    </a>
                @endunless
                @if($st['out'])
                    <div class="rounded-2xl px-3.5 py-3 bg-rose-50 dark:bg-rose-500/10">
                        <button type="button" wire:click="setFilter('out')" class="text-left w-full">
                            <p class="text-[13px] font-bold text-rose-800 dark:text-rose-200">{{ $st['out'] }} out of stock</p>
                            <p class="text-[12px] text-rose-700/80 dark:text-rose-200/70 mb-1.5">Shoppers can't buy these — restock →</p>
                        </button>
                        <div class="flex flex-wrap gap-1">
                            @foreach($st['outList']->take(5) as $p)
                                <button type="button" wire:click="show('{{ $p->id }}')" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/80 dark:bg-white/[0.08] text-rose-800 dark:text-rose-200 hover:underline">{{ $p->name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($st['noImage']->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $st['noImage']->count() }} without a photo</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70 mb-1.5">Products with pictures sell better.</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($st['noImage']->take(5) as $p)
                                <button type="button" wire:click="edit('{{ $p->id }}')" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/80 dark:bg-white/[0.08] text-amber-800 dark:text-amber-200 hover:underline">＋ {{ $p->name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($st['noPrice']->isNotEmpty())
                    <div class="rounded-2xl px-3.5 py-3 bg-amber-50 dark:bg-amber-500/10">
                        <p class="text-[13px] font-bold text-amber-800 dark:text-amber-200">{{ $st['noPrice']->count() }} with no price</p>
                        <p class="text-[12px] text-amber-700/80 dark:text-amber-200/70 mb-1.5">They show as free on the storefront.</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($st['noPrice']->take(5) as $p)
                                <button type="button" wire:click="edit('{{ $p->id }}')" class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/80 dark:bg-white/[0.08] text-amber-800 dark:text-amber-200 hover:underline">{{ $p->name }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($st['pendingReviews'])
                    <div class="rounded-2xl px-3.5 py-3 bg-gray-50 dark:bg-white/[0.04]">
                        <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100">{{ $st['pendingReviews'] }} {{ Str::plural('review', $st['pendingReviews']) }} awaiting approval</p>
                        <p class="text-[12px] text-gray-500 dark:text-gray-400">Approve them on each product's page.</p>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-0.5">Top products</p>
            <p class="text-[11px] text-gray-400 mb-3">By revenue, last 30 days</p>
            @if($st['top'] === [])
                <p class="text-[12.5px] text-gray-400 py-2">No sales yet — your best sellers show up here.</p>
            @else
                @php $tMax = max(1, $st['top'][0]['cents']); @endphp
                <div class="space-y-2.5">
                    @foreach($st['top'] as $t)
                        @php $tagName = $t['id'] ? 'a' : 'div'; @endphp
                        <{{ $tagName }} @if($t['id']) href="{{ route('site.store.product', [$site->name, $t['id']]) }}" wire:navigate @endif class="block group">
                            <span class="flex items-center justify-between gap-2 text-[12.5px]">
                                <span class="truncate text-gray-700 dark:text-gray-200 group-hover:underline">{{ $t['name'] }}</span>
                                <span class="shrink-0 font-bold text-gray-900 dark:text-white tabular-nums">{{ $t['revenue'] }}</span>
                            </span>
                            <span class="block mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                                <span class="block h-full rounded-full" style="width:{{ round($t['cents'] / $tMax * 100) }}%;background:var(--primary)"></span>
                            </span>
                            <span class="block mt-0.5 text-[10.5px] text-gray-400">{{ $t['units'] }} {{ Str::plural('unit', $t['units']) }}</span>
                        </{{ $tagName }}>
                    @endforeach
                </div>
            @endif
        </div>

        @php $ins = $this->insights; @endphp
        @if($ins['popular'] !== [] || $ins['reviewed'] !== [])
        <div class="{{ $panel }} p-5 space-y-4">
            @if($ins['popular'] !== [])
            <div>
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-0.5">Most popular</p>
                <p class="text-[11px] text-gray-400 mb-2">Storefront views &amp; basket adds, 30 days</p>
                <x-analytics.bar-list :items="$ins['popular']" />
            </div>
            @endif
            @if($ins['reviewed'] !== [])
            <div>
                <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-0.5">Most reviewed</p>
                <p class="text-[11px] text-gray-400 mb-2">Approved reviews with average rating</p>
                <x-analytics.bar-list :items="$ins['reviewed']" />
            </div>
            @endif
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach(array_filter([
                    ['Orders', 'Fulfil & refund', route('site.orders', $site->name)],
                    ['Payments', $site->stripeReady() ? 'Payouts & Stripe' : 'Connect Stripe', route('site.payments', $site->name)],
                    $site->hasFeature('invoices') ? ['Invoices', 'Bill customers', route('site.invoices', $site->name)] : null,
                    ['Assets', 'Product photos', route('media', $site->name)],
                    ['Contacts', 'Your buyers', route('site.contacts', $site->name)],
                    ['Store settings', 'Limits & options', route('site.marketplace', $site->name)],
                ]) as [$label, $hint, $href])
                    <a href="{{ $href }}" wire:navigate class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>
