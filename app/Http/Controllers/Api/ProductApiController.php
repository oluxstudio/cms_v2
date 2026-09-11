<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Store product CRUD for client tooling and the MCP server (token context).
 *
 *   GET    /api/site/products/manage         → full catalogue incl. inactive  (Bearer · store.manage)
 *   POST   /api/site/products/manage         → create
 *   PATCH  /api/site/products/manage/{slug}  → update
 *   DELETE /api/site/products/manage/{slug}  → delete
 *
 * (Also mounted under /api/sites/{siteName}/products/manage…) The public,
 * unauthenticated storefront reads stay on /products — these management
 * routes use a /manage segment so the public {slug} route never shadows them.
 */
class ProductApiController extends Controller
{
    use ResolvesApiSite;

    private function record(Product $p): array
    {
        return [
            'id' => $p->id, 'slug' => $p->slug, 'name' => $p->name,
            'description' => $p->description, 'category' => $p->category,
            'tags' => array_values($p->tags ?? []),
            'price_cents' => (int) $p->price_cents, 'price' => $p->formattedPrice(),
            'currency' => $p->currency, 'image' => $p->image ? Storage::url($p->image) : null,
            'inventory' => $p->inventory, 'in_stock' => $p->inStock(),
            'is_active' => (bool) $p->is_active, 'reviews_enabled' => (bool) $p->reviews_enabled,
            'sort' => (int) $p->sort,
        ];
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:60'],
            'price_cents' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'inventory' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'reviews_enabled' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer'],
        ]);
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'store.manage');

        return response()->json(['products' => $site->products()->orderBy('sort')->latest()->get()
            ->map(fn (Product $p) => $this->record($p))->values()]);
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'store.manage');
        $data = $this->validated($request, creating: true);

        $product = $site->products()->create($data + [
            'currency' => $data['currency'] ?? ($site->currency ?: 'GBP'),
            'is_active' => $data['is_active'] ?? true,
            'reviews_enabled' => $data['reviews_enabled'] ?? true,
            'slug' => $data['slug'] ?? '',
        ]);

        return response()->json(['ok' => true, 'product' => $this->record($product)], 201);
    }

    public function update(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'store.manage');
        $product = $site->products()->where('slug', $slug)->firstOrFail();
        $product->update($this->validated($request, creating: false));

        return response()->json(['ok' => true, 'product' => $this->record($product->refresh())]);
    }

    public function destroy(Request $request, string $siteName, string $slug): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'store.manage');
        $site->products()->where('slug', $slug)->firstOrFail()->delete();

        return response()->json(['ok' => true]);
    }
}
