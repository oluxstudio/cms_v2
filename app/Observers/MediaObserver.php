<?php

namespace App\Observers;

use App\Models\Media;
use App\Services\ActivityLogger;

class MediaObserver
{
    public function created(Media $media): void
    {
        ActivityLogger::mediaUploaded($media);
    }
}
