<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Http\Response;

/**
 * Serves a site's built renderer shell (the Nuxt template app) at a domain
 * root — used for live custom domains, instant subdomains, and the signup
 * wizard's "here is your site" preview. The SPA then loads content from
 * /api/sites/{site}/... on the same origin.
 */
class LiveShell
{
    public function respond(Site $site): Response
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
        $html = preg_replace('/baseURL:"[^"]*"/', 'baseURL:"/",cdnURL:"'.$base.'"', $html, 1);

        // Site identity: expose a global AND make sure ?site= is present in the
        // URL before the app boots (templates resolve the site from the query).
        $inject = '<script>window.__OLUX_SITE__='.json_encode($site->name).';(function(){try{var u=new URL(location);'
            .'if(!u.searchParams.get("site")){u.searchParams.set("site",'.json_encode($site->name).');history.replaceState(null,"",u)}}catch(e){}})();</script>'
            .self::trackingBeacon($site);
        $html = preg_replace('/<head>/', '<head>'.$inject, $html, 1);

        // Site Properties output (icons, SEO, JSON-LD, analytics, consent,
        // custom scripts) — the same for every template. Never breaks a page.
        try {
            $html = preg_replace('#</head>#i', SiteHead::head($site)."\n</head>", $html, 1);
            $html = preg_replace('#</body>#i', SiteHead::bodyEnd($site)."\n</body>", $html, 1);
        } catch (\Throwable $e) {
            report($e);
        }

        // Free plan: a small "Made with Olux" badge (paid plans have none).
        if ($site->user?->currentSubscription()->showsBadge()) {
            $html = preg_replace('#</body>#i', self::badge()."\n</body>", $html, 1);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Olux-Live' => e($site->name),
        ]);
    }

    /** The Free-plan badge: fixed bottom-left, small, links to the Olux home page. */
    private static function badge(): string
    {
        $href = e(rtrim((string) config('app.url'), '/').'/?ref=badge');

        return '<a id="olux-badge" href="'.$href.'" target="_blank" rel="noopener" '
            .'style="position:fixed;left:12px;bottom:12px;z-index:2147483000;display:inline-flex;align-items:center;gap:6px;'
            .'padding:6px 10px;border-radius:999px;background:#111827;color:#fff;font:600 12px/1 system-ui,-apple-system,Segoe UI,sans-serif;'
            .'text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.18);opacity:.92">'
            .'<span style="width:8px;height:8px;border-radius:50%;background:#f97316"></span>Made with Olux</a>';
    }

    /**
     * Same-origin analytics beacon injected into every live page: pings
     * /api/sites/{name}/track on load and each SPA navigation so the
     * Analytics dashboard records real traffic. Must never break the site.
     */
    private static function trackingBeacon($site): string
    {
        $endpoint = json_encode('/api/sites/'.rawurlencode($site->name).'/track', JSON_UNESCAPED_SLASHES);

        return '<script>(function(){try{'
            .'var EP='.$endpoint.';'
            .'function sid(){try{var s=sessionStorage.getItem("_ovid");if(!s){s=Math.random().toString(36).slice(2)+Date.now().toString(36);sessionStorage.setItem("_ovid",s)}return s}catch(e){return null}}'
            .'var last=null;'
            .'function ping(){try{'
            .'var p=location.pathname+location.search;if(p===last)return;last=p;'
            .'var b=JSON.stringify({path:p,url:location.href,referrer:document.referrer||null,language:navigator.language,session_id:sid()});'
            .'var blob=new Blob([b],{type:"text/plain"});'
            .'if(!(navigator.sendBeacon&&navigator.sendBeacon(EP,blob))){fetch(EP,{method:"POST",body:b,headers:{"Content-Type":"text/plain"},keepalive:true}).catch(function(){})}'
            .'}catch(e){}}'
            .'var ps=history.pushState,rs=history.replaceState;'
            .'history.pushState=function(){ps.apply(this,arguments);setTimeout(ping,50)};'
            .'history.replaceState=function(){rs.apply(this,arguments);setTimeout(ping,50)};'
            .'addEventListener("popstate",function(){setTimeout(ping,50)});'
            .'if(document.readyState==="complete"||document.readyState==="interactive"){setTimeout(ping,300)}else{addEventListener("DOMContentLoaded",function(){setTimeout(ping,300)})}'
            .'}catch(e){}})();</script>';
    }
}
