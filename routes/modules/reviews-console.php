<?php

// reviews module — CONSOLE: the reminder command + its schedule. Required once from routes/console.php.
// Owned by the reviews module; keep console wiring for this module here.

use App\Modules\Reviews\ReviewService;
use Illuminate\Support\Facades\Artisan;

// One reminder, 5+ days after a review request, for links not yet used.
Artisan::command('reviews:remind', function () {
    $sent = ReviewService::sendReminders();
    $this->info("reviews:remind — {$sent} reminder(s) sent.");
})->purpose('Send the one review-request reminder 5 days after the request');

// Scheduled (and shown on the Operations page) via ScheduleTracker::TASKS.
