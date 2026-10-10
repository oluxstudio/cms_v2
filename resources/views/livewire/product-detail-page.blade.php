@php
    $panel = 'rounded-[1.75rem] bg-white dark:bg-[#1d1e2a] border border-gray-100 dark:border-white/[0.06] shadow-sm';
    $btnSolid = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.1] text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors';
    $i = $this->interest; $s = $this->sales;
    $low = \App\Livewire\ProductsPage::LOW_STOCK;
    $stockText = $product->inventory === null ? 'Unlimited' : ($product->inventory === 0 ? 'Out of stock' : $product->inventory.' in stock');
    $approved = $this->approvedReviews;
@endphp
<x-tri-layout :title="$product->name" :site-name="$site->name"
    :subtitle="$product->formattedPrice().' · '.($product->is_active ? 'active in the storefront' : 'hidden from the storefront')"
    :labels="['📊 Overview', '🛍️ Product', '⚡ Summary']">

    <x-slot:header>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('site.store', $site->name) }}" wire:navigate class="{{ $btnSolid }} text-xs px-3.5 py-2">← Store</a>
            <button wire:click="edit" class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold shadow-sm" style="background:var(--primary);color:var(--on-primary)">Edit product</button>
        </div>
    </x-slot:header>

    {{-- ── LEFT rail: this product at a glance ── --}}
    <x-slot:rail>
    <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
        <x-tile accent="ink" wide :value="\App\Support\Money::format($s['lifetime_revenue_cents'], $this->currency)" label="Lifetime revenue" sub="paid orders only" />
        <x-tile accent="{{ $product->inventory === 0 ? 'rose' : ($product->inventory !== null && $product->inventory <= $low ? 'cocoa' : 'lime') }}"
                :value="$product->inventory === null ? '∞' : $product->inventory" label="In stock"
                :sub="$product->inventory === 0 ? 'out of stock' : ($product->inventory === null ? 'unlimited' : ($product->inventory <= $low ? 'running low' : 'units available'))"
                icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        <x-tile accent="lime" :value="number_format($s['lifetime_units'])" label="Units sold" :sub="$i['orders'].' orders in 30 days'"
                icon="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        <x-tile accent="sky" :value="number_format($i['total_views'])" label="Product views" sub="last 30 days"
                icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.46 12C3.73 7.94 7.52 5 12 5c4.48 0 8.27 2.94 9.54 7-1.27 4.06-5.06 7-9.54 7-4.48 0-8.27-2.94-9.54-7z" />
        <x-tile accent="lavender" :value="number_format($i['total_adds'])" label="Added to basket"
                :sub="$i['conversion'] !== null ? $i['conversion'].'% of viewers' : 'last 30 days'"
                icon="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        <x-tile accent="{{ $this->pendingReviews->isNotEmpty() ? 'rose' : 'cocoa' }}" :value="$approved->isNotEmpty() ? '★ '.round($approved->avg('rating'), 1) : '—'" label="Reviews"
                :sub="$this->pendingReviews->isNotEmpty() ? $this->pendingReviews->count().' awaiting approval' : $approved->count().' approved'"
                icon="M11.05 2.93c.3-.92 1.6-.92 1.9 0l1.52 4.67a1 1 0 00.95.69h4.91c.97 0 1.37 1.24.59 1.81l-3.97 2.89a1 1 0 00-.36 1.12l1.52 4.67c.3.92-.76 1.69-1.54 1.12l-3.97-2.89a1 1 0 00-1.18 0l-3.97 2.89c-.78.57-1.84-.2-1.54-1.12l1.52-4.67a1 1 0 00-.36-1.12L2.07 10.1c-.78-.57-.38-1.81.59-1.81h4.91a1 1 0 00.95-.69l1.53-4.67z" />
    </div>
    </x-slot:rail>

    <div class="space-y-5">
    {{-- Product hero --}}
    <div class="{{ $panel }} !rounded-2xl p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-start gap-5">
            <div class="w-full sm:w-48 shrink-0">
                <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-gray-100 dark:bg-white/[0.04]">
                    @if($product->image_url)
                        <img src="{{ $product->image_url }}" alt="" class="w-full h-full object-cover">
                    @else
                        <button type="button" wire:click="edit" class="w-full h-full flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-gray-600">
                            <span class="text-4xl">🛍️</span><span class="text-[11px] font-semibold">Add a photo</span>
                        </button>
                    @endif
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $product->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-gray-200 text-gray-600 dark:bg-black/40 dark:text-gray-300' }}">
                        {{ $product->is_active ? 'Active' : 'Hidden' }}
                    </span>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $product->inventory === 0 ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                        {{ $product->inventory === null ? 'Unlimited stock' : $stockText }}
                    </span>
                    @if($product->category)<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $product->category }}</span>@endif
                    @foreach($product->tags ?? [] as $tag)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">#{{ $tag }}</span>
                    @endforeach
                </div>
                <p class="mt-2 text-2xl font-extrabold tabular-nums text-gray-900 dark:text-white">{{ $product->formattedPrice() }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-3">{{ $product->description ?: 'No description yet.' }}</p>

                <div class="flex flex-wrap items-center gap-2 mt-4">
                    <button wire:click="toggleActive" class="{{ $btnSolid }} px-3.5 py-2 text-xs">
                        {{ $product->is_active ? 'Hide from storefront' : 'Show in storefront' }}
                    </button>
                    <button wire:click="toggleReviews" class="px-3.5 py-2 rounded-xl text-xs font-semibold border {{ $product->reviews_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-400 dark:border-emerald-400/20' : 'bg-white dark:bg-[#1d1e2a] text-gray-500 border-gray-200 dark:border-white/[0.1] dark:text-gray-400' }}">
                        Reviews (this product): {{ $product->reviews_enabled ? 'ON' : 'OFF' }}
                    </button>
                    <button wire:click="toggleStoreReviews" class="px-3.5 py-2 rounded-xl text-xs font-semibold border {{ $this->storeReviewsOn ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-400 dark:border-emerald-400/20' : 'bg-white dark:bg-[#1d1e2a] text-gray-500 border-gray-200 dark:border-white/[0.1] dark:text-gray-400' }}">
                        Reviews (whole store): {{ $this->storeReviewsOn ? 'ON' : 'OFF' }}
                    </button>
                </div>
                @if($product->inventory !== null)
                <div class="flex items-center gap-2 mt-3">
                    <input type="number" min="1" wire:model="restockQty" placeholder="Qty"
                           class="w-20 px-2.5 py-1.5 rounded-lg text-sm bg-gray-50 dark:bg-white/[0.04] border border-gray-200 dark:border-white/[0.08]">
                    <button wire:click="addStock" class="px-3 py-1.5 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-bold">＋ Add stock</button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
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

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">
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
                    @if($imageUrl)
                        <img src="{{ \App\Models\Media::resolveRef($site->id, $imageUrl) }}" alt="" class="w-14 h-14 rounded-xl object-cover shrink-0">
                    @else
                        <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-white/[0.06] flex items-center justify-center text-xl">🛍️</div>
                    @endif
                    <x-asset-picker model="imageUrl" :site="$site" type="image" placeholder="Image URL, or pick from assets" />
                </div>
                @error('imageUrl') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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
                <button type="button" wire:click="closeForm" class="px-4 py-2.5 bg-white dark:bg-[#1d1e2a] border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
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

    {{-- ══ RIGHT rail: summary · related ══ --}}
    <x-slot:quick>
        <div class="{{ $panel }} p-5">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] mb-3" style="color:var(--primary)">Product summary</p>
            <dl class="space-y-2 text-[12.5px]">
                @foreach([
                    ['Price', $product->formattedPrice()],
                    ['Stock', $stockText],
                    ['Status', $product->is_active ? 'Active' : 'Hidden'],
                    ['Category', $product->category ?: '—'],
                    ['Revenue · 30 days', \App\Support\Money::format((int) round(array_sum($s['revenue']) * 100), $this->currency)],
                    ['Units · 30 days', array_sum($s['units'])],
                    ['Added', $product->created_at?->format('j M Y') ?? '—'],
                ] as [$k, $v])
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $k }}</dt>
                        <dd class="font-bold text-gray-900 dark:text-white truncate">{{ $v }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        @if($product->inventory === 0 || ! $product->image_url || (int) $product->price_cents <= 0 || $this->pendingReviews->isNotEmpty())
        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Needs attention</p>
            <div class="space-y-2">
                @if($product->inventory === 0)<p class="rounded-2xl px-3.5 py-2.5 text-[12.5px] font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-800 dark:text-rose-200">Out of stock — add stock above.</p>@endif
                @if(! $product->image_url)<button type="button" wire:click="edit" class="w-full text-left rounded-2xl px-3.5 py-2.5 text-[12.5px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-200">No photo yet — add one →</button>@endif
                @if((int) $product->price_cents <= 0)<button type="button" wire:click="edit" class="w-full text-left rounded-2xl px-3.5 py-2.5 text-[12.5px] font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-200">No price — it shows as free →</button>@endif
                @if($this->pendingReviews->isNotEmpty())<p class="rounded-2xl px-3.5 py-2.5 text-[12.5px] font-semibold bg-gray-50 dark:bg-white/[0.04] text-gray-800 dark:text-gray-100">{{ $this->pendingReviews->count() }} {{ Str::plural('review', $this->pendingReviews->count()) }} awaiting approval</p>@endif
            </div>
        </div>
        @endif

        <div class="{{ $panel }} p-5">
            <p class="text-[15px] font-extrabold text-gray-900 dark:text-white mb-3">Related</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach([
                    ['Store', 'All products', route('site.store', $site->name), true],
                    ['Orders', 'Fulfil & refund', route('site.orders', $site->name), true],
                    ['Assets', 'Product photos', route('media', $site->name), true],
                    ['Storefront', 'See it live ↗', url($site->name.'/store'), false],
                ] as [$label, $hint, $href, $nav])
                    <a href="{{ $href }}" @if($nav) wire:navigate @else target="_blank" @endif class="rounded-2xl px-3 py-2.5 bg-gray-50 dark:bg-white/[0.04] hover:ring-2 hover:ring-[color-mix(in_srgb,var(--primary)_30%,transparent)]">
                        <span class="block text-[12.5px] font-bold text-gray-900 dark:text-white">{{ $label }} →</span>
                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot:quick>
</x-tri-layout>

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
