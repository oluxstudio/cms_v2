<?php

namespace App\Observers;

use App\Models\Page;
use App\Services\ActivityLogger;

class PageObserver
{
    public function created(Page $page): void
    {
        ActivityLogger::pageCreated($page);
    }

    public function updated(Page $page): void
    {
        // Only fire the "published" event when is_published flips to true
        if ($page->wasChanged('is_published') && $page->is_published) {
            ActivityLogger::pagePublished($page);
        }
    }
}
