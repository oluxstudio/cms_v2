<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pages CRUD API — a site's pages plus their EAV attributes.
 *
 *   GET    /api/sites/{site}/pages       → all pages + attributes (public)
 *   GET    /api/sites/{site}/pages/{id}  → one page (public)
 *   POST   /api/sites/{site}/pages       → create  (Bearer token · pages.manage)
 *   PATCH  /api/sites/{site}/pages/{id}  → update  (Bearer token · pages.manage)
 *   DELETE /api/sites/{site}/pages/{id}  → delete  (Bearer token · pages.manage)
 *
 * Writes accept: name, url, keywords, is_published and an `attributes` map —
 * each key is set via setAttr(); pass null as a value to forget that key.
 * Full page content (components → nodes) lives on GET /content and GET /page.
 */
class PageApiController extends Controller
{
    private function publicSite(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    private function manageableSite(Request $request, string $siteName): Site
    {
        $site = $this->publicSite($siteName);
        $user = $request->attributes->get('api_token_user');
        abort_unless($user && $site->allows($user, 'pages.manage'), 403, 'This token cannot manage pages on this site.');

        return $site;
    }

    private function record(Page $page): array
    {
        return [
            'id' => $page->id,
            'name' => $page->name,
            'url' => $page->url,
            'keywords' => $page->keywords,
            'is_published' => (bool) $page->is_published,
            'attributes' => $page->attrMap(),
            'components' => $page->components()->count(),
            'created_at' => $page->created_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    public function index(string $siteName): JsonResponse
    {
        $site = $this->publicSite($siteName);

        return response()->json([
            'pages' => $site->pages()->orderBy('id')->get()->map(fn (Page $p) => $this->record($p))->values(),
        ]);
    }

    public function show(string $siteName, int $id): JsonResponse
    {
        return response()->json(['page' => $this->record($this->publicSite($siteName)->pages()->findOrFail($id))]);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'url' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'array', 'max:60'],
            'attributes.*' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function applyAttributes(Page $page, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $value === null ? $page->forgetAttr((string) $key) : $page->setAttr((string) $key, (string) $value);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName);
        $data = $this->validated($request, creating: true);

        abort_if($site->pages()->where('url', $data['url'])->exists(), 422, 'A page with this url already exists on the site.');

        $page = $site->pages()->create([
            'name' => $data['name'],
            'url' => $data['url'],
            'keywords' => $data['keywords'] ?? '',
            'is_published' => $data['is_published'] ?? true,
        ]);
        $this->applyAttributes($page, $data['attributes'] ?? []);

        return response()->json(['ok' => true, 'page' => $this->record($page)], 201);
    }

    public function update(Request $request, string $siteName, int $id): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName);
        $page = $site->pages()->findOrFail($id);
        $data = $this->validated($request, creating: false);

        if (isset($data['url']) && $site->pages()->where('url', $data['url'])->whereKeyNot($page->id)->exists()) {
            abort(422, 'A page with this url already exists on the site.');
        }

        $page->fill(collect($data)->only(['name', 'url', 'keywords', 'is_published'])->all())->save();
        $this->applyAttributes($page, $data['attributes'] ?? []);

        return response()->json(['ok' => true, 'page' => $this->record($page)]);
    }

    public function destroy(Request $request, string $siteName, int $id): JsonResponse
    {
        $this->manageableSite($request, $siteName)->pages()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
