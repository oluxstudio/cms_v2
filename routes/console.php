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

// Booking automations: ~24h reminders + next-day review requests hourly,
// "time for your next visit" prompts once a day (send-once stamps on bookings).
Schedule::command('bookings:automate reminders')->hourly()->withoutOverlapping();
Schedule::command('bookings:automate reviews')->hourly()->withoutOverlapping();
Schedule::command('bookings:automate rebook')->dailyAt('10:00')->withoutOverlapping();

Schedule::command('site:digest')->mondays()->at('08:00')->withoutOverlapping();
// Abandoned-signup recovery: one nudge after a day of inactivity.
Schedule::command('signup:nudge')->dailyAt('09:30')->withoutOverlapping();
