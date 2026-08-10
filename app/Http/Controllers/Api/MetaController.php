<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Form;
use App\Models\Node;
use Illuminate\Http\JsonResponse;

/**
 * Public metadata for API/MCP clients — the enum vocabularies the write
 * endpoints validate against, surfaced from the canonical model constants so
 * external tooling (the MCP server) never drifts from the backend.
 *
 *   GET /api/meta → { node_types, form_field_types, collection_field_types, post_statuses }
 */
class MetaController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'node_types' => Node::TYPES,
            'form_field_types' => Form::FIELD_TYPES,
            'collection_field_types' => Collection::FIELD_TYPES,
            'post_statuses' => ['draft', 'published'],
            'collection_item_statuses' => ['published', 'pending', 'archived'],
        ]);
    }
}
