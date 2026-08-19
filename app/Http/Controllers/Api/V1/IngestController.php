<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SiteConnect\CrawlSiteJob;
use App\Jobs\SiteConnect\IngestPageJob;
use App\Models\PageIngestion;
use App\Models\Site;
use App\Models\SiteConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/connect/ingest
 *
 * Receives a page snapshot from connect.js (collect mode): sanitised-on-store,
 * staged as a PageIngestion, then extracted/classified on the queue. The seed
 * page also kicks off a same-host, SSRF-guarded crawl of its internal links.
 *
 * Requires the `connect:ingest` ability on the site's token.
 */
class IngestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $site = Site::where('name', $request->route('siteName'))->firstOrFail();

        // Ability gate — connect keys are scoped to connect:ingest + content:read.
        // The token is REQUIRED: this endpoint must never be permissive-by-default.
        $token = $request->attributes->get('api_token');
        abort_unless($token?->can('connect:ingest'), 403,
            'This token does not carry the connect:ingest ability.');

        // Size caps: the bearer token is public in client HTML, so anyone can
        // POST here — bound every field or a hostile client fills the DB.
        $data = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
            'html' => ['required', 'string', 'max:'.config('site_connect.ingest.max_html_bytes')],
            'styles' => ['nullable', 'string', 'max:'.config('site_connect.ingest.max_css_bytes')],
            'meta' => ['nullable', 'array:title,description,ogImage'],
            'meta.*' => ['nullable', 'string', 'max:2048'],
            'links' => ['nullable', 'array', 'max:'.config('site_connect.ingest.max_links')],
            'links.*' => ['string', 'max:2048'],
        ]);

        $ingestion = PageIngestion::create([
            'site_id' => $site->id,
            'source_url' => $data['url'],
            'raw_html' => $data['html'],
            'styles' => $data['styles'] ?? null,
            'meta' => $data['meta'] ?? [],
            'discovered_links' => array_values(array_unique($data['links'] ?? [])),
            'status' => PageIngestion::STATUS_RECEIVED,
        ]);

        // Record ingest time + keep the connection in collect mode until publish.
        SiteConnection::updateOrCreate(
            ['site_id' => $site->id],
            ['last_ingested_at' => now()]
        );

        $this->prune($site->id, $data['url'], $ingestion->id);

        IngestPageJob::dispatch($ingestion->id);
        // Crawl cooldown: rapid re-ingests of the same site must not fan out
        // into concurrent crawls (each POST used to seed a fresh crawl).
        $cooldown = now()->subMinutes((int) config('site_connect.ingest.crawl_cooldown_minutes', 10));
        $recentCrawl = PageIngestion::where('site_id', $site->id)
            ->where('id', '!=', $ingestion->id)
            ->where('created_at', '>=', $cooldown)
            ->exists();
        if (! $recentCrawl) {
            CrawlSiteJob::dispatch($site->id, $ingestion->id);
        }

        return response()->json([
            'ok' => true,
            'ingestionId' => $ingestion->id,
        ], 202);
    }

    /**
     * Retention: keep only the latest N snapshots per (site, url) and M per
     * site overall, so a chatty or hostile connector can't grow the table
     * without bound. Sections cascade via the FK.
     */
    private function prune(string $siteId, string $url, string $keepId): void
    {
        $perUrl = (int) config('site_connect.ingest.keep_per_url', 3);
        $perSite = (int) config('site_connect.ingest.keep_per_site', 250);

        $staleForUrl = PageIngestion::where('site_id', $siteId)->where('source_url', $url)
            ->orderByDesc('created_at')->orderByDesc('id')->skip($perUrl)->take(100)->pluck('id');
        $staleForSite = PageIngestion::where('site_id', $siteId)
            ->orderByDesc('created_at')->orderByDesc('id')->skip($perSite)->take(100)->pluck('id');

        $stale = $staleForUrl->merge($staleForSite)->unique()->reject(fn ($id) => $id === $keepId);
        if ($stale->isNotEmpty()) {
            PageIngestion::whereIn('id', $stale)->delete();
        }
    }
}
