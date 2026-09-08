<div class="main-body p-6"
     x-data="{ toast:'', toastType:'success' }"
     x-init="
        $watch('$wire.successMessage', v => { if(v){ toast=v; toastType='success'; setTimeout(()=>{ toast=''; $wire.successMessage=''; }, 4000) } });
        $watch('$wire.errorMessage',   v => { if(v){ toast=v; toastType='error';   setTimeout(()=>{ toast=''; $wire.errorMessage='';   }, 5000) } });
     ">

    {{-- Breadcrumb + header --}}
    <div class="mb-5">
        <a href="{{ url($site->name.'/store') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">← Store</a>
        <div class="flex flex-col lg:flex-row lg:items-start gap-5 mt-3">
            <div class="w-full lg:w-56 shrink-0">
                <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/[0.04]">
                    @if($product->image)
                        <img src="{{ Storage::url($product->image) }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-5xl">🛍️</div>
                    @endif
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">{{ $product->name }}</h1>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $product->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
                        {{ $product->is_active ? 'Active' : 'Hidden' }}
                    </span>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $product->inventory === 0 ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                        {{ $product->inventory === null ? 'Unlimited stock' : ($product->inventory === 0 ? 'Out of stock' : $product->inventory.' in stock') }}
                    </span>
                </div>
                <p class="mt-1 text-lg font-extrabold text-gray-900 dark:text-white">{{ $product->formattedPrice() }}</p>
                @if($product->category || $product->tags)
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    @if($product->category)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $product->category }}</span>@endif
                    @foreach($product->tags ?? [] as $tag)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">#{{ $tag }}</span>
                    @endforeach
                </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 mt-4">
                    <button wire:click="edit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition-colors">Edit product</button>
                    <button wire:click="toggleActive" class="px-3.5 py-2 rounded-xl border border-gray-200 dark:border-white/[0.08] text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/[0.04]">
                        {{ $product->is_active ? 'Hide from storefront' : 'Show in storefront' }}
                    </button>
                    <button wire:click="toggleReviews" class="px-3.5 py-2 rounded-xl text-xs font-semibold {{ $product->reviews_enabled ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                        Reviews (this product): {{ $product->reviews_enabled ? 'ON' : 'OFF' }}
                    </button>
                    <button wire:click="toggleStoreReviews" class="px-3.5 py-2 rounded-xl text-xs font-semibold {{ $this->storeReviewsOn ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                        Reviews (whole store): {{ $this->storeReviewsOn ? 'ON' : 'OFF' }}
                    </button>
                    @if($product->inventory !== null)
                    <div class="flex items-center gap-2 ml-auto">
                        <input type="number" min="1" wire:model="restockQty" placeholder="Qty"
                               class="w-20 px-2.5 py-1.5 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                        <button wire:click="addStock" class="px-3 py-1.5 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold">＋ Add stock</button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Totals row --}}
    @php $i = $this->interest; $s = $this->sales; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <x-tile accent="ink" :value="number_format($i['total_views'])" label="product views" sub="last 30 days" />
        <x-tile accent="lavender" :value="number_format($i['total_adds'])" label="added to basket"
                :sub="$i['conversion'] !== null ? $i['conversion'].'% of viewers' : 'last 30 days'" />
        <x-tile accent="lime" :value="number_format($s['lifetime_units'])" label="units sold"
                :sub="$i['orders'].' orders in 30 days'" />
        <x-tile accent="cocoa" :value="\App\Support\Money::format($s['lifetime_revenue_cents'], $this->currency)" label="lifetime revenue" sub="paid orders only" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        {{-- Interest chart --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-1">Interest — last 30 days</h2>
            <p class="text-xs text-gray-400 mb-2">Storefront views vs. added to basket.</p>
            <div id="product-interest-chart" wire:ignore></div>
        </div>
        {{-- Sales chart --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-1">Sales — last 30 days</h2>
            <p class="text-xs text-gray-400 mb-2">Units sold per day (paid orders).</p>
            <div id="product-sales-chart" wire:ignore></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5 items-start">
        {{-- Inventory history --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm mb-3">Inventory history</h2>
            @if($this->movements->isEmpty())
                <p class="text-sm text-gray-400 py-4">No stock changes recorded yet — sales, restocks and manual edits will appear here.</p>
            @else
            <ul class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                @foreach($this->movements as $m)
                <li class="py-2.5 flex items-center gap-3 text-sm" wire:key="mv-{{ $m->id }}">
                    <span class="font-extrabold w-12 shrink-0 {{ $m->delta < 0 ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400' }}">
                        {{ $m->delta > 0 ? '+' : '' }}{{ $m->delta }}
                    </span>
                    @php $reasonStyle = [
                        'sale' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300',
                        'restock' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
                        'manual' => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
                        'cancel_restock' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                        'return_restock' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',
                    ][$m->reason] ?? 'bg-gray-100 text-gray-500'; @endphp
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $reasonStyle }}">{{ str_replace('_', ' ', $m->reason) }}</span>
                    <span class="text-xs text-gray-400 truncate">
                        → {{ $m->stock_after }} left
                        @if($m->user) · by {{ $m->user->name }} @endif
                        @if($m->order_id) · <a href="{{ url($site->name.'/orders') }}" class="text-indigo-500 hover:underline">order #{{ substr($m->order_id, -6) }}</a> @endif
                    </span>
                    <span class="ml-auto text-[11px] text-gray-400 shrink-0">{{ $m->created_at?->diffForHumans() }}</span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- Reviews --}}
        <div class="bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.05] shadow-sm rounded-2xl p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-gray-900 dark:text-white font-bold text-sm">Reviews</h2>
            @if($this->approvedReviews->isNotEmpty())
                <span class="text-xs text-gray-400">★ {{ round($this->approvedReviews->avg('rating'), 1) }} · {{ $this->approvedReviews->count() }} approved</span>
            @endif
        </div>
        @if(! $this->storeReviewsOn)
            <p class="text-sm text-gray-400 py-2">Reviews are switched <b>off for the whole store</b> — no product shows ratings or accepts new reviews. Existing reviews are kept.</p>
        @elseif(! $product->reviews_enabled)
            <p class="text-sm text-gray-400 py-2">Reviews are switched <b>off</b> for this product — visitors can't submit new ones and ratings are hidden on the storefront. Existing reviews are kept.</p>
        @endif

        @if($this->pendingReviews->isNotEmpty())
        <p class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-2">Awaiting approval ({{ $this->pendingReviews->count() }})</p>
        <ul class="space-y-2 mb-4">
            @foreach($this->pendingReviews as $r)
            <li class="p-3 rounded-xl bg-amber-50/60 dark:bg-amber-500/[0.06] border border-amber-100 dark:border-amber-500/20" wire:key="rev-{{ $r->id }}">
                <div class="flex items-center gap-2 text-sm">
                    <b class="text-gray-900 dark:text-white">{{ $r->name }}</b>
                    <span class="text-amber-500">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</span>
                    <span class="text-[11px] text-gray-400 ml-auto">{{ $r->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $r->body }}</p>
                <div class="flex gap-2 mt-2">
                    <button wire:click="approveReview('{{ $r->id }}')" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Approve</button>
                    <button wire:click="deleteReview('{{ $r->id }}')" data-confirm="Delete this review?" class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                </div>
            </li>
            @endforeach
        </ul>
        @endif

        @if($this->approvedReviews->isEmpty() && $this->pendingReviews->isEmpty())
            <p class="text-sm text-gray-400 py-2">No reviews yet.</p>
        @else
        <ul class="space-y-2">
            @foreach($this->approvedReviews as $r)
            <li class="p-3 rounded-xl bg-gray-50 dark:bg-white/[0.03]" wire:key="rev-{{ $r->id }}">
                <div class="flex items-center gap-2 text-sm">
                    <b class="text-gray-900 dark:text-white">{{ $r->name }}</b>
                    <span class="text-amber-500">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</span>
                    <span class="text-[11px] text-gray-400 ml-auto">{{ $r->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $r->body }}</p>
                <button wire:click="deleteReview('{{ $r->id }}')" data-confirm="Delete this review?" class="text-xs font-medium text-red-500 hover:text-red-700 mt-1.5">Delete</button>
            </li>
            @endforeach
        </ul>
        @endif
        </div>
    </div>

    {{-- Edit product — right-side drawer (same shell as the store page) --}}
    @if($showForm)
    <x-lightbox close="closeForm" :drawer="true" max-width="max-w-md" icon="🛍️"
                title="Edit product" :subtitle="$product->name" wire:key="pd-form-{{ $product->id }}">
        <form wire:submit="save" class="space-y-4">
            <div>
                <x-field.text label="Name" model="name" />
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-field.text label="Price ({{ \App\Support\Money::symbol($this->currency) }})" model="price" type="number" step="0.01" min="0" />
                    @error('price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <x-field.text label="Inventory (blank = ∞)" model="inventory" type="number" min="0" placeholder="Unlimited" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Image</label>
                <div class="flex items-center gap-3">
                    @if($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="w-14 h-14 rounded-xl object-cover">
                    @elseif($product->image)
                        <img src="{{ Storage::url($product->image) }}" class="w-14 h-14 rounded-xl object-cover">
                    @else
                        <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-white/[0.06] flex items-center justify-center text-xl">🛍️</div>
                    @endif
                    <input wire:model="photo" type="file" accept="image/*" class="text-xs text-gray-500 dark:text-gray-400">
                </div>
                @error('photo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <x-field.textarea label="Description" model="description" rows="3" class="resize-none" />
            <x-field.check model="is_active" text="Active (visible in storefront)" />

            <div class="olx-adv-lead">More options</div>
            <x-panel-group label="Organisation" hint="category & tags for the shop">
                <div>
                    <label class="bkf-label">Category</label>
                    <input wire:model="category" list="pd-categories" placeholder="e.g. Styling" class="bkf-input">
                    <datalist id="pd-categories">
                        @foreach($this->categories as $cat)<option value="{{ $cat }}">@endforeach
                    </datalist>
                </div>
                <x-field.text label="Tags" model="tagsInput" placeholder="curly, colour-safe, heat"
                              hint="Comma-separated — used for search and shop filters." />
            </x-panel-group>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <span wire:loading.remove wire:target="save">Save changes</span>
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
    const isDark = document.documentElement.classList.contains('dark');
    const txt = isDark ? '#e5e7eb' : '#374151';
    const sub = isDark ? '#9ca3af' : '#6b7280';
    const interest = @js($this->interest);
    const sales = @js($this->sales);

    new ApexCharts(document.querySelector('#product-interest-chart'), {
        chart: { type: 'area', height: 220, background: 'transparent', toolbar: { show: false }, animations: { speed: 400 } },
        series: [
            { name: 'Views', data: interest.views },
            { name: 'Added to basket', data: interest.adds },
        ],
        xaxis: { categories: interest.labels, tickAmount: 6, labels: { style: { colors: sub, fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        colors: ['#7a7df2', '#34d399'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
        dataLabels: { enabled: false },
        grid: { borderColor: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
        legend: { labels: { colors: txt }, fontSize: '11px' },
        tooltip: { theme: isDark ? 'dark' : 'light' },
        noData: { text: 'No data yet', style: { color: sub } },
    }).render();

    new ApexCharts(document.querySelector('#product-sales-chart'), {
        chart: { type: 'bar', height: 220, background: 'transparent', toolbar: { show: false }, animations: { speed: 400 } },
        series: [{ name: 'Units', data: sales.units }],
        xaxis: { categories: sales.labels, tickAmount: 6, labels: { style: { colors: sub, fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: sub, fontSize: '10px' } } },
        plotOptions: { bar: { borderRadius: 3, borderRadiusApplication: 'end', columnWidth: '55%' } },
        colors: ['#7a7df2'],
        dataLabels: { enabled: false },
        grid: { borderColor: isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
        legend: { show: false },
        tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: (v, o) => `${v} units · ${@js(\App\Support\Money::symbol($this->currency))}${(sales.revenue[o.dataPointIndex] ?? 0).toFixed(2)}` } },
        noData: { text: 'No sales yet', style: { color: sub } },
    }).render();
})();
</script>
@endscript
