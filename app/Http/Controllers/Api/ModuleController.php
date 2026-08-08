<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Site;
use App\Support\FieldSchemaPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Standard JSON API for declarative modules, consumed by the in-site ModuleWidget.
 * Mirrors the Forms API (schema + validated submit) and adds public list/get.
 *   GET    /api/sites/{site}/modules/{key}/schema
 *   GET    /api/sites/{site}/modules/{key}/items
 *   GET    /api/sites/{site}/modules/{key}/items/{id}
 *   POST   /api/sites/{site}/modules/{key}/items
 */
class ModuleController extends Controller
{
    private function module(string $siteName, string $key): Module
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $module = $site->modules()->where('key', $key)->where('enabled', true)->with('collection')->first();
        abort_unless($module && $module->collection, 404);

        return $module;
    }

    /** Field schema + metadata for rendering the create form. */
    public function schema(string $siteName, string $key): JsonResponse
    {
        $module = $this->module($siteName, $key);
        $c = $module->collection;

        return response()->json([
            'key' => $module->key,
            'name' => $module->name,
            'description' => $module->description,
            'capabilities' => $module->capabilities,
            'fields' => FieldSchemaPresenter::fields($c->fields ?? []),
        ]);
    }

    /** Published entries (only when the module lists publicly). */
    public function items(string $siteName, string $key): JsonResponse
    {
        $module = $this->module($siteName, $key);
        abort_unless(($module->capabilities['list'] ?? false) && $module->collection->is_public, 404);

        $items = $module->collection->items()
            ->where('status', 'published')
            ->latest()
            ->limit(200)
            ->get(['id', 'data', 'created_at'])
            ->map(fn ($i) => ['id' => $i->id, 'data' => $i->data, 'created_at' => $i->created_at?->toIso8601String()]);

        return response()->json([
            'fields' => FieldSchemaPresenter::fields($module->collection->fields ?? []),
            'items' => $items,
        ]);
    }

    public function item(string $siteName, string $key, string $id): JsonResponse
    {
        $module = $this->module($siteName, $key);
        abort_unless(($module->capabilities['get'] ?? false) && $module->collection->is_public, 404);

        $item = $module->collection->items()->where('status', 'published')->findOrFail($id);

        return response()->json(['id' => $item->id, 'data' => $item->data, 'created_at' => $item->created_at?->toIso8601String()]);
    }

    /** Create an entry (validated against the collection schema). */
    public function store(string $siteName, string $key, Request $request): JsonResponse
    {
        $module = $this->module($siteName, $key);
        $c = $module->collection;
        abort_unless(($module->capabilities['submit'] ?? false) && $c->allow_submit, 403);

        $validated = $request->validate($c->buildValidationRules(), $c->buildValidationMessages());

        // Persist only the defined schema keys.
        $keys = collect($c->fields ?? [])->pluck('key')->all();
        $data = array_intersect_key($validated, array_flip($keys));

        $item = $c->items()->create([
            'site_id' => $c->site_id,
            'data' => $data,
            // Held for review unless the collection opts into auto-publish.
            'status' => $c->auto_publish ? 'published' : 'pending',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Submitted successfully.',
            'id' => $item->id,
        ], 201);
    }
}
