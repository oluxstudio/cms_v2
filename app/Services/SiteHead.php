<?php

namespace App\Services;

use App\Models\Site;
use App\Support\SiteProperties;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * What every live site gets from its Site Properties, whatever template it
 * uses: <head> tags (icons, manifest, description, social cards, robots,
 * Search Console, canonical, LocalBusiness JSON-LD, analytics), the cookie
 * banner + custom scripts before </body>, and the host-level files
 * (robots.txt, sitemap.xml, site.webmanifest). Values may come from the Edit
 * page (unvalidated), so everything is re-checked and escaped here.
 */
class SiteHead
{
    public static function head(Site $site, ?Request $request = null): string
    {
        $request ??= request();
        $p = SiteProperties::payload($site);
        $origin = self::origin($site, $request);
        $out = [];

        // Icons + manifest
        if ($icons = $p['icons']) {
            foreach ([32, 48] as $s) {
                if (! empty($icons[$s])) {
                    $out[] = '<link rel="icon" type="image/png" sizes="'.$s.'x'.$s.'" href="'.e($icons[$s]).'">';
                }
            }
            if (! empty($icons[180])) {
                $out[] = '<link rel="apple-touch-icon" sizes="180x180" href="'.e($icons[180]).'">';
            }
            $out[] = '<link rel="manifest" href="/site.webmanifest">';
        } elseif ($p['icon']) {
            $out[] = '<link rel="icon" href="'.e($p['icon']).'">';
        }
        if ($color = self::hex($site->theme['accent'] ?? null)) {
            $out[] = '<meta name="theme-color" content="'.$color.'">';
        }

        // Search + social defaults (templates' own per-page tags still apply)
        $description = $p['seo']['meta_description'] ?: ($p['business']['description'] ?: $p['tagline']);
        if ($description) {
            $out[] = '<meta name="description" content="'.e($description).'" data-olux-default>';
            $out[] = '<meta property="og:description" content="'.e($description).'">';
        }
        $out[] = '<meta property="og:site_name" content="'.e($p['name']).'">';
        $out[] = '<meta property="og:type" content="website">';
        $out[] = '<meta property="og:url" content="'.e($origin.'/'.ltrim($request->path(), '/')).'">';
        if ($p['share_image']) {
            $out[] = '<meta property="og:image" content="'.e($p['share_image']).'">';
            $out[] = '<meta name="twitter:card" content="summary_large_image">';
        }
        if ($p['seo']['noindex']) {
            $out[] = '<meta name="robots" content="noindex, nofollow">';
        }
        $token = SiteProperties::value($site, 'search_console_token');
        if (preg_match('/^[A-Za-z0-9_\-]{10,100}$/', $token)) {
            $out[] = '<meta name="google-site-verification" content="'.$token.'">';
        }
        $out[] = '<link rel="canonical" href="'.e($origin.($request->path() === '/' ? '/' : '/'.$request->path())).'">';

        // Structured data
        $ld = SiteProperties::schemaOrg($site, $p, $origin.'/');
        $out[] = '<script type="application/ld+json">'.json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';

        // Analytics — straight away, or only after cookie consent.
        $loaders = self::trackingLoaders($p['tracking']);
        if ($loaders) {
            $js = 'window.__oluxTrack=function(){if(window.__oluxTracked)return;window.__oluxTracked=1;'.implode('', $loaders).'};';
            $js .= $p['legal']['cookie_consent']
                ? 'try{if(localStorage.getItem("olux-consent")==="yes")window.__oluxTrack()}catch(e){}'
                : 'window.__oluxTrack();';
            $out[] = '<script>'.$js.'</script>';
        }

        if ($custom = self::customScript($site, 'head')) {
            $out[] = $custom;
        }

        return implode("\n", $out);
    }

    /** Before </body>: the cookie banner (when on) and custom body scripts. */
    public static function bodyEnd(Site $site): string
    {
        $p = SiteProperties::payload($site);
        $out = [];
        if ($p['legal']['cookie_consent']) {
            $msg = $p['legal']['cookie_message'] ?: 'We use cookies to understand how our site is used.';
            $policy = $p['legal']['policies']['cookies'] ?? $p['legal']['policies']['privacy'] ?? null;
            $link = $policy && preg_match('#^(https?://|/)#i', $policy) ? ' <a href="'.e($policy).'" style="color:inherit;text-decoration:underline">Learn more</a>' : '';
            $out[] = '<div id="olux-consent" role="dialog" aria-label="Cookie consent" style="display:none;position:fixed;left:16px;right:16px;bottom:16px;z-index:2147483000;max-width:560px;margin:0 auto;padding:14px 16px;border-radius:16px;background:#111827;color:#fff;font:14px/1.45 system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)">'
                .'<p style="margin:0 0 10px">'.e($msg).$link.'</p>'
                .'<div style="display:flex;gap:8px;justify-content:flex-end">'
                .'<button type="button" data-c="no" style="padding:8px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.3);background:transparent;color:#fff;cursor:pointer">Reject</button>'
                .'<button type="button" data-c="yes" style="padding:8px 14px;border-radius:10px;border:0;background:#fff;color:#111827;font-weight:700;cursor:pointer">Accept</button>'
                .'</div></div>'
                .'<script>(function(){try{var b=document.getElementById("olux-consent");if(!b)return;var v=localStorage.getItem("olux-consent");'
                .'if(!v)b.style.display="block";b.addEventListener("click",function(e){var c=e.target.getAttribute("data-c");if(!c)return;'
                .'localStorage.setItem("olux-consent",c);b.style.display="none";if(c==="yes"&&window.__oluxTrack)window.__oluxTrack()})}catch(e){}})();</script>';
        }
        if ($custom = self::customScript($site, 'body')) {
            $out[] = $custom;
        }

        return implode("\n", $out);
    }

