<?php

namespace App\Observers;

use App\Models\Form;
use App\Services\ActivityLogger;

class FormObserver
{
    public function created(Form $form): void
    {
        ActivityLogger::formCreated($form);
    }
}
