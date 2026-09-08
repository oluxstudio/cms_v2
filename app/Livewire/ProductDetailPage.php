<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dedicated admin page for one product: full editing, inventory history,
 * interest metrics (storefront views / add-to-basket), sales charts and
 * review moderation with a per-product ON/OFF switch.
 */
class ProductDetailPage extends Component
{
    use WithFileUploads;

    /** Order statuses that count as revenue. */
    private const PAID = ['paid', 'shipped', 'delivered', 'fulfilled'];

    public Site $site;

    public Product $product;

    // ── Edit form ────────────────────────────────────────────────
    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $inventory = '';

    public string $category = '';

    public string $tagsInput = '';

    public bool $is_active = true;

    public $photo;

    public bool $showForm = false;

    public string $restockQty = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(Site $site, Product $product): void
    {
        abort_unless($product->site_id === $site->id, 404);
        $this->site = $site;
        $this->product = $product;
        $this->fillForm();
    }

    private function fillForm(): void
    {
        $p = $this->product;
        $this->name = $p->name;
        $this->description = $p->description ?? '';
        $this->price = (string) $p->priceMajor();
        $this->inventory = $p->inventory === null ? '' : (string) $p->inventory;
        $this->category = (string) ($p->category ?? '');
        $this->tagsInput = implode(', ', $p->tags ?? []);
        $this->is_active = $p->is_active;
    }

    public function getCurrencyProperty(): string
    {
        return $this->site->currency ?? 'gbp';
    }

    public function getCategoriesProperty(): array
    {
        return $this->site->products()->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    // ── Actions ──────────────────────────────────────────────────
    public function edit(): void
    {
        $this->fillForm();
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
            'inventory' => $this->inventory !== '' ? (int) $this->inventory : null,
            'is_active' => $this->is_active,
            'category' => trim($this->category) !== '' ? trim($this->category) : null,
            'tags' => collect(explode(',', $this->tagsInput))->map(fn ($t) => trim($t))->filter()->unique()->values()->all() ?: null,
        ];
        if ($this->photo) {
            $data['image'] = $this->photo->store('products', 'public');
        }

        $old = $this->product->inventory;
        $this->product->update($data);
        if ($old !== null && $data['inventory'] !== null && $data['inventory'] !== $old) {
            $this->product->stockMovements()->create([
                'site_id' => $this->site->id,
                'delta' => $data['inventory'] - $old,
                'stock_after' => $data['inventory'],
                'reason' => 'manual',
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        }

        $this->photo = null;
        $this->product->refresh();
        $this->fillForm();
        $this->showForm = false;
        $this->successMessage = 'Product updated.';
    }

    public function toggleActive(): void
    {
        $this->product->update(['is_active' => ! $this->product->is_active]);
        $this->product->refresh();
        $this->is_active = $this->product->is_active;
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

    public function toggleReviews(): void
    {
        $this->product->update(['reviews_enabled' => ! $this->product->reviews_enabled]);
        $this->product->refresh();
        $this->successMessage = $this->product->reviews_enabled
            ? 'Reviews are ON — visitors can leave reviews for this product.'
            : 'Reviews are OFF — the storefront hides ratings and blocks new reviews.';
    }

    public function addStock(): void
    {
        $qty = (int) $this->restockQty;
        if ($qty < 1) {
            return;
        }
        if ($this->product->inventory === null) {
            $this->errorMessage = 'This product has unlimited stock — set an inventory number first.';

            return;
        }
        $this->product->adjustStock($qty, 'restock', null, auth()->id());
        $this->product->refresh();
        $this->fillForm();
        $this->restockQty = '';
        $this->successMessage = "Added {$qty} to stock — {$this->product->inventory} now available.";
    }

    public function approveReview(string $id): void
    {
        $this->product->reviews()->findOrFail($id)->update(['status' => 'approved']);
        $this->successMessage = 'Review approved — it now shows on the storefront.';
    }

    public function deleteReview(string $id): void
    {
        $this->product->reviews()->findOrFail($id)->delete();
        $this->successMessage = 'Review deleted.';
    }

    // ── Analytics ────────────────────────────────────────────────

    /** 30-day day labels shared by both charts. */
    private function days(): array
    {
        return collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString())->all();
    }

    /** Interest: daily view/add_to_cart series + totals + conversion. */
    public function getInterestProperty(): array
    {
        $since = now()->subDays(29)->startOfDay();
        $rows = $this->product->events()->where('created_at', '>=', $since)
            ->get(['event', 'created_at'])
            ->groupBy(fn ($e) => $e->event.'|'.$e->created_at->toDateString())
            ->map->count();

        $days = $this->days();
        $views = array_map(fn ($d) => (int) ($rows['view|'.$d] ?? 0), $days);
        $adds = array_map(fn ($d) => (int) ($rows['add_to_cart|'.$d] ?? 0), $days);
        $totalViews = array_sum($views);
        $totalAdds = array_sum($adds);

        return [
            'labels' => array_map(fn ($d) => date('j M', strtotime($d)), $days),
            'views' => $views,
            'adds' => $adds,
            'total_views' => $totalViews,
            'total_adds' => $totalAdds,
            'orders' => $this->paidItems()->where('orders.paid_at', '>=', $since)->distinct('order_items.order_id')->count('order_items.order_id'),
            'conversion' => $totalViews > 0 ? round($totalAdds / $totalViews * 100, 1) : null,
        ];
    }

    /** Base query: this product's order lines on paid-ish orders. */
    private function paidItems()
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.site_id', $this->site->id)
            ->whereIn('orders.status', self::PAID)
            ->where('order_items.product_id', $this->product->id);
    }

    /** Sales: 30-day units + revenue series and lifetime totals. */
    public function getSalesProperty(): array
    {
        $since = now()->subDays(29)->startOfDay();
        $rows = $this->paidItems()->where('orders.paid_at', '>=', $since)
            ->selectRaw('DATE(orders.paid_at) as d, SUM(order_items.qty) as units, SUM(order_items.qty * order_items.price_cents) as cents')
            ->groupBy('d')->get()->keyBy('d');

        $days = $this->days();

        return [
            'labels' => array_map(fn ($d) => date('j M', strtotime($d)), $days),
            'units' => array_map(fn ($d) => (int) ($rows[$d]->units ?? 0), $days),
            'revenue' => array_map(fn ($d) => round(($rows[$d]->cents ?? 0) / 100, 2), $days),
            'lifetime_units' => (int) $this->paidItems()->sum('order_items.qty'),
            'lifetime_revenue_cents' => (int) $this->paidItems()->sum(DB::raw('order_items.qty * order_items.price_cents')),
        ];
    }

    public function getMovementsProperty()
    {
        return $this->product->stockMovements()->with(['user:id,name'])->take(50)->get();
    }

    public function getPendingReviewsProperty()
    {
        return $this->product->reviews()->where('status', 'pending')->latest()->get();
    }

    public function getApprovedReviewsProperty()
    {
        return $this->product->reviews()->approved()->latest()->get();
    }

    public function render()
    {
        return view('livewire.product-detail-page');
    }
}
