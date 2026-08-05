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
                {{ $this->products->count() }}/{{ $this->productLimit }} products ·
                <a href="{{ url($site->name.'/store') }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">View public storefront ↗</a>
            </p>
        </div>
        <button wire:click="create"
                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Product
        </button>
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
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl border border-gray-100 dark:border-white/[0.05] shadow-sm overflow-hidden">
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
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 line-clamp-2">{{ $p->description }}</p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-2">{{ $p->inventory === null ? 'Unlimited stock' : $p->inventory.' in stock' }}</p>

                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-50 dark:border-white/[0.04]">
                    <button wire:click="edit({{ $p->id }})" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                    <button wire:click="toggleActive({{ $p->id }})" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ $p->is_active ? 'Hide' : 'Show' }}</button>
                    <button wire:click="delete({{ $p->id }})" data-confirm="Delete this product?" class="ml-auto text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Create / edit modal --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.45)">
        <div class="bg-white dark:bg-[#1d1e2a] rounded-2xl shadow-2xl w-full max-w-lg p-7 border border-gray-100 dark:border-white/[0.05] max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingId ? 'Edit product' : 'New product' }}</h2>
                <button wire:click="$set('showForm', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <x-field.text label="Name" model="name" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <x-field.textarea label="Description" model="description" rows="3" class="resize-none" />
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-field.text label="Price ({{ \App\Support\Money::symbol($this->currency) }})" model="price" type="number" step="0.01" min="0" placeholder="9.99" />
                        @error('price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <x-field.text label="Inventory (blank = ∞)" model="inventory" type="number" min="0" placeholder="Unlimited" />
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

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create product' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2.5 border border-gray-200 dark:border-white/[0.08] text-sm font-semibold text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>
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
