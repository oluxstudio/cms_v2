<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * connect.js control plane. GET /api/v1/connect/status tells the embedded
 * script which mode to run in (collect vs hydrate) and, in hydrate mode, the
 * exact page.json URL for the current path. The site is resolved from the
 * bearer token by the `token.site` middleware.
 */
class ConnectController extends Controller
{
    /**
     * GET /api/v1/connect/status?path=/about
     *
     * @return JsonResponse {mode, schemaVersion, pageJsonUrl}
     */
    public function status(Request $request): JsonResponse
    {
        // Injected by token.site from the authenticated key.
        $site = Site::where('name', $request->route('siteName'))->firstOrFail();
        $connection = $site->connection;

        $mode = $connection?->mode ?? 'collect';
        $slug = $this->slugForPath($request->query('path', '/'));

        return response()->json([
            'mode' => $mode,
            'schemaVersion' => (int) config('site_connect.schema_version', 1),
            // Absolute URL so connect.js can fetch it from any origin.
            'pageJsonUrl' => $mode === 'hydrate'
                ? route('api.v1.page-json', ['siteName' => $site->name, 'slug' => $slug])
                : null,
        ]);
    }

    /** Map a request path to a page.json slug — the same rule the generator uses. */
    private function slugForPath(string $path): string
    {
        $trimmed = trim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        return $trimmed === '' ? 'index' : Str::slug(str_replace('/', '-', $trimmed));
    }
}
