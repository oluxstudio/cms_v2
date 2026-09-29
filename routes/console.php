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
