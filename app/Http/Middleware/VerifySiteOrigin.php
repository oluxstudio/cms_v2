<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-site abuse guard for VISITOR write endpoints (forms, leads,
 * bookings): when a browser sends an Origin/Referer, its host must belong
 * to the site being posted to — its custom domain (± www), the platform
 * hosts, localhost dev, or an `allowed_origins` site attribute (comma
 * separated hosts).
 *
 * Requests WITHOUT an Origin/Referer (static-site builds, curl, server-to-
 * server) pass — those are covered by rate limits + the honeypot. This
 * middleware only stops OTHER websites posting into a site from a browser.
 */
class VerifySiteOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $originHost = $this->requestOriginHost($request);

        if ($originHost === null) {
            return $next($request);
        }

        $site = Site::where('name', $request->route('siteName'))->first();
        if (! $site) {
            return $next($request); // route will 404 on its own
        }

        if ($this->hostAllowed($originHost, $site)) {
            return $next($request);
        }

        return response()->json(['message' => 'Submissions from this origin are not allowed for this site.'], 403);
    }

    private function requestOriginHost(Request $request): ?string
    {
        $raw = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (! $raw) {
            return null;
        }

        return strtolower(parse_url($raw, PHP_URL_HOST) ?: '') ?: null;
    }

    private function hostAllowed(string $host, Site $site): bool
    {
        $bare = Str::after($host, 'www.');

        $allowed = collect([
            $site->domain ? strtolower($site->domain) : null,
            $site->domain ? strtolower(Str::after($site->domain, 'www.')) : null,
            'localhost', '127.0.0.1',
        ])
            ->merge(collect(config('publishing.platform_hosts', []))->map(fn ($h) => strtolower($h)))
            ->merge(collect(explode(',', (string) $site->getAttr('allowed_origins')))->map(fn ($h) => strtolower(trim($h))))
            ->merge([strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?: '')])
            ->filter()->unique();

        return $allowed->contains($host) || $allowed->contains($bare);
    }
}
