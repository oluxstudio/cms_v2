<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Ai\SiteKnowledge;
use App\Services\SiteAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Rebuild one site's AI knowledge base (chunks + embeddings) in the background. */
class SyncSiteKnowledge implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public string $siteId) {}

    public function handle(SiteKnowledge $knowledge): void
    {
        if ($site = Site::find($this->siteId)) {
            if ($knowledge->sync($site) > 0) {
                // Content changed outside the assistant — cached answers are stale.
                SiteAgent::bumpAnswerCache($site->id);
            }
        }
    }
}
