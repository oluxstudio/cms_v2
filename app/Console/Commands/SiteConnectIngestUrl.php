<?php

namespace App\Console\Commands;

use App\Models\PageIngestion;
use App\Models\Site;
use App\Services\SiteConnect\IngestionProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Local testing helper: fetch a URL, run the full ingest → extract → classify
 * pipeline SYNCHRONOUSLY (no queue worker), print the classified sections, and
 * optionally commit them into real content. The production path is the queued
 * jobs triggered by connect.js collect mode; this is the same services, inline.
 *
 *   php artisan connect:ingest-url acme-salon https://example.com
 *   php artisan connect:ingest-url acme-salon https://example.com --commit
 */
class SiteConnectIngestUrl extends Command
{
    protected $signature = 'connect:ingest-url {site} {url} {--commit : Also materialise confident sections into real content}';

    protected $description = 'Ingest + classify a URL into a site (synchronous, for local testing)';

    public function handle(IngestionProcessor $processor): int
    {
        $site = Site::where('name', $this->argument('site'))->first();
        if (! $site) {
            $this->error("Site not found: {$this->argument('site')}");

            return self::FAILURE;
        }

        $url = $this->argument('url');
        $this->line("Fetching <info>{$url}</info> …");
        $response = Http::timeout(15)->withHeaders(['User-Agent' => 'OluxSiteConnect/1.0'])->get($url);
        if (! $response->ok()) {
            $this->error("Fetch failed: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $ingestion = PageIngestion::create([
            'site_id' => $site->id,
            'source_url' => $url,
            'raw_html' => $response->body(),
            'meta' => ['title' => $this->titleFrom($response->body())],
            'status' => PageIngestion::STATUS_RECEIVED,
        ]);

        $processor->process($ingestion);
        $ingestion->refresh();

        $this->newLine();
        $this->line("Classified <info>{$ingestion->sections->count()}</info> sections:");
        $this->table(
            ['#', 'tag', 'classification', 'confidence', 'review?'],
            $ingestion->sections->map(fn ($s) => [
                $s->position, $s->tag, $s->classification,
                number_format($s->confidence, 2),
                $s->needs_review ? 'REVIEW' : '',
            ])->all()
        );

        if ($this->option('commit')) {
            $page = $processor->commit($ingestion);
            $this->info("Committed to page {$page->url} — ".
                "{$page->components()->count()} component(s), ".
                "{$page->collections()->count()} collection(s).");
            $this->line('Publish it with: <comment>php artisan connect:publish '.$site->name.' --page='.$page->url.'</comment>');
        } else {
            $this->line('Re-run with <comment>--commit</comment> to materialise the confident sections into content.');
        }

        return self::SUCCESS;
    }

    private function titleFrom(string $html): ?string
    {
        return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) ? trim(html_entity_decode($m[1])) : null;
    }
}
