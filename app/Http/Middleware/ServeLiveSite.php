<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Services\LiveShell;
use App\Services\SiteHead;
use App\Support\SiteProperties;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts sites LIVE on their own address. Runs globally, before routing: when
 * the request Host is not the platform, resolve it to a Site — a verified
 * live custom domain, or an instant subdomain ({site}.{base}) — and serve
 * that site's built renderer shell for any page path. The SPA then loads
 * content from /api/sites/{site}/... on the same origin. API/webhook/asset
 * paths pass straight through untouched.
 */
class ServeLiveSite
{
    /** Path prefixes a live domain still needs from the backend itself. */
    private const PASS_THROUGH = ['api/*', 'preview/*', 'nuxt-preview/*', 'livewire/*', 'stripe/*', 'internal/*', 'storage/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($this->isPlatformHost($host) || $request->is(...self::PASS_THROUGH)) {
            return $next($request);
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $site = self::siteForHost($host);
        if (! $site) {
            // An old subdomain of a site that changed its address → its new one.
            if ($to = self::movedSubdomainUrl($host, $request)) {
                return redirect()->away($to, 301);
            }

            return $next($request);
        }

        // Site Properties at the domain level: preferred host, crawler files,
        // app manifest and maintenance mode.
        if ($to = SiteHead::canonicalRedirect($site, $request)) {
            return redirect()->away($to, 301);
        }
        switch ($request->path()) {
            case 'robots.txt':
                return response(SiteHead::robots($site, $request), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            case 'sitemap.xml':
                $xml = SiteHead::sitemap($site, $request);

                return $xml === null ? response('Not found', 404) : response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
            case 'site.webmanifest':
                return response()->json(SiteHead::manifest($site), 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
        }
        if (SiteHead::inMaintenance($site)) {
            return response()->view('live-maintenance', ['site' => $site, 'p' => SiteProperties::payload($site)], 503)
                ->header('Retry-After', '3600')
                ->header('Cache-Control', 'no-store');
        }

        return app(LiveShell::class)->respond($site);
    }

    /** Custom domain (must be live) first, then instant subdomain (always on). */
    public static function siteForHost(string $host): ?Site
    {
        $host = strtolower($host);
        $bare = preg_replace('/^www\./', '', $host);

        return Site::where('live', true)
            ->where(fn ($q) => $q->where('domain', $bare)->orWhere('domain', $host))
            ->first()
            ?? Site::forSubdomainHost($host);
    }

    /** {old}.{base}/path → https://{new}.{base}/path, or null when $host isn't an old subdomain. */
    public static function movedSubdomainUrl(string $host, Request $request): ?string
    {
        $base = (string) config('publishing.subdomain_base');
        if ($base === '' || ! str_ends_with($host, '.'.$base)) {
            return null;
        }
        $site = Site::forOldName(substr($host, 0, -strlen('.'.$base)));
        if (! $site || ! ($new = $site->subdomainHost())) {
            return null;
        }

        return 'https://'.$new.$request->getRequestUri();
    }

    private function isPlatformHost(string $host): bool
    {
        $platform = array_filter(array_merge(
            [parse_url((string) config('app.url'), PHP_URL_HOST), 'localhost', '127.0.0.1'],
            (array) config('publishing.platform_hosts', []),
        ));
        // The bare subdomain base (and www.) is the marketing site, never a tenant.
        if ($base = (string) config('publishing.subdomain_base')) {
            $platform[] = $base;
            $platform[] = 'www.'.$base;
        }

        return in_array($host, array_map('strtolower', $platform), true);
    }
}
