<?php

namespace App\Observers;

use App\Models\FormResponse;
use App\Services\ActivityLogger;

class FormResponseObserver
{
    public function created(FormResponse $response): void
    {
        // Eager-load the parent form (needed for site_id + displayTitle)
        $response->loadMissing('form');

        if ($response->form) {
            ActivityLogger::formResponse($response);
        }
    }
}
