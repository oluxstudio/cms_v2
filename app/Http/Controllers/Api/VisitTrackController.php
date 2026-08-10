<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\VisitRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/sites/{siteName}/track
 *
 * The visitor-tracking beacon. Called by the client site (navigator.sendBeacon)
 * on each page view. Guarded by site.origin + throttle:track. Recording is
 * best-effort and always returns 204 — a beacon must never surface an error to
 * the visitor's browser.
 */
class VisitTrackController extends Controller
{
    public function store(Request $request, string $siteName, VisitRecorder $recorder): Response
    {
        $site = Site::where('name', $siteName)->first();

        if ($site) {
            try {
                // Beacons are sent as text/plain (to dodge a cross-origin CORS
                // preflight sendBeacon can't do), so parse the raw JSON body too.
                $data = $request->all();
                if (empty($data)) {
                    $data = json_decode($request->getContent(), true) ?: [];
                }

                $recorder->record($site, $request, [
                    'path' => $data['path'] ?? null,
                    'url' => $data['url'] ?? null,
                    'referrer' => $data['referrer'] ?? null,
                    'session_id' => $data['session_id'] ?? null,
                    'language' => $data['language'] ?? null,
                ]);
            } catch (\Throwable $e) {
                report($e); // never break the beacon
            }
        }

        return response()->noContent(); // 204
    }
}
