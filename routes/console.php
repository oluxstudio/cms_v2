<?php

use App\Support\ScheduleTracker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every scheduled task lives in ScheduleTracker::TASKS (backups, invoice and
// booking automations, digests, signup nudges, domain renewal warnings) so the
// admin Operations page can show each one's runs. Runs via the `scheduler`
// docker-compose service (php artisan schedule:work).
ScheduleTracker::register(Schedule::getFacadeRoot());
require __DIR__.'/modules/reviews-console.php'; // reviews:remind + its daily schedule
Artisan::command('events:remind', function () { $r = app(\App\Modules\Events\EventReminders::class)->run(); $this->info("Event reminders: {$r['emails']} email(s) for {$r['events']} event(s); {$r['released']} expired hold(s) released."); })->purpose('Events add-on: attendee reminders before events + release expired ticket holds'); // scheduled via ScheduleTracker::TASKS
Schedule::job(new App\Modules\Newsletter\Jobs\DispatchScheduledCampaigns)->everyMinute()->name('newsletter-scheduled-campaigns')->withoutOverlapping(); // newsletter: send due scheduled campaigns
