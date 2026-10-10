<?php

use App\Models\Site;
use App\Models\User;
use App\Modules\Events\CalendarInvite;
use App\Modules\Events\EventsException;
use App\Modules\Events\EventTickets;
use App\Modules\Events\Mail\EventReminder;
use App\Modules\Events\Mail\TicketConfirmation;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\Ticket;
use App\Modules\Events\Models\TicketOrder;
use App\Payments\PaymentManager;
use App\Payments\SitePaymentFulfilment;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePaymentGateway;

uses(DatabaseTransactions::class);

afterEach(fn () => app(PaymentManager::class)->fake(null));

/** A site with the Events add-on on (and optionally a fake payment gateway). */
function eventsSite(bool $payments = false, array $config = []): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ev-'.uniqid(), 'domain' => 'ev-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('events', $config);
    $gateway = null;
    if ($payments) {
        $gateway = new FakePaymentGateway;
        app(PaymentManager::class)->fake($gateway);
        $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    }

    return [$owner, $site, $gateway];
}

function eventsMake(Site $site, array $attrs = [], array $types = [['name' => 'General', 'price_cents' => 0]]): Event
{
    $event = Event::create($attrs + [
        'site_id' => $site->id, 'title' => 'Launch night', 'slug' => 'launch-'.uniqid(),
        'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(10)->addHours(2),
        'timezone' => 'Europe/London', 'venue_name' => 'The Hall', 'status' => 'published',
    ]);
    foreach ($types as $i => $t) {
        $event->ticketTypes()->create($t + ['sort' => $i, 'max_per_order' => 10]);
    }

    return $event;
}

test('a free RSVP issues tickets immediately and emails them', function () {
    Mail::fake();
    [, $site] = eventsSite();
    $event = eventsMake($site);
    $type = $event->ticketTypes()->first();

    $res = $this->postJson("/api/sites/{$site->name}/events/{$event->slug}/order", [
        'name' => 'Ada', 'email' => 'ada@x.test', 'ticket_type_id' => $type->id, 'quantity' => 2, 'attendees' => ['Ada', 'Bob'],
    ])->assertStatus(201)->assertJsonPath('status', 'paid')->json();

    expect($res['tickets'])->toHaveCount(2)
        ->and($res['tickets'][1]['attendee'])->toBe('Bob')
        ->and($res['tickets'][0]['url'])->toContain('signature=');
    $order = TicketOrder::find($res['order_id']);
    expect($order->status)->toBe('paid')->and($order->tickets()->count())->toBe(2);
    Mail::assertQueued(TicketConfirmation::class, fn ($m) => $m->hasTo('ada@x.test') && $m->order->is($order));

    // The hosted ticket page renders with the signed link only.
    $this->get($res['tickets'][0]['url'])->assertOk()->assertSee($res['tickets'][0]['code'])->assertSee('<svg', false);
    $this->get("/preview/{$site->name}/tickets/{$res['tickets'][0]['code']}")->assertForbidden();

    // The confirmation renders (with its .ics attachment).
    $mail = new TicketConfirmation($order, $site);
    expect($mail->render())->toContain($res['tickets'][0]['code'])->toContain('Launch night')
        ->and($mail->attachments())->toHaveCount(1);
});

test('the list and detail API show upcoming events, availability and ticket types', function () {
    [, $site] = eventsSite();
    $soon = eventsMake($site, ['title' => 'Soon', 'capacity' => 50]);
    eventsMake($site, ['title' => 'Old', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour()]);
    eventsMake($site, ['title' => 'Hidden', 'status' => 'draft']);

    $this->getJson("/api/sites/{$site->name}/events")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Soon')
        ->assertJsonPath('data.0.remaining', 50)->assertJsonPath('data.0.is_free', true);
    $this->getJson("/api/sites/{$site->name}/events?past=1")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Old');
    $this->getJson("/api/sites/{$site->name}/events/{$soon->slug}")->assertOk()
        ->assertJsonPath('data.ticket_types.0.name', 'General')
        ->assertJsonPath('data.ticket_types.0.available', true)
        ->assertJsonPath('data.payments_available', false);

    // Feature off → 404.
    $site->disableFeature('events');
    $this->getJson("/api/sites/{$site->name}/events")->assertNotFound();
});

