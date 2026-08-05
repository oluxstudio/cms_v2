<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * GET /api/media
     * List all publicly available media across sites.
     * Query: ?type=image|video|document  ?search=  ?site=  ?per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::query()->with('site:id,name')->latest();

        $this->applyFilters($query, $request);

        if ($request->filled('site')) {
            $query->whereHas('site', fn ($q) => $q->where('name', $request->string('site')));
        }

        $perPage = min((int) $request->integer('per_page', 24), 100);
        $page    = $query->paginate($perPage);

        return $this->respond($page);
    }

    /**
     * GET /api/sites/{siteName}/media
     * List a single site's media.
     * Query: ?type=  ?search=  ?per_page=
     */
    public function siteIndex(Request $request, string $siteName): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        $query = Media::where('site_id', $site->id)->with('site:id,name')->latest();
        $this->applyFilters($query, $request);

        $perPage = min((int) $request->integer('per_page', 24), 100);

        return $this->respond($query->paginate($perPage));
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('type') && in_array($request->string('type')->toString(), Media::TYPES, true)) {
            $query->where('file_type', $request->string('type'));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->string('search') . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('alt_text', 'like', $term));
        }
    }

    private function respond($paginator): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())->map(fn (Media $m) => [
                'id'         => $m->id,
                'name'       => $m->name,
                'type'       => $m->file_type,
                'url'        => $m->publicUrl(),
                'size'       => $m->size,
                'alt'        => $m->alt_text,
                'site'       => $m->site?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
