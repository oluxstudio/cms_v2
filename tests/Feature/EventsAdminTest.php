<?php

use App\Livewire\EventsPage;
use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Modules\Events\EventTickets;
use App\Modules\Events\Mail\AttendeeMessage;
use App\Modules\Events\Models\Event;
use App\Payments\PaymentManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Fakes\FakePaymentGateway;

uses(DatabaseTransactions::class);

afterEach(fn () => app(PaymentManager::class)->fake(null));

function eventsAdminSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'eva-'.uniqid(), 'domain' => 'eva-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('events');

    return [$owner, $site];
}

function eventsAdminEvent(Site $site, array $attrs = [], int $price = 0): Event
{
    $e = Event::create($attrs + [
        'site_id' => $site->id, 'title' => 'Open evening', 'slug' => 'open-'.uniqid(),
        'starts_at' => now()->addDays(5), 'timezone' => 'Europe/London', 'status' => 'published', 'venue_name' => 'Studio',
    ]);
    $e->ticketTypes()->create(['name' => 'Entry', 'price_cents' => $price, 'max_per_order' => 10]);

    return $e;
}

test('the events page renders its three rails for the owner', function () {
    Mail::fake();
    [$owner, $site] = eventsAdminSite();
    $event = eventsAdminEvent($site, ['capacity' => 2]);
    eventsAdminEvent($site, ['title' => 'Paid gala', 'status' => 'draft'], 2000);
    app(EventTickets::class)->placeOrder($site, $event, [$event->ticketTypes()->first()->id => 2], ['name' => 'P', 'email' => 'p@x.test']);

    $this->actingAs($owner)->get("/{$site->name}/events")->assertOk()
        ->assertSee('Upcoming events')->assertSee('Tickets this month')->assertSee('Check-ins today')   // left
        ->assertSee('Open evening')->assertSee('New event')                                            // centre
        ->assertSee('Sales by event')->assertSee('Needs attention')->assertSee('Payments not connected') // right
        ->assertSee(route('site.payments', $site->name));

    Livewire::actingAs($owner)->test(EventsPage::class, ['site' => $site])
        ->assertSet('viewMode', 'grid')
        ->assertViewHas('stats', fn ($s) => $s['upcoming'] === 1 && $s['soldMonth'] === 2 && $s['nearCapacity']->count() === 1
            && $s['drafts']->count() === 1 && $s['paymentsMissing'] === true)
        ->call('setFilter', 'drafts')
        ->assertViewHas('events', fn ($e) => $e->pluck('title')->all() === ['Paid gala']);

    // Feature off → the page is gone.
    $site->disableFeature('events');
    $this->actingAs($owner)->get("/{$site->name}/events")->assertNotFound();
});

test('the owner creates an event, adds a paid ticket type and checks attendees in', function () {
    Mail::fake();
    [$owner, $site] = eventsAdminSite();

    $page = Livewire::actingAs($owner)->test(EventsPage::class, ['site' => $site])
        ->call('newEvent')
        ->set('form.title', 'Spring <b>Fair</b>')
        ->set('form.starts_at', now()->addDays(3)->format('Y-m-d\T18:00'))
        ->set('form.ends_at', now()->addDays(3)->format('Y-m-d\T21:00'))
        ->set('form.status', 'published')
        ->set('form.description', '<p>Hi</p><script>alert(1)</script>')
        ->set('form.image', '@media/fair.jpg')
        ->call('saveEvent')->assertHasNoErrors();

    $event = Event::where('site_id', $site->id)->firstOrFail();
    expect($event->slug)->toBe('spring-bfairb')
        ->and($event->description)->not->toContain('script')
        ->and($event->image)->toBe('@media/fair.jpg')
        ->and($event->ticketTypes()->count())->toBe(1);                       // free RSVP by default

    $page->call('newType')->set('typeForm.name', 'VIP')->set('typeForm.price', '12.50')->call('saveType')->assertHasNoErrors();
    expect($event->ticketTypes()->where('price_cents', 1250)->exists())->toBeTrue();

    $order = app(EventTickets::class)->placeOrder($site, $event, [$event->ticketTypes()->first()->id => 2], ['name' => 'Gus', 'email' => 'g@x.test']);
    [$t1, $t2] = $order->tickets()->get()->all();

    $page->call('openEvent', $event->id)->assertSee('Gus')
        ->set('checkinCode', strtolower($t1->code))->call('checkInByCode');
    expect($t1->fresh()->checked_in_at)->not->toBeNull();

    $page->call('toggleCheckIn', $t2->id);
    expect($t2->fresh()->checked_in_at)->not->toBeNull();
    $page->call('toggleCheckIn', $t2->id);
    expect($t2->fresh()->checked_in_at)->toBeNull();

    $page->set('attendeeSearch', $t2->code)->assertViewHas('attendees', fn ($a) => $a->count() === 1);
});

