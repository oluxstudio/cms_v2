<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Template;
use App\Services\DesignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Site › Design apply / revert — library-gated, tenancy-scoped. */
class SiteDesignController extends Controller
{
    private function site(Request $request, string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->accessibleBy($request->user()), 403);

        return $site;
    }

    public function apply(Request $request, string $siteName, DesignService $design): JsonResponse
    {
        $site = $this->site($request, $siteName);
        $data = $request->validate([
            'template_id' => ['required', 'string'],
            'version_id' => ['nullable', 'string'],
        ]);
        $template = Template::where('status', 'published')->findOrFail($data['template_id']);

        return response()->json($design->apply($request->user(), $site, $template, $data['version_id'] ?? null));
    }

    public function revert(Request $request, string $siteName, DesignService $design): JsonResponse
    {
        $site = $this->site($request, $siteName);

        return response()->json($design->revert($request->user(), $site));
    }
}
