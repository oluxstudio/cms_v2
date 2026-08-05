<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Abort with 404 unless the site (resolved from the route's {siteID}
     * admin slug or {siteName} public slug) has the given feature enabled.
     *
     * Usage: ->middleware('feature:store')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $slug = $request->route('siteID') ?? $request->route('siteName');

        abort_if($slug === null, 404);

        $site = Site::where('name', $slug)->first();

        abort_if($site === null || ! $site->hasFeature($feature), 404);

        // Make the resolved site available downstream to avoid a second query.
        $request->attributes->set('resolvedSite', $site);

        return $next($request);
    }
}
