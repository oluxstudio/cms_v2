<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts sites LIVE on their custom domain. Runs globally, before routing:
 * when the request Host is a client domain (not the platform), resolve it to
 * the live Site and serve that site's built renderer shell for any page
 * path — the SPA then loads content from /api/sites/{site}/... on the same
 * origin. API/webhook/asset paths pass straight through untouched.
 */
class ServeLiveSite
{
    /** Path prefixes a live domain still needs from the backend itself. */
    private const PASS_THROUGH = ['api/*', 'preview/*', 'nuxt-preview/*', 'livewire/*', 'stripe/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($this->isPlatformHost($host) || $request->is(...self::PASS_THROUGH)) {
            return $next($request);
        }

        $bare = preg_replace('/^www\./', '', $host);
        $site = Site::where('live', true)
            ->where(fn ($q) => $q->where('domain', $bare)->orWhere('domain', $host))
            ->first();

        if (! $site || ! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        return $this->shell($site);
    }

    private function isPlatformHost(string $host): bool
    {
        $platform = array_filter(array_merge(
            [parse_url((string) config('app.url'), PHP_URL_HOST), 'localhost', '127.0.0.1'],
            (array) config('publishing.platform_hosts', []),
        ));

        return in_array($host, array_map('strtolower', $platform), true);
    }

    /**
     * Serve the built SPA shell at the domain root: rewrite its inline Nuxt
     * config so the router runs at "/" while assets keep loading from the
     * build's real public path, and seed the site identity for the app.
     */
    private function shell(Site $site): Response
    {
        $found = $site->liveShell();
        if (! $found) {
            // No renderer build yet — friendly holding page, never a dead 500.
            return response()->view('live-pending', ['site' => $site], 200);
        }

        [$index, $base] = $found;
        $html = (string) file_get_contents($index);

        // Router base → "/" (clean URLs on the domain); cdnURL → the build dir
        // so dynamic chunks still resolve to the existing files.
        $html = preg_replace(
            '/baseURL:"[^"]*"/',
            'baseURL:"/",cdnURL:"'.$base.'"',
            $html,
            1,
        );

        // Site identity: expose a global AND make sure ?site= is present in the
        // URL before the app boots (templates resolve the site from the query).
        $name = e($site->name);
        $inject = '<script>window.__OLUX_SITE__='.json_encode($site->name).';(function(){try{var u=new URL(location);'
            .'if(!u.searchParams.get("site")){u.searchParams.set("site",'.json_encode($site->name).');history.replaceState(null,"",u)}}catch(e){}})();</script>';
        $html = preg_replace('/<head>/', '<head>'.$inject, $html, 1);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Olux-Live' => $name,
        ]);
    }
}
