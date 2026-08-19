<?php

namespace App\Jobs\SiteConnect;

use App\Models\PageIngestion;
use App\Models\Site;
use App\Services\SiteConnect\SsrfGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * From the first ingested page's discovered links, fetch additional internal
 * pages SERVER-SIDE (SSRF-guarded, same-host only, capped per tier) and queue
 * each for extraction. v1 fetches HTML only — it does NOT execute JS, so
 * client-rendered pages the connector couldn't snapshot are skipped.
 */
class CrawlSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $siteId, public string $seedIngestionId) {}

    /** One crawl per site at a time — overlapping crawls multiply the fetch load. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('crawl:'.$this->siteId))->dontRelease()->expireAfter(600)];
    }

    public function handle(SsrfGuard $guard): void
    {
        $site = Site::find($this->siteId);
        $seed = PageIngestion::find($this->seedIngestionId);
        if (! $site || ! $seed) {
            return;
        }

        $allowedHosts = $this->allowedHosts($site);
        $cap = $this->pageCap($site);
        $seen = PageIngestion::where('site_id', $site->id)->pluck('source_url')->all();
        $fetched = 0;

        foreach (array_unique($seed->discovered_links ?? []) as $url) {
            if ($fetched >= $cap) {
                break;
            }
            if (in_array($url, $seen, true) || ! $guard->allows($url, $allowedHosts)) {
                continue;
            }

            // pinnedOptions locks the fetch to the IP the guard vetted
            // (defeats check-then-fetch DNS rebinding).
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'OluxSiteConnect/1.0'])
                ->withOptions($guard->pinnedOptions($url))
                ->get($url);

            if (! $response->ok() || strlen($response->body()) > config('site_connect.crawl.max_page_bytes')) {
                continue;
            }

            $ingestion = PageIngestion::create([
                'site_id' => $site->id,
                'source_url' => $url,
                'raw_html' => $response->body(),
                'status' => PageIngestion::STATUS_RECEIVED,
            ]);
            IngestPageJob::dispatch($ingestion->id);

            $seen[] = $url;
            $fetched++;
        }
    }

    /** @return array<int,string> */
    private function allowedHosts(Site $site): array
    {
        $hosts = $site->connection?->allowed_origins ?? [];
        if ($site->domain) {
            $hosts[] = parse_url('https://'.ltrim($site->domain, 'https://'), PHP_URL_HOST) ?: $site->domain;
        }

        return array_values(array_filter(array_unique($hosts)));
    }

    private function pageCap(Site $site): int
    {
        $tiers = config('site_connect.crawl.max_pages', []);
        $tier = $site->currentSubscription()?->tier()['key'] ?? 'free';

        return $tiers[$tier] ?? ($tiers['free'] ?? 10);
    }
}
