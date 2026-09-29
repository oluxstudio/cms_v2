<?php

namespace App\Observers;

use App\Models\Todo;
use App\Services\ActivityLogger;

class TodoObserver
{
    public function created(Todo $todo): void
    {
        if ($todo->system_key !== null) {
            return; // platform-created (e.g. the setup task) — not someone's action
        }
        ActivityLogger::todoCreated($todo);
    }

    public function updated(Todo $todo): void
    {
        // Only log completion once — when status changes TO 'done'
        if ($todo->wasChanged('status') && $todo->status === 'done') {
            ActivityLogger::todoCompleted($todo);
        }
    }
}
