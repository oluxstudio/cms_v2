<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Services\SiteConnect\PageJsonPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/site/connect/publish (Bearer · publish.manage)
 *
 * Publishes page.json for every live page, so client tooling (cms-seed.mjs)
 * can make its changes visible to connected sites without touching the UI.
 */
class SitePublishController extends Controller
{
    use ResolvesApiSite;

    public function __invoke(Request $request, string $siteName, PageJsonPublisher $publisher): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'publish.manage');

        $versions = [];
        foreach ($site->livePages()->get() as $page) {
            $versions[$page->url] = $publisher->publish($page)['version'];
        }

        return response()->json(['ok' => true, 'pages' => count($versions), 'versions' => $versions]);
    }
}