test('a paid order opens a checkout with ticket metadata and fulfilment issues the tickets', function () {
    Mail::fake();
    [, $site, $gateway] = eventsSite(payments: true, config: ['currency' => 'usd']);
    $event = eventsMake($site, [], [['name' => 'VIP', 'price_cents' => 2500]]);
    $type = $event->ticketTypes()->first();

    $res = $this->postJson("/api/sites/{$site->name}/events/{$event->slug}/order", [
        'name' => 'Cy', 'email' => 'cy@x.test', 'tickets' => [['ticket_type_id' => $type->id, 'quantity' => 3]],
    ])->assertStatus(201)->assertJsonPath('status', 'pending')->json();

    expect($res['checkout_url'])->toBe('https://fake-pay.test/checkout');
    $order = TicketOrder::find($res['order_id']);
    $checkout = $gateway->checkouts[0];
    expect($order->status)->toBe('pending')->and($order->total_cents)->toBe(7500)->and($order->currency)->toBe('usd')
        ->and($checkout->metadata['ticket_order_id'])->toBe($order->id)
        ->and($checkout->lines[0]->unitAmountCents)->toBe(2500)
        ->and($checkout->lines[0]->quantity)->toBe(3)
        ->and($checkout->successUrl)->toContain('{CHECKOUT_SESSION_ID}');
    Mail::assertNothingQueued();

    // The platform Connect webhook → SitePaymentFulfilment → config('payments.fulfilment') → TicketFulfilment.
    app(SitePaymentFulfilment::class)->apply($site, new WebhookEvent(
        WebhookEventKind::Completed, 'sess_1', ['ticket_order_id' => $order->id], 'cy@x.test', 'Cy', 'pi_123', true));

    $order->refresh();
    expect($order->status)->toBe('paid')->and($order->payment_ref)->toBe('pi_123')->and($order->paid_at)->not->toBeNull();
    Mail::assertQueued(TicketConfirmation::class, 1);
    expect($site->alerts()->where('type', 'event')->exists())->toBeTrue();

    // A replayed webhook is idempotent.
    app(SitePaymentFulfilment::class)->apply($site, new WebhookEvent(
        WebhookEventKind::Completed, 'sess_1', ['ticket_order_id' => $order->id], 'cy@x.test', 'Cy', 'pi_123', true));
    Mail::assertQueued(TicketConfirmation::class, 1);
});

test('paid tickets without payments connected return a clear 422 while free RSVP still works', function () {
    Mail::fake();
    [, $site] = eventsSite();
    $event = eventsMake($site, [], [['name' => 'Paid', 'price_cents' => 1000], ['name' => 'Free', 'price_cents' => 0]]);
    [$paid, $free] = $event->ticketTypes()->get()->all();

    $this->postJson("/api/sites/{$site->name}/events/{$event->slug}/order", ['name' => 'A', 'email' => 'a@x.test', 'ticket_type_id' => $paid->id])
        ->assertStatus(422)->assertJsonPath('reason', 'payments_unavailable');
    $this->postJson("/api/sites/{$site->name}/events/{$event->slug}/order", ['name' => 'A', 'email' => 'a@x.test', 'ticket_type_id' => $free->id])
        ->assertStatus(201)->assertJsonPath('status', 'paid');
    expect(TicketOrder::where('event_id', $event->id)->count())->toBe(1);
});

test('capacity and per-type quantity can never be oversold', function () {
    Mail::fake();
    [, $site] = eventsSite();
    $event = eventsMake($site, ['capacity' => 5], [['name' => 'A', 'price_cents' => 0, 'quantity' => 3], ['name' => 'B', 'price_cents' => 0]]);
    [$a, $b] = $event->ticketTypes()->get()->all();
    $post = fn ($type, $qty) => $this->postJson("/api/sites/{$site->name}/events/{$event->slug}/order",
        ['name' => 'N', 'email' => 'n@x.test', 'ticket_type_id' => $type->id, 'quantity' => $qty]);

    $post($a, 2)->assertStatus(201);
    $post($a, 2)->assertStatus(422)->assertJsonPath('reason', 'sold_out');     // type: only 1 A left
    $post($a, 1)->assertStatus(201);
    $post($b, 3)->assertStatus(422)->assertJsonPath('reason', 'sold_out');     // event: only 2 left
    $post($b, 2)->assertStatus(201);
    $post($b, 1)->assertStatus(422)->assertJsonPath('message', 'Sorry — this event is sold out.');

    expect(Ticket::where('event_id', $event->id)->count())->toBe(5);
    $this->getJson("/api/sites/{$site->name}/events/{$event->slug}")->assertJsonPath('data.sold_out', true);

    // The engine refuses directly too (the API is not the only guard).
    expect(fn () => app(EventTickets::class)->placeOrder($site, $event, [$b->id => 1], ['name' => 'X', 'email' => 'x@x.test']))
        ->toThrow(EventsException::class);
});