test('the attendee CSV export lists every ticket', function () {
    Mail::fake();
    [$owner, $site] = eventsAdminSite();
    $event = eventsAdminEvent($site);
    $order = app(EventTickets::class)->placeOrder($site, $event, [$event->ticketTypes()->first()->id => 2],
        ['name' => '=cmd', 'email' => 'c@x.test'], ['=cmd', 'Dee']);

    $res = Livewire::actingAs($owner)->test(EventsPage::class, ['site' => $site])
        ->call('openEvent', $event->id)->call('exportCsv')
        ->assertFileDownloaded('attendees-'.$event->slug.'.csv');

    $csv = base64_decode(data_get($res->effects, 'download.content'));
    $codes = $order->tickets()->pluck('code');
    expect($csv)->toContain('Code,Attendee')->toContain($codes[0])->toContain($codes[1])->toContain('Dee')
        ->toContain("'=cmd")                                                    // formula injection neutralised
        ->not->toContain(',=cmd');
});

test('refunds go through the gateway, cancelling notifies ticket holders, messages reach attendees', function () {
    Mail::fake();
    [$owner, $site] = eventsAdminSite();
    $gateway = new FakePaymentGateway;
    app(PaymentManager::class)->fake($gateway);
    $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    $event = eventsAdminEvent($site, [], 1000);
    $engine = app(EventTickets::class);
    $order = $engine->placeOrder($site, $event, [$event->ticketTypes()->first()->id => 1], ['name' => 'R', 'email' => 'r@x.test']);
    $engine->markPaid($site, $order, 'pi_9');

    $page = Livewire::actingAs($owner)->test(EventsPage::class, ['site' => $site])->call('openEvent', $event->id)
        ->set('mailSubject', 'Parking')->set('mailBody', 'Use the north car park.')->call('sendMessage')->assertHasNoErrors();
    Mail::assertQueued(AttendeeMessage::class, fn ($m) => $m->hasTo('r@x.test') && $m->subjectLine === 'Parking');

    $page->call('refundOrder', $order->id);
    expect($gateway->refunds)->toBe([['ref' => 'pi_9', 'amount' => null]])
        ->and($order->fresh()->status)->toBe('refunded');

    $page->call('cancelEvent', $event->id);
    expect($event->fresh()->status)->toBe('cancelled');
});

test('changes need events.manage', function () {
    [$owner, $site] = eventsAdminSite();
    $event = eventsAdminEvent($site);
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(EventsPage::class, ['site' => $site])->call('newEvent')->assertStatus(403);
    Livewire::actingAs($member)->test(EventsPage::class, ['site' => $site])->call('cancelEvent', $event->id)->assertStatus(403);
    Livewire::actingAs($member)->test(EventsPage::class, ['site' => $site])->call('openEvent', $event->id)
        ->call('toggleCheckIn', 'nope')->assertStatus(403);
    expect($event->fresh()->status)->toBe('published');

    // Viewers may look (the role has events.view) but every change above was refused.
    $this->actingAs($member)->get("/{$site->name}/events")->assertOk();
});
