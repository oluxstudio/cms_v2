<?php

use App\Mail\BookingReminder;
use App\Mail\RebookPrompt;
use App\Mail\ReviewRequest;
use App\Models\Booking;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function automationSite(): Site
{
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $site->enableFeature('bookings');

    return $site;
}

function makeBooking(Site $site, array $attrs = []): Booking
{
    return Booking::create(array_merge([
        'site_id' => $site->id,
        'reference' => 'BK-'.strtoupper(substr(md5(uniqid()), 0, 8)),
        'status' => 'confirmed',
        'customer_name' => 'Cust',
        'customer_email' => 'cust@example.test',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'params' => [],
    ], $attrs));
}

it('sends the ~24h reminder exactly once', function () {
    Mail::fake();
    $site = automationSite();
    $due = makeBooking($site);                                        // starts in 24h
    makeBooking($site, ['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHour()]); // too far out

    $this->artisan('bookings:automate reminders')->assertSuccessful();
    Mail::assertQueued(BookingReminder::class, 1);
    expect($due->refresh()->reminder_sent_at)->not->toBeNull();

    // Second run: the stamp prevents a duplicate.
    $this->artisan('bookings:automate reminders')->assertSuccessful();
    Mail::assertQueued(BookingReminder::class, 1);
});

it('sends a review request after the appointment when a review link is set', function () {
    Mail::fake();
    $site = automationSite();
    $ended = makeBooking($site, ['starts_at' => now()->subHours(5), 'ends_at' => now()->subHours(4)]);

    // No review URL yet → nothing goes out.
    $this->artisan('bookings:automate reviews')->assertSuccessful();
    Mail::assertNothingQueued();

    $site->setAttr('google_review_url', 'https://g.page/r/test-review');
    $this->artisan('bookings:automate reviews')->assertSuccessful();
    Mail::assertQueued(ReviewRequest::class, fn ($m) => $m->reviewUrl === 'https://g.page/r/test-review');
    expect($ended->refresh()->review_requested_at)->not->toBeNull();
});

it('sends a rebooking prompt N weeks after the last visit, unless they rebooked', function () {
    Mail::fake();
    $site = automationSite();
    $lastVisit = makeBooking($site, ['starts_at' => now()->subWeeks(5)->subHour(), 'ends_at' => now()->subWeeks(5)]);

    // Another customer who already has a FUTURE booking — must be skipped.
    makeBooking($site, ['customer_email' => 'loyal@example.test', 'starts_at' => now()->subWeeks(5)->subHour(), 'ends_at' => now()->subWeeks(5)]);
    makeBooking($site, ['customer_email' => 'loyal@example.test']); // future booking

    $this->artisan('bookings:automate rebook')->assertSuccessful();
    Mail::assertQueued(RebookPrompt::class, 1);
    Mail::assertQueued(RebookPrompt::class, fn ($m) => $m->booking->id === $lastVisit->id);
    expect($lastVisit->refresh()->rebook_prompted_at)->not->toBeNull();
});

it('respects the per-site toggles', function () {
    Mail::fake();
    $site = automationSite();
    $site->saveFeatureConfig('bookings', ['remind_visitor' => false, 'rebook_weeks' => 0]);
    makeBooking($site);
    makeBooking($site, ['starts_at' => now()->subWeeks(5)->subHour(), 'ends_at' => now()->subWeeks(5)]);

    $this->artisan('bookings:automate reminders')->assertSuccessful();
    $this->artisan('bookings:automate rebook')->assertSuccessful();
    Mail::assertNothingQueued();
});
