<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\BlockTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One mutation API, two clients: the Vue editor calls these endpoints from
 * drag-and-drop / inspector actions; the AI assistant's tool dispatcher calls
 * the same service methods with source='ai'. Validation lives in the service,
 * so parity is structural.
 */
class BlockKitController extends Controller
{
    public function __construct(private BlockTreeService $trees)
    {
    }

    public function tree(Request $request, Page $page): JsonResponse
    {
        $this->authorizePage($request, $page);

        return response()->json([
            'tree'      => $this->trees->tree($page),
            'catalogue' => ['types' => config('blockkit.types'), 'style' => config('blockkit.style')],
        ]);
    }

    public function insert(Request $request, Page $page): JsonResponse
    {
        $this->authorizePage($request, $page);
        $data = $request->validate([
            'parent_id' => 'required|string',
            'position'  => 'required|integer|min:0',
            'blocks'    => 'required|array|min:1',
        ]);

        return $this->attempt(fn () => [
            'created' => $this->trees->insertBlocks($page, $data['parent_id'], $data['position'], $data['blocks']),
            'tree'    => $this->trees->tree($page),
        ]);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $this->authorizePage($request, $page);
        $data = $request->validate([
            'block_id' => 'required|string',
            'props'    => 'sometimes|array',
            'style'    => 'sometimes|array',
            'meta'     => 'sometimes|array',
        ]);

        return $this->attempt(fn () => [
            'block' => $this->trees->updateBlock($page, $data['block_id'], $data)->only(['id', 'type', 'props', 'style', 'meta']),
        ]);
    }

    public function move(Request $request, Page $page): JsonResponse
    {
        $this->authorizePage($request, $page);
        $data = $request->validate([
            'block_id'      => 'required|string',
            'new_parent_id' => 'required|string',
            'position'      => 'required|integer|min:0',
        ]);

        return $this->attempt(fn () => [
            'moved' => $this->trees->moveBlock($page, $data['block_id'], $data['new_parent_id'], $data['position'])->id,
            'tree'  => $this->trees->tree($page),
        ]);
    }

    public function delete(Request $request, Page $page): JsonResponse
    {
        $this->authorizePage($request, $page);
        $data = $request->validate([
            'block_ids' => 'required|array|min:1',
            'confirmed' => 'sometimes|boolean',
        ]);

        return $this->attempt(fn () => [
            'deleted' => $this->trees->deleteBlocks($page, $data['block_ids'], (bool) ($data['confirmed'] ?? false)),
            'tree'    => $this->trees->tree($page),
        ]);
    }

    public function duplicate(Request $request, Page $page): JsonResponse
    {
        $this->authorizePage($request, $page);
        $data = $request->validate(['block_id' => 'required|string']);

        return $this->attempt(fn () => [
            'copy' => $this->trees->duplicateBlock($page, $data['block_id'])->id,
            'tree' => $this->trees->tree($page),
        ]);
    }

    /** Service exceptions → 422 with the human-readable reason. */
    private function attempt(callable $op): JsonResponse
    {
        try {
            return response()->json($op());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function authorizePage(Request $request, Page $page): void
    {
        abort_unless($request->user() && $page->site && $page->site->accessibleBy($request->user()), 403);
    }
}
