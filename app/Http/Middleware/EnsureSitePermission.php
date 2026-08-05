<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level RBAC gate: ->middleware('perm:pages.view').
 * Resolves the site from the {siteID} slug and 403s unless the
 * authenticated user's role (or ownership) grants the permission.
 */
class EnsureSitePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $slug = $request->route('siteID') ?? $request->route('siteName');
        $site = is_string($slug) ? Site::where('name', $slug)->first() : null;

        // Unknown site → let the controller produce its own 404.
        if ($site && ! $site->allows($request->user(), $permission)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
