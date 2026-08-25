<?php

use App\Livewire\BookingsPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function panelSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id]);
    $site->enableFeature('bookings');

    return [$owner, $site];
}

test('the bookings page renders with every editor closed', function () {
    [$owner, $site] = panelSite();

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->assertOk()
        ->assertSet('panel', null)
        ->assertSee('＋ Add service')
        ->assertSee('Edit schedule')
        ->assertDontSee('Add a service');   // the form lives in the panel, not inline
});

test('add service opens the panel, saving closes it and the service is listed', function () {
    [$owner, $site] = panelSite();

    $lw = Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('newService')
        ->assertSet('panel', 'service')
        ->assertSee('Add a service')
        ->set('name', 'Haircut')
        ->set('duration', 45)
        ->set('price', '38')
        ->call('saveService')
        ->assertHasNoErrors()
        ->assertSet('panel', null)
        ->assertSee('Haircut');

    $svc = $site->services()->where('name', 'Haircut')->first();
    expect($svc)->not->toBeNull();

    // ✎ on the row → the same panel, prefilled with that service.
    $lw->call('editService', $svc->id)
        ->assertSet('panel', 'service')
        ->assertSet('name', 'Haircut')
        ->assertSee('Edit service')
        ->call('closePanel')
        ->assertSet('panel', null)
        ->assertSet('editingId', null);
});

test('schedule and exceptions open in the panel and close on demand', function () {
    [$owner, $site] = panelSite();

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('openPanel', 'schedule')
        ->assertSet('panel', 'schedule')
        ->assertSee('Bookable slots')
        ->call('saveAvailability')
        ->assertHasNoErrors()
        ->assertSet('panel', null)
        ->call('openPanel', 'exceptions')
        ->assertSet('panel', 'exceptions')
        ->assertSee('Close whole day')
        ->call('closePanel')
        ->assertSet('panel', null)
        ->call('openPanel', 'bogus')
        ->assertSet('panel', null);
});

test('resource editor opens in the panel and saving closes it', function () {
    [$owner, $site] = panelSite();

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('newSiteResource')
        ->assertSet('panel', 'resource')
        ->assertSee('Add a resource')
        ->set('srName', 'Bella')
        ->call('saveSiteResource')
        ->assertHasNoErrors()
        ->assertSet('panel', null)
        ->assertSee('Bella');
});
