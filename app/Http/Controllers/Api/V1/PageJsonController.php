<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;
use App\Services\SiteConnect\PageJsonGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GET /api/v1/sites/{siteName}/pages/{slug}.json
 *
 * The per-page content contract a client site hydrates from. Serves the last
 * PUBLISHED JSON from the disk (authoritative, versioned, CDN-cacheable); falls
 * back to generating live from current content when nothing is published yet
 * (so the CMS preview always has something to render).
 *
 * Public + read-only by design (it IS the site's own published content, the
 * same trust boundary as content.json). Tenant-scoped: a slug only resolves
 * within its own site, so there is no path to another tenant's JSON here.
 */
class PageJsonController extends Controller
{
    public function __construct(private PageJsonGenerator $generator) {}

    public function show(string $siteName, string $slug): JsonResponse
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        $page = $this->resolvePage($site, $slug);

        // Prefer the published artefact; regenerate live only as a fallback.
        if ($page->page_json_path) {
            $disk = config('site_connect.disk', 'public');
            if (Storage::disk($disk)->exists($page->page_json_path)) {
                return response()->json(
                    json_decode(Storage::disk($disk)->get($page->page_json_path), true)
                );
            }
        }

        return response()->json($this->generator->generate($page));
    }

    /** Resolve a page within THIS site by its page.json slug (home → "index"). */
    private function resolvePage(Site $site, string $slug): Page
    {
        $url = $slug === 'index' ? '/' : '/'.str_replace('-', '/', $slug);

        return Page::where('site_id', $site->id)
            ->where(fn ($q) => $q->where('url', $url)->orWhere('url', '/'.$slug))
            ->firstOr(function () use ($site, $slug) {
                // Last resort: match by the generator's slugging of each page url.
                $page = $site->livePages()->get()
                    ->first(fn (Page $p) => $this->slugOf($p) === $slug);

                abort_unless($page, 404);

                return $page;
            });
    }

    private function slugOf(Page $page): string
    {
        $url = trim((string) $page->url, '/');

        return $url === '' ? 'index' : Str::slug(str_replace('/', '-', $url));
    }
}
