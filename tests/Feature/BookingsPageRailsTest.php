<?php

use App\Livewire\BookingsPage;
use App\Models\Booking;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function bookingsRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id]);
    $site->enableFeature('bookings');

    return [$owner, $site];
}

function bookingsRailsBooking(Site $site, array $attrs): Booking
{
    $starts = $attrs['starts_at'] ?? now()->addDay();

    return Booking::create($attrs + [
        'site_id' => $site->id,
        'customer_email' => strtolower(str_replace(' ', '', $attrs['customer_name'] ?? 'x')).'@x.test',
        'status' => 'confirmed',
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addHour(),
    ]);
}

/** A small, varied book: one of every list bucket. */
function bookingsRailsSeed(Site $site): array
{
    $svc = $site->services()->create(['name' => 'Haircut', 'slug' => 'haircut', 'kind' => 'slot', 'duration_min' => 60, 'price_cents' => 4000, 'is_active' => true]);

    return [
        'svc' => $svc,
        'pending' => bookingsRailsBooking($site, ['customer_name' => 'Penny Pending', 'status' => 'pending', 'service_id' => $svc->id, 'starts_at' => now()->addDays(2)]),
        'confirmed' => bookingsRailsBooking($site, ['customer_name' => 'Connie Confirmed', 'service_id' => $svc->id, 'starts_at' => now()->addDays(3), 'total_cents' => 4000, 'paid_cents' => 4000]),
        'past' => bookingsRailsBooking($site, ['customer_name' => 'Owen Owes', 'service_id' => $svc->id, 'starts_at' => now()->subDays(2), 'total_cents' => 4000, 'paid_cents' => 1000]),
        'cancelled' => bookingsRailsBooking($site, ['customer_name' => 'Cass Cancelled', 'status' => 'cancelled', 'starts_at' => now()->addDays(4)]),
        'noshow' => bookingsRailsBooking($site, ['customer_name' => 'Nora Noshow', 'status' => 'no_show', 'starts_at' => now()->startOfMonth()->addHours(1)->min(now()->subMinute())]),
    ];
}

test('the page opens on the bookings tab as grid cards with tiles, summary and related links', function () {
    [$owner, $site] = bookingsRailsSite();
    bookingsRailsSeed($site);

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->assertOk()
        ->assertSet('tab', 'bookings')
        ->assertSet('viewMode', 'grid')
        ->assertSee("Today's bookings")
        ->assertSee('Awaiting confirmation')
        ->assertSee('No-shows this month')
        ->assertSee('Busiest service')
        ->assertSee('Bookings summary')
        ->assertSee('Needs attention')
        ->assertSee('New booking')
        ->assertSee('Penny Pending')
        ->assertSee('Deposit paid')
        ->assertSee(route('site.payments', $site->name))
        ->assertSee(route('site.contacts', $site->name));
});

test('one aggregate query feeds every count', function () {
    [, $site] = bookingsRailsSite();
    bookingsRailsSeed($site);
    [$owner] = [$site->user];

    $s = Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])->instance()->stats;

    expect($s['total'])->toBe(5)
        ->and($s['pending'])->toBe(1)
        ->and($s['confirmed'])->toBe(2)
        ->and($s['cancelled'])->toBe(1)
        ->and($s['no_show'])->toBe(1)
        ->and($s['upcoming'])->toBe(2)
        ->and($s['past'])->toBe(1)
        ->and($s['unpaid'])->toBe(1)
        ->and($s['unpaid_cents'])->toBe(3000)
        ->and($s['noshow_month'])->toBe(1)
        ->and($s['busiest']['name'])->toBe('Haircut');
});

test('filter pills, search and sort narrow the list and live in the URL', function () {
    [$owner, $site] = bookingsRailsSite();
    $b = bookingsRailsSeed($site);

    // Cards are keyed bk-card-{id}; the right rail may still name a booking.
    $card = fn (string $k) => 'bk-card-'.$b[$k]->id;

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('setFilter', 'pending')
        ->assertSet('filter', 'pending')
        ->assertSee($card('pending'), false)
        ->assertDontSee($card('confirmed'), false)
        ->call('setFilter', 'unpaid')
        ->assertSee($card('past'), false)
        ->assertDontSee($card('pending'), false)
        ->call('setFilter', 'bogus')
        ->assertSet('filter', 'all')
        ->set('search', $b['confirmed']->reference)
        ->assertSee($card('confirmed'), false)
        ->assertDontSee($card('pending'), false)
        ->set('search', 'haircut')
        ->assertSee($card('pending'), false)
        ->assertDontSee($card('cancelled'), false)
        ->set('search', 'zzz-nothing')
        ->assertSee('Nothing matches')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->set('sort', 'customer')
        ->assertSeeHtmlInOrder([$card('cancelled'), $card('confirmed'), $card('noshow'), $card('past'), $card('pending')]);

    // #[Url] round-trip: query string seeds the state.
    Livewire::withQueryParams(['tab' => 'calendar', 'filter' => 'past', 'sort' => 'value', 'q' => 'Owen'])
        ->actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->assertSet('tab', 'calendar')
        ->assertSet('filter', 'past')
        ->assertSet('sort', 'value')
        ->assertSet('search', 'Owen');
});

test('tiles filter the list; cards open the existing drawer', function () {
    [$owner, $site] = bookingsRailsSite();
    $b = bookingsRailsSeed($site);

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('setTab', 'services')
        ->call('openTile', 'pending')
        ->assertSet('tab', 'bookings')
        ->assertSet('filter', 'pending')
        ->call('openTile', 'noshow')
        ->assertSet('filter', 'no_show')
        ->assertSee('Nora Noshow')
        ->call('openTile', 'busiest')
        ->assertSet('search', 'Haircut')
        ->call('viewBooking', $b['pending']->id)
        ->assertSet('viewingId', $b['pending']->id)
        ->assertSee('Booking details')
        ->assertSee('Confirm booking');
});

test('list and compact layouts render a table and the choice is remembered per site', function () {
    [$owner, $site] = bookingsRailsSite();
    bookingsRailsSeed($site);

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('setViewMode', 'list')
        ->assertSee('<table', false)
        ->assertSee('Payment');

    expect($site->fresh()->getAttr('layout:bookings'))->toBe('list');

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site->fresh()])
        ->assertSet('viewMode', 'list')
        ->call('setViewMode', 'compact')
        ->assertSee('<table', false);
});

test('calendar and services tabs keep the agenda, services, resources and availability', function () {
    [$owner, $site] = bookingsRailsSite();
    bookingsRailsSeed($site);
    $site->resources()->create(['name' => 'Bella', 'capacity' => 1, 'is_active' => true]);

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('openDay', now()->addDays(2)->format('Y-m-d'))
        ->assertSet('tab', 'calendar')
        ->assertSee('Penny Pending')
        ->call('setTab', 'services')
        ->assertSee('Resources')
        ->assertSee('Availability')
        ->assertSee('Day &amp; slot exceptions', false)
        ->call('setTab', 'availability')
        ->assertSet('tab', 'services')
        ->call('startCreate')
        ->assertSet('wizOpen', true)
        ->assertSee('What are you offering?');
});

test('an empty site gets a helpful first-run state', function () {
    [$owner, $site] = bookingsRailsSite();

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->assertSee('No bookings yet')
        ->assertSee('Create your first service')
        ->assertSee('All caught up');
});
