<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Services\LiveShell;
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
    private const PASS_THROUGH = ['api/*', 'preview/*', 'nuxt-preview/*', 'livewire/*', 'stripe/*', 'up'];

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

        return $site ? app(LiveShell::class)->respond($site) : $next($request);
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
