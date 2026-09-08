<?php

namespace App\Livewire;

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProductsPage extends Component
{
    use WithFileUploads, WithPagination;

    public Site $site;

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $search = '';

    // Form
    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $inventory = '';

    public string $category = '';

    public string $tagsInput = '';

    /** Grid filter: '' = all categories. */
    public string $categoryFilter = '';

    public bool $is_active = true;

    public $photo;

    public ?string $existingImage = null;

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    /** Product being viewed in the read-only detail drawer. */
    public ?string $viewingId = null;

    public function getProductsProperty()
    {
        return $this->site->products()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderBy('sort')
            ->latest()
            ->paginate(9);
    }

    /** Distinct categories in use on this site (for filter chips + datalist). */
    public function getCategoriesProperty(): array
    {
        return $this->site->products()->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function filterCategory(string $category): void
    {
        $this->categoryFilter = $this->categoryFilter === $category ? '' : $category;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function show(string $id): void
    {
        $this->viewingId = $this->site->products()->findOrFail($id)->id;
    }

    public function closeView(): void
    {
        $this->viewingId = null;
        $this->restockQty = '';
    }

    /** Quick restock from the product view drawer. */
    public string $restockQty = '';

    public function addStock(string $id): void
    {
        $qty = (int) $this->restockQty;
        if ($qty < 1) {
            return;
        }
        $p = $this->site->products()->findOrFail($id);
        if ($p->inventory === null) {
            $this->errorMessage = 'This product has unlimited stock — set an inventory number first (Edit → More options).';

            return;
        }
        $p->adjustStock($qty, 'restock', null, auth()->id());
        $this->restockQty = '';
        $this->successMessage = "Added {$qty} to stock — {$p->fresh()->inventory} now available.";
    }

    public function getViewedProductProperty()
    {
        return $this->viewingId ? $this->site->products()->find($this->viewingId) : null;
    }

    /** Store-level product analytics: best sellers, most popular, most reviewed. */
    public function getInsightsProperty(): array
    {
        $since = now()->subDays(29)->startOfDay();

        $best = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.site_id', $this->site->id)
            ->whereIn('orders.status', ['paid', 'shipped', 'delivered', 'fulfilled'])
            ->where('orders.paid_at', '>=', $since)
            ->selectRaw('order_items.name as n, SUM(order_items.qty) as q')
            ->groupBy('n')->orderByDesc('q')->limit(5)->get();

        $popular = DB::table('product_events')
            ->join('products', 'products.id', '=', 'product_events.product_id')
            ->where('product_events.site_id', $this->site->id)
            ->where('product_events.created_at', '>=', $since)
            ->selectRaw('products.name as n, COUNT(*) as c')
            ->groupBy('n')->orderByDesc('c')->limit(6)->pluck('c', 'n');

        $reviewed = DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('product_reviews.site_id', $this->site->id)
            ->where('product_reviews.status', 'approved')
            ->selectRaw('products.name as n, COUNT(*) as c, AVG(product_reviews.rating) as a')
            ->groupBy('n')->orderByDesc('c')->limit(6)->get();

        return [
            'best_labels' => $best->pluck('n')->all(),
            'best_units' => $best->pluck('q')->map(fn ($q) => (int) $q)->all(),
            'popular' => $popular->map(fn ($c) => (int) $c)->all(),
            'reviewed' => $reviewed->mapWithKeys(fn ($r) => [$r->n.'  ★'.round($r->a, 1) => (int) $r->c])->all(),
        ];
    }

    /** Store-wide reviews master switch (site attribute, default ON). */
    public function getStoreReviewsOnProperty(): bool
    {
        return $this->site->getAttr('store_reviews_enabled') !== '0';
    }

    public function toggleStoreReviews(): void
    {
        $on = ! $this->storeReviewsOn;
        $this->site->setAttr('store_reviews_enabled', $on ? '1' : '0');
        $this->successMessage = $on
            ? 'Reviews are ON for the whole store.'
            : 'Reviews are OFF for the whole store — no product shows ratings or accepts new reviews.';
    }

    public function getCurrencyProperty(): string
    {
        return $this->site->currency ?? 'gbp';
    }

    public function getProductLimitProperty(): int
    {
        return (int) ($this->site->feature('store')['product_limit'] ?? 50);
    }

    public function create(): void
    {
        if ($this->site->products()->count() >= $this->productLimit) {
            $this->errorMessage = "You've reached the product limit ({$this->productLimit}). Raise it in Marketplace → Store settings.";

            return;
        }
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $this->viewingId = null; // detail drawer gives way to the editor
        $p = $this->site->products()->findOrFail($id);
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->description = $p->description ?? '';
        $this->price = (string) $p->priceMajor();
        $this->inventory = $p->inventory === null ? '' : (string) $p->inventory;
        $this->category = (string) ($p->category ?? '');
        $this->tagsInput = implode(', ', $p->tags ?? []);
        $this->is_active = $p->is_active;
        $this->existingImage = $p->image;
        $this->photo = null;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'inventory' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => (int) round(((float) $validated['price']) * 100),
            'currency' => $this->currency,
            'inventory' => $validated['inventory'] !== '' && $validated['inventory'] !== null ? (int) $validated['inventory'] : null,
            'is_active' => $this->is_active,
            'category' => trim($this->category) !== '' ? trim($this->category) : null,
            'tags' => collect(explode(',', $this->tagsInput))->map(fn ($t) => trim($t))->filter()->unique()->values()->all() ?: null,
        ];

        if ($this->photo) {
            $data['image'] = $this->photo->store('products', 'public');
        }

        if ($this->editingId) {
            $p = $this->site->products()->findOrFail($this->editingId);
            $oldInventory = $p->inventory;
            $p->update($data);
            // Direct inventory edits go into the audit trail as "manual".
            if ($oldInventory !== null && $data['inventory'] !== null && $data['inventory'] !== $oldInventory) {
                $p->stockMovements()->create([
                    'site_id' => $this->site->id,
                    'delta' => $data['inventory'] - $oldInventory,
                    'stock_after' => $data['inventory'],
                    'reason' => 'manual',
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
            $this->successMessage = 'Product updated.';
        } else {
            if ($this->site->products()->count() >= $this->productLimit) {
                $this->errorMessage = 'Product limit reached.';

                return;
            }
            // Ensure unique slug within the site
            $base = Str::slug($data['name']);
            $slug = $base;
            $i = 1;
            while ($this->site->products()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $this->site->products()->create($data + ['slug' => $slug]);
            $this->successMessage = 'Product created.';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleActive(string $id): void
    {
        $p = $this->site->products()->findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
    }

    public function delete(string $id): void
    {
        $this->site->products()->findOrFail($id)->delete();
        $this->successMessage = 'Product deleted.';
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'price', 'inventory', 'photo', 'existingImage', 'category', 'tagsInput']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.products-page');
    }
}
