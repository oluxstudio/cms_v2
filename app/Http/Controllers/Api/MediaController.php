<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Site;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    use \App\Http\Controllers\Api\Concerns\ResolvesApiSite;

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

    private function payloadFor(Media $m): array
    {
        return [
            'id'         => $m->id,
            'name'       => $m->name,
            'type'       => $m->file_type,
            'url'        => $m->publicUrl(),
            'size'       => $m->size,
            'alt'        => $m->alt_text,
            'site'       => $m->site?->name,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function respond($paginator): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())->map(fn (Media $m) => $this->payloadFor($m))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /* ── Assets CRUD (Bearer token · media.manage) ──────────────────────── */

    /**
     * POST /api/sites/{siteName}/media
     * Create an asset — either upload a file (multipart `file`) or register
     * an external one by `url` (+ name, type, alt).
     */
    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'media.manage');
        $data = $request->validate([
            'file' => ['required_without:url', 'file', 'max:51200'],
            'url' => ['required_without:file', 'nullable', 'url', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:'.implode(',', Media::TYPES)],
            'alt' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->hasFile('file')) {
            $media = app(MediaStore::class)->store($site, $request->file('file'));
            $media->update(array_filter(['name' => $data['name'] ?? null, 'alt_text' => $data['alt'] ?? null]));
        } else {
            $media = Media::create([
                'site_id' => $site->id,
                'name' => $data['name'] ?? basename(parse_url($data['url'], PHP_URL_PATH) ?: 'asset'),
                'file_type' => $data['type'] ?? 'image',
                'url' => $data['url'],
                'size' => '—',
                'alt_text' => $data['alt'] ?? null,
            ]);
        }

        return response()->json(['ok' => true, 'asset' => $this->payloadFor($media->fresh('site'))], 201);
    }

    /**
     * PATCH /api/sites/{siteName}/media/{id}
     * Rename / re-describe an asset (name, alt; url + type for external assets).
     */
    public function update(Request $request, string $siteName, int $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'media.manage');
        $media = Media::where('site_id', $site->id)->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'type' => ['sometimes', 'in:'.implode(',', Media::TYPES)],
        ]);

        if (isset($data['url']) && Str::startsWith($media->url, '/storage/')) {
            abort(422, 'The url of an uploaded file cannot be changed — delete it and upload again.');
        }

        $media->fill([
            'name' => $data['name'] ?? $media->name,
            'alt_text' => array_key_exists('alt', $data) ? $data['alt'] : $media->alt_text,
            'url' => $data['url'] ?? $media->url,
            'file_type' => $data['type'] ?? $media->file_type,
        ])->save();

        return response()->json(['ok' => true, 'asset' => $this->payloadFor($media->fresh('site'))]);
    }

    /**
     * DELETE /api/sites/{siteName}/media/{id}
     * Delete an asset; uploaded files are removed from disk too.
     */
    public function destroy(Request $request, string $siteName, int $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'media.manage');
        $media = Media::where('site_id', $site->id)->findOrFail($id);

        if (Str::startsWith($media->url, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $media->url));
        }
        $media->delete();

        return response()->json(['ok' => true]);
    }
}
