<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic DB backups every 6 hours (storage/app/backups, 40 files ≈ 10 days).
// Runs via the `scheduler` docker-compose service (php artisan schedule:work).
Schedule::command('db:backup')->everySixHours()->withoutOverlapping();

// Invoice automation: overdue refresh, recurring generation, payment reminders.
Schedule::command('invoices:sweep')->hourly()->withoutOverlapping();
