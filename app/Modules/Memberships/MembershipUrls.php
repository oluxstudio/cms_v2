<?php

namespace App\Modules\Memberships;

use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** URL + naming helpers for the membership flows. */
class MembershipUrls
{
    public static function siteTitle(Site $site): string
    {
        return ucwords(str_replace('-', ' ', (string) $site->name));
    }

    public static function magicLink(Site $site, string $raw): string
    {
        return route('memberships.public.auth', [$site->name, $raw]);
    }

    public static function nextRenewal(string $interval): Carbon
    {
        return $interval === 'year' ? now()->addYear() : now()->addMonth();
    }

    /**
     * A visitor-supplied return URL, kept only when it points at the site
     * itself (custom domain ± www, its subdomain, the platform's own host,
     * localhost dev, or an `allowed_origins` host) — never an open redirect.
     */
    public static function safeReturn(Site $site, ?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || mb_strlen($url) > 500 || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }
        $allowed = collect([
            $site->domain ? strtolower($site->domain) : null,
            $site->domain ? strtolower(Str::after($site->domain, 'www.')) : null,
            $site->subdomainHost() ? strtolower($site->subdomainHost()) : null,
            'localhost', '127.0.0.1',
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
        ])
            ->merge(collect(config('publishing.platform_hosts', []))->map(fn ($h) => strtolower((string) $h)))
            ->merge(collect(explode(',', (string) $site->getAttr('allowed_origins')))->map(fn ($h) => strtolower(trim($h))))
            ->filter()->unique();

        return $allowed->contains($host) || $allowed->contains(Str::after($host, 'www.')) ? $url : null;
    }

    /** Where to send a member after sign-in, carrying their token in the URL fragment (never sent to servers). */
    public static function withToken(string $url, string $token): string
    {
        $base = Str::before($url, '#');

        return $base.'#member_token='.urlencode($token);
    }
}
