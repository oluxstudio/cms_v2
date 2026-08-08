<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Site;
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

    /** @param bool $everything include private fields + all items regardless of status */
    private function record(Collection $c, bool $everything = false): array
    {
        $items = $everything ? $c->items : $c->items->where('status', 'published')->values();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'type' => $c->type,
            'description' => $c->description,
            'fields' => $c->fields ?? [],
            'is_public' => (bool) $c->is_public,
            'allow_submit' => (bool) $c->allow_submit,
            'items' => $items->map(fn (CollectionItem $i) => [
                'id' => $i->id,
                'data' => $i->data ?? [],
                'status' => $i->status,
                'created_at' => $i->created_at?->toIso8601String(),
            ])->values(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
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

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fields' => ['sometimes', 'array', 'max:60'],
            'is_public' => ['sometimes', 'boolean'],
            'allow_submit' => ['sometimes', 'boolean'],
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
        ]);

        return response()->json(['ok' => true, 'collection' => $this->record($collection->load('items'), everything: true)], 201);
    }

    public function update(Request $request, string $siteName, string $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'collections.manage');
        $collection = $site->collections()->findOrFail($id);
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
        $data = $this->itemValidated($request, creating: true);

        $item = $collection->items()->create([
            'site_id' => $site->id,
            'data' => $data['data'],
            'status' => $data['status'] ?? 'published',
        ]);

        return response()->json(['ok' => true, 'item' => ['id' => $item->id, 'data' => $item->data, 'status' => $item->status]], 201);
    }

    public function updateItem(Request $request, string $siteName, string $id, string $itemId): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'collections.manage');
        $item = $site->collections()->findOrFail($id)->items()->findOrFail($itemId);
        $item->fill($this->itemValidated($request, creating: false))->save();

        return response()->json(['ok' => true, 'item' => ['id' => $item->id, 'data' => $item->data, 'status' => $item->status]]);
    }

    public function destroyItem(Request $request, string $siteName, string $id, string $itemId): JsonResponse
    {
        $this->manageableSite($request, $siteName, 'collections.manage')->collections()->findOrFail($id)->items()->findOrFail($itemId)->delete();

        return response()->json(['ok' => true]);
    }
}
