<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithLayoutMode;
use App\Models\Order;
use App\Models\Site;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The Store — product catalogue on the house 3-rail layout: stock / sales
 * tiles left, the product grid (search · filter · category · sort · layout)
 * centre, sales summary · needs attention · top products · related right.
 */
class ProductsPage extends Component
{
    use WithLayoutMode;
    use WithPagination;

    /** Order statuses that count as money in. */
    private const PAID = ['paid', 'shipped', 'delivered', 'fulfilled'];

    /** At or below this many units (and above 0) a product counts as low stock. */
    public const LOW_STOCK = 5;

    public const FILTERS = ['all', 'active', 'hidden', 'out', 'low'];

    public const SORTS = ['newest', 'best', 'price_asc', 'price_desc', 'stock'];

    public Site $site;

    public bool $showForm = false;

    public ?string $editingId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Listing filter: all | active | hidden | out | low */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** Listing order: newest | best | price_asc | price_desc | stock */
    #[Url(except: 'newest')]
    public string $sort = 'newest';

    // Form
    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $inventory = '';

    public string $category = '';

    public string $tagsInput = '';

    /** Grid filter: '' = all categories. */
    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';

    public bool $is_active = true;

    /** Product picture — a URL from the asset picker (or pasted). */
    public string $imageUrl = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->initLayout('store', 'grid');
    }

    /** Product being viewed in the read-only detail drawer. */
    public ?string $viewingId = null;

    public function getProductsProperty()
    {
        $term = trim($this->search);

        return $this->site->products()
            // Units sold on paid orders — one grouped subquery, no N+1.
            ->withSum(['orderItems as sold_units' => fn ($q) => $q->whereIn('order_id', $this->paidOrderIds())], 'qty')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->filter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filter === 'hidden', fn ($q) => $q->where('is_active', false))
            ->when($this->filter === 'out', fn ($q) => $q->where('inventory', 0))
            ->when($this->filter === 'low', fn ($q) => $q->whereBetween('inventory', [1, self::LOW_STOCK]))
            ->when($this->sort === 'best', fn ($q) => $q->orderByDesc('sold_units'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price_cents'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('price_cents'))
            // Unlimited (null) stock sorts last; scarce stock first.
            ->when($this->sort === 'stock', fn ($q) => $q->orderByRaw('inventory IS NULL')->orderBy('inventory'))
            ->when($this->sort === 'newest', fn ($q) => $q->orderBy('sort'))
            ->latest()
            ->paginate(12);
    }

    /** Subquery: this site's orders that count as revenue. */
    private function paidOrderIds()
    {
        return Order::select('id')->where('site_id', $this->site->id)->whereIn('status', self::PAID);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'newest';
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Rail numbers — catalogue health, 30-day sales with the previous 30 for
     * comparison, this month's revenue, orders to fulfil, top products. A
     * handful of grouped queries regardless of catalogue size.
     */
    public function getStoreStatsProperty(): array
    {
        $currency = $this->currency;
        $products = $this->site->products()->get(['id', 'name', 'image', 'price_cents', 'inventory', 'is_active', 'category']);

        $since = now()->subDays(29)->startOfDay();
        $prevSince = now()->subDays(59)->startOfDay();
        $paid = fn () => $this->site->orders()->whereIn('status', self::PAID);

        $window = $paid()->where('paid_at', '>=', $prevSince)
            ->selectRaw('CASE WHEN paid_at >= ? THEN 1 ELSE 0 END as cur, COUNT(*) as n, SUM(total_cents) as cents', [$since->toDateTimeString()])
            ->groupBy('cur')->get()->keyBy('cur');
        $cur = $window[1] ?? null;
        $prev = $window[0] ?? null;
        $revenue30 = (int) ($cur->cents ?? 0);
        $orders30 = (int) ($cur->n ?? 0);
        $prevRevenue = (int) ($prev->cents ?? 0);

        // Daily revenue for the 30-day sparkline (one grouped query).
        $byDay = $paid()->where('paid_at', '>=', $since)
            ->selectRaw('DATE(paid_at) as d, SUM(total_cents) as cents')
            ->groupBy('d')->pluck('cents', 'd');
        $daily = collect(range(29, 0))->map(fn ($i) => (int) ($byDay[now()->subDays($i)->toDateString()] ?? 0))->all();

        // Top products by 30-day revenue (order lines on paid orders).
        $top = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.site_id', $this->site->id)
            ->whereIn('orders.status', self::PAID)
            ->where('orders.paid_at', '>=', $since)
            ->selectRaw('order_items.product_id as pid, MAX(order_items.name) as name, SUM(order_items.qty) as units, SUM(order_items.qty * order_items.price_cents) as cents')
            ->groupBy('order_items.product_id')->orderByDesc('cents')->limit(5)->get();
        $unitsSold = (int) DB::table('order_items')->whereIn('order_id', $paid()->where('paid_at', '>=', $since)->select('id'))->sum('qty');

        $out = $products->filter(fn ($p) => $p->inventory === 0)->values();
        $low = $products->filter(fn ($p) => $p->inventory !== null && $p->inventory > 0 && $p->inventory <= self::LOW_STOCK)->sortBy('inventory')->values();
        $active = $products->where('is_active', true)->count();

        return [
            'total' => $products->count(),
            'active' => $active,
            'hidden' => $products->count() - $active,
            'out' => $out->count(),
            'outList' => $out,
            'low' => $low->count(),
            'lowList' => $low,
            'noImage' => $products->filter(fn ($p) => trim((string) $p->image) === '')->values(),
            'noPrice' => $products->filter(fn ($p) => (int) $p->price_cents <= 0)->values(),
            'units' => (int) $products->sum(fn ($p) => (int) $p->inventory),
            'unlimited' => $products->whereNull('inventory')->count(),
            'categories' => $products->pluck('category')->filter()->countBy()->sortDesc()->all(),
            'monthRevenue' => Money::format((int) $paid()->where('paid_at', '>=', now()->startOfMonth())->sum('total_cents'), $currency),
            'toFulfil' => $this->site->orders()->where('status', 'paid')->count(),
            'revenue30' => Money::format($revenue30, $currency),
            'revenue30Cents' => $revenue30,
            'orders30' => $orders30,
            'units30' => $unitsSold,
            'aov30' => Money::format($orders30 > 0 ? (int) round($revenue30 / $orders30) : 0, $currency),
            'revDelta' => $prevRevenue > 0 ? (int) round(($revenue30 - $prevRevenue) / $prevRevenue * 100) : null,
            'daily' => $daily,
            'top' => $top->map(fn ($r) => [
                'id' => $r->pid,
                'name' => $r->name,
                'units' => (int) $r->units,
                'cents' => (int) $r->cents,
                'revenue' => Money::format((int) $r->cents, $currency),
            ])->all(),
            'pendingReviews' => (int) DB::table('product_reviews')->where('site_id', $this->site->id)->where('status', 'pending')->count(),
        ];
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
        // The field shows what is stored (an @media ref); legacy upload paths show as their URL.
        $this->imageUrl = str_starts_with((string) $p->image, '@media/') ? (string) $p->image : (string) $p->image_url;
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
            'imageUrl' => ['nullable', 'string', 'max:2048'],
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

        $data['image'] = trim($this->imageUrl) !== '' ? trim($this->imageUrl) : null;

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
        $this->reset(['editingId', 'name', 'description', 'price', 'inventory', 'imageUrl', 'category', 'tagsInput']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.products-page');
    }
}
