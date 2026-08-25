<?php

use App\Mail\BookingConfirmed;
use App\Mail\FormSubmissionNotification;
use App\Mail\NewBookingNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Booking;
use App\Models\Form;
use App\Models\Site;
use App\Models\User;
use App\Services\Booking\BookingNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('routes the receipt to the visitor and the alert to the owner on form submit', function () {
    Mail::fake();
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $site = Site::factory()->create(['user_id' => $owner->id]);
    $form = Form::create([
        'site_id' => $site->id, 'name' => 'contact', 'title' => 'Contact', 'active' => true,
        'fields' => [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false],
        ],
    ]);

    $this->postJson("/api/sites/{$site->name}/form/{$form->name}", [
        'name' => 'Visitor V', 'email' => 'visitor@example.test', 'message' => 'Hi there',
    ])->assertSuccessful();

    Mail::assertSent(SubmissionReceipt::class, fn ($m) => $m->hasTo('visitor@example.test') && ! $m->hasTo('owner@example.test'));
    Mail::assertSent(FormSubmissionNotification::class, fn ($m) => $m->hasTo('owner@example.test') && ! $m->hasTo('visitor@example.test'));
});

it('routes booking confirmation to the customer and the alert to the owner', function () {
    Mail::fake();
    $owner = User::factory()->create(['email' => 'owner2@example.test']);
    $site = Site::factory()->create(['user_id' => $owner->id]);
    $booking = Booking::create([
        'site_id' => $site->id, 'reference' => 'BK-TEST-1', 'status' => 'confirmed',
        'customer_name' => 'Cust', 'customer_email' => 'customer@example.test',
        'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'params' => [],
    ]);

    app(BookingNotifications::class)->send($booking, $site, confirmed: true);

    Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('customer@example.test') && ! $m->hasTo('owner2@example.test'));
    Mail::assertSent(NewBookingNotification::class, fn ($m) => $m->hasTo('owner2@example.test') && ! $m->hasTo('customer@example.test'));
});
