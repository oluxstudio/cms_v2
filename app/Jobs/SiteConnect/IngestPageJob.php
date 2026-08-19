<?php

namespace App\Jobs\SiteConnect;

use App\Models\PageIngestion;
use App\Models\Site;
use App\Services\SiteConnect\IngestionProcessor;
use App\Services\SiteConnect\ThemeExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Extract + classify a single ingested page into staging sections, and fold its
 * CSS into the site theme on the first page. Runs on the queue (Horizon-friendly).
 */
class IngestPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $ingestionId) {}

    public function handle(IngestionProcessor $processor, ThemeExtractor $themeExtractor): void
    {
        $ingestion = PageIngestion::find($this->ingestionId);
        if (! $ingestion) {
            return;
        }

        $processor->process($ingestion);
        $this->deriveTheme($ingestion, $themeExtractor);
    }

    /** Seed the site theme from ingested CSS, without clobbering explicit edits. */
    private function deriveTheme(PageIngestion $ingestion, ThemeExtractor $extractor): void
    {
        if (empty($ingestion->styles)) {
            return;
        }
        $site = Site::find($ingestion->site_id);
        if (! $site) {
            return;
        }
        $derived = $extractor->extract((string) $ingestion->styles);
        if ($derived === []) {
            return;
        }
        // Only fill keys the owner hasn't already set.
        $site->theme = array_merge($derived, is_array($site->theme) ? $site->theme : []);
        $site->save();
    }
}
