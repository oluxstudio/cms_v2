<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiSite;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/site/connect-token (Bearer · publish.manage)
 *
 * Returns the site's PUBLIC Site Connect key (the one baked into the
 * connect.js script tag), minting one if none exists. Lets the client build
 * fetch it with the management key, so .env only needs OLUX_CMS / OLUX_SITE /
 * OLUX_API_KEY. Only retrievable connect keys are ever returned — management
 * keys are hash-only and cannot come out of this endpoint.
 */
class ConnectTokenController extends Controller
{
    use ResolvesApiSite;

    public function __invoke(Request $request, string $siteName): JsonResponse
    {
        $site = $this->manageableSite($request, $siteName, 'publish.manage');

        $existing = ApiToken::where('site_id', $site->id)
            ->whereNotNull('plain')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()->first();

        $raw = $existing?->plainValue();
        if (! $raw) {
            [, $raw] = ApiToken::mintConnect($site);
        }

        return response()->json(['site' => $site->name, 'token' => $raw]);
    }
}
