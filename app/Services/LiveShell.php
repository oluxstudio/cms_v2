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
            .'if(!u.searchParams.get("site")){u.searchParams.set("site",'.json_encode($site->name).');history.replaceState(null,"",u)}}catch(e){}})();</script>';
        $html = preg_replace('/<head>/', '<head>'.$inject, $html, 1);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Olux-Live' => e($site->name),
        ]);
    }
}
