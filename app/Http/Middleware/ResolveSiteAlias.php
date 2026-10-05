<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Old web addresses keep working after a site changes its address
 * (Site::changeAddress). When a route's {siteID}/{siteName} is one of a
 * site's previous names:
 *  - a plain page visit (GET in the browser) is redirected (301) to the same
 *    page under the new name, so bookmarks and shared links land correctly;
 *  - anything else (API reads, form posts, Stripe webhooks, Site Connect)
 *    is resolved in place to the new name — no redirect for clients that
 *    can't follow one.
 */
class ResolveSiteAlias
{
    private const PARAMS = ['siteID', 'siteName'];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        if (! $route) {
            return $next($request);
        }

        foreach (self::PARAMS as $param) {
            $old = $route->parameter($param);
            if (! is_string($old) || ! ($site = Site::forOldName($old))) {
                continue;
            }

            if ($request->isMethod('GET') && ! $request->is('api/*') && ! $request->expectsJson()) {
                $path = preg_replace('#(^|/)'.preg_quote($old, '#').'(?=/|$)#', '$1'.$site->name, $request->path(), 1);
                $qs = $request->getQueryString();

                return redirect('/'.ltrim($path, '/').($qs ? '?'.$qs : ''), 301);
            }

            $route->setParameter($param, $site->name);
        }

        return $next($request);
    }
}
