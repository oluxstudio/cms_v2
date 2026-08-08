<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Components CRUD API — classic content components (named bags of typed
 * nodes) that can stand alone or be attached to pages.
 *
 *   GET    /api/sites/{site}/components        → all components + nodes (public)
 *   GET    /api/sites/{site}/components/{id}   → one component (public)
 *   POST   /api/sites/{site}/components        → create        (Bearer token)
 *   PATCH  /api/sites/{site}/components/{id}   → update        (Bearer token)
 *   DELETE /api/sites/{site}/components/{id}   → delete        (Bearer token)
 *
 * Writes accept: name, description, nodes[] ({label,type,value,parent?,order?,
 * description?}) and page_ids[] to sync page attachments. Nodes are replaced
 * wholesale when a nodes array is present.
 */
class ComponentApiController extends Controller
{
    use \App\Http\Controllers\Api\Concerns\ResolvesApiSite;

    public function index(string $siteName): JsonResponse
    {
        $site = $this->publicSite($siteName);

        return response()->json([
            'components' => $site->contentComponents()->with(['nodes', 'pages'])->get()
                ->map(fn ($c) => $c->payload(withPages: true))->values(),
        ]);
    }

    public function show(string $siteName, int $id): JsonResponse
    {
        $component = $this->publicSite($siteName)
            ->contentComponents()->with(['nodes', 'pages'])->findOrFail($id);

        return response()->json(['component' => $component->payload(withPages: true)]);
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'components.manage');
        $data = $this->validated($request);

        $component = Component::create([
            'site_id' => $site->id,
            'name' => $data['name'],
            'author' => $request->attributes->get('api_token_user')?->name ?? 'API',
            'created_by' => $request->attributes->get('api_token_user')?->id,
            'source' => 'api',
            'description' => $data['description'] ?? null,
            'tags' => $data['tags'] ?? null,
        ]);
        $this->syncNodes($component, $data['nodes'] ?? []);
        $this->syncPages($site, $component, $data['page_ids'] ?? null);

        return response()->json(['ok' => true, 'component' => $component->fresh(['nodes', 'pages'])->payload(withPages: true)], 201);
    }

    public function update(Request $request, string $siteName, int $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'components.manage');
        $component = Component::where('site_id', $site->id)->findOrFail($id);
        $data = $this->validated($request, updating: true);

        $attrs = [];
        if (array_key_exists('name', $data)) {
            $attrs['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $attrs['description'] = $data['description'];
        }
        if (array_key_exists('tags', $data)) {
            $attrs['tags'] = $data['tags'];
        }
        if ($attrs !== []) {
            $component->update($attrs);
        }

        if (array_key_exists('nodes', $data)) {
            $this->syncNodes($component, $data['nodes'] ?? []);
        }
        $this->syncPages($site, $component, $data['page_ids'] ?? null);

        return response()->json(['ok' => true, 'component' => $component->fresh(['nodes', 'pages'])->payload(withPages: true)]);
    }

    public function destroy(Request $request, string $siteName, int $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'components.manage');
        Component::where('site_id', $site->id)->findOrFail($id)->delete(); // nodes + pivots cascade

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'nodes' => ['sometimes', 'array'],
            'nodes.*.label' => ['required_with:nodes', 'string', 'max:120'],
            'nodes.*.type' => ['required_with:nodes', 'in:'.implode(',', Node::TYPES)],
            'nodes.*.value' => ['nullable', 'string', 'max:5000'],
            'nodes.*.parent' => ['nullable', 'integer', 'min:0'],
            'nodes.*.order' => ['nullable', 'integer', 'min:0'],
            'nodes.*.description' => ['nullable', 'string', 'max:255'],
            'page_ids' => ['sometimes', 'nullable', 'array'],
            'page_ids.*' => ['integer'],
        ]);
    }

    /** Replace the component's nodes with the given definitions. */
    private function syncNodes(Component $component, array $nodes): void
    {
        $component->nodes()->delete();
        foreach (array_values($nodes) as $i => $n) {
            $component->nodes()->create([
                'label' => $n['label'],
                'type' => $n['type'],
                'value' => $n['value'] ?? '',
                'parent' => (int) ($n['parent'] ?? 0),
                'order' => (int) ($n['order'] ?? $i),
                'description' => $n['description'] ?? null,
            ]);
        }
    }

    /** Sync page attachments (order preserved/appended). Null = leave as-is. */
    private function syncPages(Site $site, Component $component, ?array $pageIds): void
    {
        if ($pageIds === null) {
            return;
        }
        $valid = $site->pages()->whereIn('id', $pageIds)->pluck('id');
        $attach = [];
        foreach ($valid as $pageId) {
            $current = $component->pages()->where('pages.id', $pageId)->first();
            $order = $current->pivot->order
                ?? ((int) \DB::table('page_component')->where('page_id', $pageId)->max('order') + 1);
            $attach[$pageId] = ['order' => $order];
        }
        $component->pages()->sync($attach);
    }
}
