<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicPageController extends Controller
{
    /**
     * Public page view. The legacy blade/component renderer is gone — pages
     * are built with blocks and render through the generic block renderer,
     * so this route simply forwards to it.
     */
    public function show(Request $request, string $siteName, string $pageUrl)
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        $url = '/'.ltrim($pageUrl, '/');

        $preview = $request->boolean('preview') && $site->accessibleBy(Auth::user());
        Page::where('site_id', $site->id)
            ->where('url', $url)
            ->when(! $preview, fn ($q) => $q->where('is_published', true))
            ->firstOrFail();

        $target = $site->previewUrl($url);
        abort_unless($target, 404, 'Renderer not built. Run: php artisan nuxt:preview-build');

        return redirect($target);
    }
}
