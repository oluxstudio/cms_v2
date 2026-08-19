<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the site for the token-context API (/api/site/...) so the client
 * only needs its key — no site name in the URL. Runs AFTER auth.token.
 *
 *   1. Site-scoped key  → the key's own site (the common, frictionless case).
 *   2. Unscoped key     → an `X-Olux-Site` header the user may access,
 *                         else the user's sole accessible site,
 *                         else 409 (ambiguous).
 *
 * The resolved name is injected as the `siteName` route parameter, so every
 * existing /api/sites/{siteName}/... controller is reused verbatim (and still
 * re-checks token scope + ability via ResolvesApiSite).
 */
class ResolveTokenSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->attributes->get('api_token');
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated — a valid API key is required.'], 401);
        }

        // 1. Key bound to a site → use it.
        $site = $token->site;

        // 2. Unscoped key → header, else the user's only site.
        if (! $site) {
            $user = $token->user;
            $accessible = Site::all()->filter(fn (Site $s) => $s->accessibleBy($user))->values();

            if ($header = $request->header('X-Olux-Site')) {
                $site = $accessible->firstWhere('name', $header);
                if (! $site) {
                    return response()->json(['message' => "This key cannot access the site '{$header}'."], 403);
                }
            } elseif ($accessible->count() === 1) {
                $site = $accessible->first();
            } else {
                return response()->json([
                    'message' => 'Ambiguous site — this key is not bound to a site. Send an X-Olux-Site header naming the site.',
                ], 409);
            }
        }

        // siteName must be the FIRST route parameter: scalar params are passed
        // to controller methods positionally, and every shared controller
        // signature is (…, string $siteName, …other params). Appending it after
        // the URL's own params (e.g. /collections/{id}) would shift them all.
        $route = $request->route();
        $urlParams = $route->parameters();
        foreach (array_keys($urlParams) as $key) {
            $route->forgetParameter($key);
        }
        $route->setParameter('siteName', $site->name);
        foreach ($urlParams as $key => $value) {
            $route->setParameter($key, $value);
        }

        return $next($request);
    }
}
