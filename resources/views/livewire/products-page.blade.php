<div class="main-body p-6"
     x-data="{ toast:'', toastType:'success' }"
     x-init="
        $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
     ">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">Store</h1>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                {{ $this->products->total() }}/{{ $this->productLimit }} products ·
                <a href="{{ url($site->name.'/store') }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">View public storefront ↗</a>
            </p>
        </div>
        <div class="flex items-center gap-2">
        <button wire:click="toggleStoreReviews"
                title="Master switch — individual products can still be toggled on their own page"
                class="px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-colors {{ $this->storeReviewsOn ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
            Reviews: {{ $this->storeReviewsOn ? 'ON' : 'OFF' }}
        </button>
        <button wire:click="create"
                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Product
        </button>
        </div>
    </div>

    @unless($site->stripeReady())
    <div class="mb-5 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-sm text-amber-700 dark:text-amber-400">
        Stripe isn't connected — buyers can't pay yet. Connect it in <a href="{{ url($site->name.'/marketplace') }}" class="font-semibold underline">Marketplace</a>.
    </div>
    @endunless

    {{-- Stat tiles — app tile theme --}}
    @php $allProducts = $site->products()->get(['is_active', 'inventory']); @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <x-tile accent="ink" :value="$allProducts->count()" label="products in the store"
                :sub="$this->productLimit.' allowed on your plan'" />
        <x-tile accent="lime" :value="$allProducts->where('is_active', true)->count()" label="active &amp; visible"
                :sub="($allProducts->count() - $allProducts->where('is_active', true)->count()).' hidden'" />
        <x-tile accent="lavender" :value="number_format($allProducts->sum(fn ($p) => (int) $p->inventory))" label="units in stock"
                sub="across all products" />
        <x-tile accent="cocoa" :value="$allProducts->where('inventory', 0)->count()" label="out of stock"
                :sub="$allProducts->where('inventory', 0)->count() > 0 ? 'needs restocking' : 'all stocked'" />
    </div>

    {{-- Product insights --}}
    @php $ins = $this->insights; @endphp
    @if($ins['best_labels'] !== [] || $ins['popular'] !== [] || $ins['reviewed'] !== [])
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-1">Best sellers</h2>
            <p class="text-xs text-gray-400 mb-2">Units sold, last 30 days.</p>
            <div id="store-best-chart" wire:ignore></div>
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-3">Most popular</h2>
            <p class="text-xs text-gray-400 mb-2">Storefront views &amp; basket adds, 30 days.</p>
            @if(count($ins['popular'])) <x-analytics.bar-list :items="$ins['popular']" />
            @else <p class="text-sm text-gray-400 py-4">No interest data yet.</p> @endif
        </div>
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-3">Most reviewed</h2>
            <p class="text-xs text-gray-400 mb-2">Approved reviews with average rating.</p>
            @if(count($ins['reviewed'])) <x-analytics.bar-list :items="$ins['reviewed']" />
            @else <p class="text-sm text-gray-400 py-4">No reviews yet.</p> @endif
        </div>
    </div>
    @endif

    {{-- Category filter chips --}}
    @if($this->categories !== [])
    <div class="flex flex-wrap gap-1.5 mb-4">
        <button wire:click="filterCategory('')" class="px-3 py-1.5 rounded-full text-[11px] font-bold border transition-colors
                {{ $categoryFilter === '' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-200 dark:border-white/[0.08]' }}">All</button>
        @foreach($this->categories as $cat)
            <button wire:click="filterCategory('{{ $cat }}')" class="px-3 py-1.5 rounded-full text-[11px] font-bold border transition-colors
                    {{ $categoryFilter === $cat ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-200 dark:border-white/[0.08]' }}">{{ $cat }}</button>
        @endforeach
    </div>
    @endif

    {{-- Search --}}
    <div class="relative mb-5 max-w-xs">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <x-field.text wire:model.live.debounce.300ms="search" placeholder="Search products…"
                      style="padding-left:2.25rem" />
    </div>

    {{-- Product grid --}}
    @if($this->products->isEmpty())
    <div class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05]">
        <span class="text-4xl mb-3">🛍️</span>
        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No products yet.</p>
        <button wire:click="create" class="mt-3 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Add your first product →</button>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($this->products as $p)
        <div x-on:click="window.location = '{{ url($site->name.'/store/'.$p->id) }}'" wire:key="prod-{{ $p->id }}" role="button"
             class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all">
            <div class="aspect-[4/3] bg-gray-100 dark:bg-white/[0.04] relative">
                @if($p->image)
                    <img src="{{ Storage::url($p->image) }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-4xl">🛍️</div>
                @endif
                <span class="absolute top-2 right-2 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $p->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
                    {{ $p->is_active ? 'Active' : 'Hidden' }}
                </span>
            </div>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $p->name }}</h3>
                    <span class="text-sm font-extrabold text-gray-900 dark:text-white shrink-0">{{ $p->formattedPrice() }}</span>
                </div>
                @if($p->category)<span class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $p->category }}</span>@endif
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 line-clamp-2">{{ $p->description }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-2">{{ $p->inventory === null ? 'Unlimited stock' : $p->inventory.' in stock' }}</p>

                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-50 dark:border-white/[0.04]">
                    <button wire:click.stop="show('{{ $p->id }}')" x-on:click.stop title="Quick view" class="text-xs">👁</button>
                    <button wire:click.stop="edit('{{ $p->id }}')" x-on:click.stop class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                    <button wire:click.stop="toggleActive('{{ $p->id }}')" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ $p->is_active ? 'Hide' : 'Show' }}</button>
                    <button wire:click.stop="delete('{{ $p->id }}')" data-confirm="Delete this product?" class="ml-auto text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $this->products->links() }}
    </div>
    @endif

    {{-- Product detail — read-only right drawer --}}
    @if($this->viewedProduct)
    @php $vp = $this->viewedProduct; @endphp
    <x-lightbox close="closeView" :drawer="true" max-width="max-w-md" icon="🛍️"
                :title="$vp->name" :subtitle="$vp->formattedPrice()" wire:key="product-view-{{ $vp->id }}">
        <x-slot:badge>
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $vp->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
                {{ $vp->is_active ? 'Active' : 'Hidden' }}
            </span>
        </x-slot:badge>

        <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/[0.04] mb-4">
            @if($vp->image)
                <img src="{{ Storage::url($vp->image) }}" class="w-full h-full object-cover">
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
        </div>

        <x-slot:footer>
            <div class="flex gap-2">
                <button wire:click="edit('{{ $vp->id }}')" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Edit product</button>
                <button wire:click="toggleActive('{{ $vp->id }}')" class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $vp->is_active ? 'Hide' : 'Show' }}</button>
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
                        @if($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="w-14 h-14 rounded-xl object-cover">
                        @elseif($existingImage)
                            <img src="{{ Storage::url($existingImage) }}" class="w-14 h-14 rounded-xl object-cover">
                        @else
                            <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-white/[0.06] flex items-center justify-center text-xl">🛍️</div>
                        @endif
                        <input wire:model="photo" type="file" accept="image/*" class="text-xs text-gray-500 dark:text-gray-400">
                    </div>
                    @error('photo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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
                    <button type="submit" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create product' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                    <button type="button" wire:click="closeForm" class="px-4 py-2.5 border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
                </div>
            </form>
    </x-lightbox>
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-[60] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl text-sm font-medium"
         :class="toastType === 'success' ? 'bg-gray-900 text-white' : 'bg-red-600 text-white'">
        <span x-text="toast"></span>
    </div>
</div>

@script
<script>
(function () {
    const el = document.querySelector('#store-best-chart');
    if (!el || typeof ApexCharts === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    const sub = isDark ? '#9ca3af' : '#6b7280';
    new ApexCharts(el, {
        chart: { type: 'bar', height: 200, background: 'transparent', toolbar: { show: false }, animations: { speed: 400 } },
        series: [{ name: 'Units sold', data: @js($this->insights['best_units']) }],
        xaxis: { categories: @js($this->insights['best_labels']), labels: { style: { colors: sub, fontSize: '10px' }, trim: true, rotate: 0, hideOverlappingLabels: false }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { show: false },
        plotOptions: { bar: { distributed: true, borderRadius: 6, borderRadiusApplication: 'end', columnWidth: '50%' } },
        colors: ['#7a7df2', '#34d399', '#f6ad55', '#f687b3', '#63b3ed'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: '600' }, offsetY: -6 },
        grid: { show: false }, legend: { show: false },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No sales yet', style: { color: sub } },
    }).render();
})();
</script>
@endscript
