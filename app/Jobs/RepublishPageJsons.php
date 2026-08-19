<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\SiteConnect\PageJsonPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Regenerates every previously-published page.json of a site after an API
 * content write (auto-republish). Dispatched debounced: the auto.republish
 * middleware delays it a few seconds and ShouldBeUnique collapses bursts of
 * edits into a single run per site.
 *
 * Only pages that have been published at least once (page_json_path set) are
 * refreshed — a page never exposed via Site Connect stays unpublished.
 */
class RepublishPageJsons implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Seconds the unique lock is held (covers the debounce delay + run time). */
    public int $uniqueFor = 300;

    public function __construct(public int $siteId) {}

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    public function handle(PageJsonPublisher $publisher): void
    {
        $pages = Page::where('site_id', $this->siteId)
            ->whereNotNull('page_json_path')
            ->get();

        foreach ($pages as $page) {
            try {
                $publisher->publish($page);
            } catch (\Throwable $e) {
                Log::warning('Auto-republish failed for a page — continuing with the rest.', [
                    'site_id' => $this->siteId,
                    'page_id' => $page->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
