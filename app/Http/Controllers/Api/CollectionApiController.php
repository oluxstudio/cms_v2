<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Site;
use App\Services\ContentVersioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Collections CRUD API — structured content lists (a field schema + items).
 *
 *   GET    /api/sites/{site}/collections                  → public collections + published items
 *   GET    /api/sites/{site}/collections/{id}             → one public collection
 *   POST   /api/sites/{site}/collections                  → create   (Bearer · collections.manage)
 *   PATCH  /api/sites/{site}/collections/{id}             → update   (Bearer · collections.manage)
 *   DELETE /api/sites/{site}/collections/{id}             → delete   (Bearer · collections.manage)
 *   POST   /api/sites/{site}/collections/{id}/items       → add item (Bearer · collections.manage)
 *   PATCH  /api/sites/{site}/collections/{id}/items/{item}→ update   (Bearer · collections.manage)
 *   DELETE /api/sites/{site}/collections/{id}/items/{item}→ delete   (Bearer · collections.manage)
 *
 * Public reads only expose collections with is_public = true and their
 * published items; token-authenticated writes return the full record
 * (all items, any status) in the response.
 */
class CollectionApiController extends Controller
{
    use ResolvesApiSite;

    /** @param bool $everything include all items regardless of status (else published only) */
    private function record(Collection $c, bool $everything = false): array
    {
        return $c->toApiArray(withItems: true, everything: $everything);
    }

    public function index(string $siteName): JsonResponse
    {
        $site = $this->publicSite($siteName);

        return response()->json([
            'collections' => $site->collections()->where('is_public', true)->with('items')->get()
                ->map(fn (Collection $c) => $this->record($c))->values(),
        ]);
    }

    public function show(string $siteName, string $id): JsonResponse
    {
        $collection = $this->publicSite($siteName)->collections()
            ->where('is_public', true)->with('items')->findOrFail($id);

        return response()->json(['collection' => $this->record($collection)]);
    }

    /**
     * Visitor engagement beacon (media views/plays). Mirrors the product
     * event endpoint: 204 always, silent no-op on any miss.
     */
    public function event(string $siteName, string $id, string $itemId, Request $request)
    {
        $site = \App\Models\Site::where('name', $siteName)->firstOrFail();
        $data = $request->validate([
            'event' => ['required', 'in:'.implode(',', \App\Models\CollectionItemEvent::EVENTS)],
            'session' => ['nullable', 'string', 'max:64'],
        ]);

        $item = \App\Models\CollectionItem::where('site_id', $site->id)
            ->whereKey($itemId)
            ->where('collection_id', $id)
            ->where('status', 'published')
            ->whereHas('collection', fn ($q) => $q->where('is_public', true))
            ->first();
        if ($item) {
            \App\Models\CollectionItemEvent::create([
                'site_id' => $site->id,
                'collection_id' => $item->collection_id,
                'collection_item_id' => $item->id,
                'event' => $data['event'],
                'session_hash' => $data['session'] ?? null,
                'created_at' => now(),
            ]);
        }

        return response()->noContent();
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fields' => ['sometimes', 'array', 'max:60'],
            'is_public' => ['sometimes', 'boolean'],
            'allow_submit' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', 'nullable', 'array'],
            'visibility.from' => ['nullable', 'date'],
            'visibility.until' => ['nullable', 'date', 'after_or_equal:visibility.from'],
            'visibility.days' => ['nullable', 'array'],
            'visibility.days.*' => ['integer', 'between:1,7'],
            'visibility.time_from' => ['nullable', 'date_format:H:i', 'required_with:visibility.time_until'],
            'visibility.time_until' => ['nullable', 'date_format:H:i', 'required_with:visibility.time_from'],
            'visibility.requires_content' => ['nullable', 'boolean'],
            'visibility.promo' => ['nullable', 'string', 'alpha_dash', 'max:64'],
        ]);
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'collections.manage');
        $data = $this->validated($request, creating: true);

        $collection = $site->collections()->create([
            'name' => $data['name'],
            'slug' => $data['name'],
            'type' => $data['type'] ?? 'list',
            'description' => $data['description'] ?? null,
            'fields' => $data['fields'] ?? [],
            'is_public' => $data['is_public'] ?? true,
            'allow_submit' => $data['allow_submit'] ?? false,
            'visibility' => $data['visibility'] ?? null,
        ]);

        return response()->json(['ok' => true, 'collection' => $this->record($collection->load('items'), everything: true)], 201);
    }

    public function update(Request $request, string $siteName, string $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'collections.manage');
        $collection = $site->collections()->findOrFail($id);
        app(ContentVersioner::class)->capture($collection, $request->attributes->get('api_token_user')?->name);
        $data = $this->validated($request, creating: false);

        if (isset($data['name'])) {
            $data['slug'] = $data['name'];
        }
        $collection->fill($data)->save();

        return response()->json(['ok' => true, 'collection' => $this->record($collection->load('items'), everything: true)]);
    }

    public function destroy(Request $request, string $siteName, string $id): JsonResponse
    {
        $collection = $this->manageableSite($request, $siteName, 'collections.manage')->collections()->findOrFail($id);
        $collection->items()->delete();
        $collection->delete();

        return response()->json(['ok' => true]);
    }

    /* ── Items ──────────────────────────────────────────────────────────── */

    private function itemValidated(Request $request, bool $creating): array
    {
        return $request->validate([
            'data' => [$creating ? 'required' : 'sometimes', 'array'],
            'status' => ['sometimes', 'in:published,pending,archived'],
        ]);
    }

    public function storeItem(Request $request, string $siteName, string $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'collections.manage');
        $collection = $site->collections()->findOrFail($id);
        app(ContentVersioner::class)->capture($collection, $request->attributes->get('api_token_user')?->name);
        $data = $this->itemValidated($request, creating: true);

        $item = $collection->items()->create([
            'position' => ((int) $collection->items()->max('position')) + 1,
            'site_id' => $site->id,
            'data' => $data['data'],
            'status' => $data['status'] ?? 'published',
        ]);

        return response()->json(['ok' => true, 'item' => ['id' => $item->id, 'data' => $item->data, 'status' => $item->status]], 201);
    }

    public function updateItem(Request $request, string $siteName, string $id, string $itemId): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'collections.manage');
        $collection = $site->collections()->findOrFail($id);
        app(ContentVersioner::class)->capture($collection, $request->attributes->get('api_token_user')?->name);
        $item = $collection->items()->findOrFail($itemId);
        $item->fill($this->itemValidated($request, creating: false))->save();

        return response()->json(['ok' => true, 'item' => ['id' => $item->id, 'data' => $item->data, 'status' => $item->status]]);
    }

    public function destroyItem(Request $request, string $siteName, string $id, string $itemId): JsonResponse
    {
        $collection = $this->manageableSite($request, $siteName, 'collections.manage')->collections()->findOrFail($id);
        app(ContentVersioner::class)->capture($collection, $request->attributes->get('api_token_user')?->name);
        $collection->items()->findOrFail($itemId)->delete();

        return response()->json(['ok' => true]);
    }
}
