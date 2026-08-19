<?php

use App\Mail\BookingConfirmed;
use App\Mail\NewBookingNotification;
use App\Mail\SubmissionReceipt;
use App\Models\Booking;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Service;
use App\Models\Site;
use App\Models\User;
use App\Services\Booking\BookingNotifications;
use Illuminate\Support\Facades\Mail;

function bookingFixture(): array
{
    $owner = User::factory()->create(['email' => 'owner-'.uniqid().'@site.test']);
    $site = Site::factory()->create(['user_id' => $owner->id]);
    $service = Service::create(['site_id' => $site->id, 'name' => 'Haircut', 'slug' => '', 'kind' => 'slot',
        'duration_min' => 45, 'price_cents' => 3800, 'is_active' => true]);
    $booking = Booking::create(['site_id' => $site->id, 'service_id' => $service->id,
        'customer_name' => 'Ada', 'customer_email' => 'ada@example.test', 'customer_phone' => '0700',
        'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45),
        'status' => 'pending', 'notes' => 'First visit']);

    return [$owner, $site, $booking];
}

test('a booking is recorded as a form response and emails customer + owner by default', function () {
    Mail::fake();
    [$owner, $site, $booking] = bookingFixture();

    app(BookingNotifications::class)->send($booking, $site);

    // The "booking" form was auto-created and holds the response.
    $form = Form::where('site_id', $site->id)->where('name', 'booking')->first();
    expect($form)->not->toBeNull();
    $response = FormResponse::where('form_id', $form->id)->first();
    expect($response)->not->toBeNull()
        ->and($response->fields['email'])->toBe('ada@example.test')
        ->and($response->fields['service'])->toBe('Haircut')
        ->and($response->fields['reference'])->toBe($booking->reference);

    Mail::assertSent(SubmissionReceipt::class, fn ($m) => $m->hasTo('ada@example.test'));
    Mail::assertSent(NewBookingNotification::class, fn ($m) => $m->hasTo($owner->email));
});

test('the admin recipient is configurable on the booking form settings', function () {
    Mail::fake();
    [, $site, $booking] = bookingFixture();
    Form::create(['site_id' => $site->id, 'name' => 'booking', 'title' => 'Bookings', 'is_active' => true,
        'fields' => [], 'delivery' => ['channels' => ['email' => ['enabled' => true, 'notify_visitor' => true, 'notify_admin' => true, 'admin_address' => 'desk@salon.test']]]]);

    app(BookingNotifications::class)->send($booking, $site);

    Mail::assertSent(NewBookingNotification::class, fn ($m) => $m->hasTo('desk@salon.test'));
});

test('notify toggles are honoured and confirmed bookings send the confirmation mailable', function () {
    Mail::fake();
    [, $site, $booking] = bookingFixture();
    Form::create(['site_id' => $site->id, 'name' => 'booking', 'title' => 'Bookings', 'is_active' => true,
        'fields' => [], 'delivery' => ['channels' => ['email' => ['enabled' => true, 'notify_visitor' => true, 'notify_admin' => false, 'admin_address' => null]]]]);

    app(BookingNotifications::class)->send($booking, $site, confirmed: true);

    Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('ada@example.test'));
    Mail::assertNotSent(NewBookingNotification::class);
    // The CRM record is written regardless of email toggles.
    expect(FormResponse::whereHas('form', fn ($q) => $q->where('site_id', $site->id))->count())->toBe(1);
});

test('the response lands on the form the client booking UI came from', function () {
    Mail::fake();
    [, $site, $booking] = bookingFixture();
    $appointment = Form::create(['site_id' => $site->id, 'name' => 'appointment', 'title' => 'Book an appointment',
        'is_active' => true, 'fields' => [],
        'delivery' => ['channels' => ['email' => ['enabled' => true, 'notify_visitor' => true, 'notify_admin' => true, 'admin_address' => 'front-desk@salon.test']]]]);
    $booking->update(['params' => ['form' => 'appointment', 'fields' => ['inspiration' => 'Layered bob']]]);

    app(BookingNotifications::class)->send($booking->fresh(), $site);

    // Response on the APPOINTMENT form (not the fallback "booking" form)…
    $response = FormResponse::where('form_id', $appointment->id)->first();
    expect($response)->not->toBeNull()
        ->and($response->fields['inspiration'])->toBe('Layered bob') // custom field included
        ->and(Form::where('site_id', $site->id)->where('name', 'booking')->exists())->toBeFalse();
    // …and the admin email uses THAT form's configured address.
    Mail::assertSent(NewBookingNotification::class, fn ($m) => $m->hasTo('front-desk@salon.test'));
});

test('an unknown or inactive requested form falls back to the booking form', function () {
    Mail::fake();
    [, $site, $booking] = bookingFixture();
    $booking->update(['params' => ['form' => 'nope']]);

    app(BookingNotifications::class)->send($booking->fresh(), $site);

    $fallback = Form::where('site_id', $site->id)->where('name', 'booking')->first();
    expect($fallback)->not->toBeNull()
        ->and(FormResponse::where('form_id', $fallback->id)->count())->toBe(1);
});