test('an expired checkout releases its held places', function () {
    Mail::fake();
    [, $site, $gateway] = eventsSite(payments: true);
    $event = eventsMake($site, ['capacity' => 2], [['name' => 'Seat', 'price_cents' => 1500]]);
    $type = $event->ticketTypes()->first();
    $order = fn () => $this->postJson("/api/sites/{$site->name}/events/{$event->slug}/order",
        ['name' => 'H', 'email' => 'h@x.test', 'ticket_type_id' => $type->id, 'quantity' => 2]);

    $first = $order()->assertStatus(201)->json();
    $order()->assertStatus(422);                                                 // held by the pending checkout

    app(SitePaymentFulfilment::class)->apply($site, new WebhookEvent(
        WebhookEventKind::Expired, 'sess_x', ['ticket_order_id' => $first['order_id']]));
    expect(TicketOrder::find($first['order_id'])->status)->toBe('cancelled');
    $order()->assertStatus(201);                                                 // places are free again

    // Stale holds the webhook never reported are swept by the hourly command.
    TicketOrder::where('event_id', $event->id)->where('status', 'pending')->update(['created_at' => now()->subHours(30)]);
    $this->artisan('events:remind')->assertSuccessful();
    expect(TicketOrder::where('event_id', $event->id)->where('status', 'pending')->count())->toBe(0);
});

test('the reminder command emails paid orders once, reminder_hours before the start', function () {
    Mail::fake();
    [, $site] = eventsSite(config: ['reminder_hours' => 24]);
    $soon = eventsMake($site, ['starts_at' => now()->addHours(20)]);
    $later = eventsMake($site, ['starts_at' => now()->addDays(4)]);
    $engine = app(EventTickets::class);
    $engine->placeOrder($site, $soon, [$soon->ticketTypes()->first()->id => 1], ['name' => 'R', 'email' => 'r@x.test']);
    $engine->placeOrder($site, $later, [$later->ticketTypes()->first()->id => 1], ['name' => 'L', 'email' => 'l@x.test']);

    $this->artisan('events:remind')->assertSuccessful();
    Mail::assertQueued(EventReminder::class, 1);
    Mail::assertQueued(EventReminder::class, fn ($m) => $m->hasTo('r@x.test'));
    expect($soon->fresh()->reminder_sent_at)->not->toBeNull()->and($later->fresh()->reminder_sent_at)->toBeNull();

    $this->artisan('events:remind')->assertSuccessful();
    Mail::assertQueued(EventReminder::class, 1);                                 // not twice

    // 0 = reminders off.
    $site->saveFeatureConfig('events', ['reminder_hours' => 0]);
    $off = eventsMake($site, ['starts_at' => now()->addHours(2)]);
    $engine->placeOrder($site, $off, [$off->ticketTypes()->first()->id => 1], ['name' => 'O', 'email' => 'o@x.test']);
    $this->artisan('events:remind')->assertSuccessful();
    Mail::assertQueued(EventReminder::class, 1);
});

test('the calendar invite is a valid VEVENT', function () {
    [, $site] = eventsSite();
    $event = eventsMake($site, ['title' => 'Talk; with, commas']);
    $ics = CalendarInvite::make($event, $site);

    expect($ics)->toContain('BEGIN:VCALENDAR')->toContain('BEGIN:VEVENT')
        ->toContain('SUMMARY:Talk\; with\, commas')
        ->toContain('DTSTART:'.$event->starts_at->copy()->utc()->format('Ymd\THis\Z'));
});