    public static function manifest(Site $site): array
    {
        $p = SiteProperties::payload($site);

        return array_filter([
            'name' => $p['name'],
            'short_name' => $p['short_name'] ?: mb_substr($p['name'], 0, 12),
            'description' => $p['tagline'],
            'start_url' => '/',
            'display' => 'standalone',
            'lang' => $p['locale']['language'],
            'theme_color' => self::hex($site->theme['accent'] ?? null),
            'background_color' => '#ffffff',
            'icons' => collect($p['icons'])->only([192, 512])
                ->map(fn ($url, $size) => ['src' => $url, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any maskable'])
                ->values()->all(),
        ]);
    }

    public static function robots(Site $site, Request $request): string
    {
        $p = SiteProperties::payload($site);
        if ($p['seo']['noindex']) {
            return "User-agent: *\nDisallow: /\n";
        }

        return "User-agent: *\nAllow: /\n".($p['seo']['sitemap'] ? 'Sitemap: '.self::origin($site, $request)."/sitemap.xml\n" : '');
    }

    /** Published pages (minus any a page marks noindex); null when the sitemap is switched off. */
    public static function sitemap(Site $site, Request $request): ?string
    {
        $p = SiteProperties::payload($site);
        if (! $p['seo']['sitemap'] || $p['seo']['noindex']) {
            return null;
        }
        $origin = self::origin($site, $request);
        $urls = $site->livePages()->where('is_published', true)->get()
            ->reject(fn ($page) => str_contains(strtolower((string) $page->getAttr('robots', '')), 'noindex'))
            ->map(fn ($page) => '<url><loc>'.e($origin.(($page->url ?? '/') === '/' ? '/' : '/'.ltrim($page->url, '/'))).'</loc>'
                .'<lastmod>'.$page->updated_at?->toDateString().'</lastmod></url>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>';
    }

    /**
     * The www ⇄ apex redirect a custom domain should get, or null. Moving to
     * www only happens once www resolves (the edge only routes/certifies www
     * when it does), so a redirect can never land on a host without HTTPS.
     */
    public static function canonicalRedirect(Site $site, Request $request): ?string
    {
        $domain = strtolower((string) $site->domain);
        $host = strtolower($request->getHost());
        if ($domain === '' || ! in_array($host, [$domain, 'www.'.$domain], true)) {
            return null; // instant subdomains stay as they are
        }
        $wantWww = SiteProperties::value($site, 'canonical_host') === 'www';
        $target = $wantWww ? 'www.'.$domain : $domain;
        if ($host === $target || ($wantWww && ! self::resolves('www.'.$domain))) {
            return null;
        }

        return $request->getScheme().'://'.$target.$request->getRequestUri();
    }

    public static function inMaintenance(Site $site): bool
    {
        return SiteProperties::payload($site)['status']['maintenance'];
    }

    // ── helpers ────────────────────────────────────────────────────

    private static function origin(Site $site, Request $request): string
    {
        $domain = strtolower((string) $site->domain);
        $host = strtolower($request->getHost());
        if ($domain !== '' && in_array($host, [$domain, 'www.'.$domain], true)) {
            $host = SiteProperties::value($site, 'canonical_host') === 'www' ? 'www.'.$domain : $domain;
        }

        return $request->getScheme().'://'.$host;
    }

    /** @return list<string> JS snippets that load each configured tracker */
    private static function trackingLoaders(array $t): array
    {
        $add = fn (string $src, array $attrs = []) => 'var s=document.createElement("script");s.async=1;s.src='.json_encode($src, JSON_UNESCAPED_SLASHES).';'
            .collect($attrs)->map(fn ($v, $k) => 's.setAttribute('.json_encode($k).','.json_encode($v).');')->implode('')
            .'document.head.appendChild(s);';
        $js = [];
        if (preg_match('/^G-[A-Z0-9]{4,12}$/i', $t['ga4_id'] ?? '')) {
            $id = json_encode(strtoupper($t['ga4_id']));
            $js[] = $add('https://www.googletagmanager.com/gtag/js?id='.strtoupper($t['ga4_id']))
                .'window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments)};gtag("js",new Date());gtag("config",'.$id.');';
        }
        if (preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/i', $t['plausible_domain'] ?? '')) {
            $js[] = $add('https://plausible.io/js/script.js', ['data-domain' => strtolower($t['plausible_domain'])]);
        }
        if (preg_match('/^[0-9]{6,20}$/', $t['meta_pixel_id'] ?? '')) {
            $js[] = '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};'
                .'if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;'
                .'s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");'
                .'fbq("init",'.json_encode($t['meta_pixel_id']).');fbq("track","PageView");';
        }

        return $js;
    }

    /** Custom scripts only run while the owner's plan includes premium features. */
    private static function customScript(Site $site, string $slot): ?string
    {
        $code = trim((string) $site->getAttr(config("site-properties.scripts.{$slot}"), ''));
        if ($code === '' || ! $site->user?->currentSubscription()->allowsPremium()) {
            return null;
        }

        return $code;
    }

    private static function hex(?string $color): ?string
    {
        return is_string($color) && preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? $color : null;
    }

    private static function resolves(string $host): bool
    {
        return Cache::remember('edge-resolves:'.$host, now()->addMinutes(10), fn () => gethostbyname($host) !== $host);
    }
}
