<?php

use App\Livewire\MarketplacePage;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function navSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'mnav-'.uniqid(), 'domain' => 'mnav-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('enabling a feature adds its page to the menu (and the page unlocks); disabling removes it', function () {
    [$owner, $site] = navSite();

    // Before: nothing in the menu, page gated off.
    $this->actingAs($owner)->get("/{$site->name}/dashboard")->assertDontSee(url("{$site->name}/bookings"));
    $this->actingAs($owner)->get("/{$site->name}/bookings")->assertNotFound();

    Livewire::actingAs($owner)->test(MarketplacePage::class, ['site' => $site])
        ->call('toggle', 'bookings')
        ->assertRedirect(url("{$site->name}/marketplace"));

    expect($site->fresh()->hasFeature('bookings'))->toBeTrue();
    $this->actingAs($owner)->get("/{$site->name}/dashboard")->assertSee(url("{$site->name}/bookings"));
    $this->actingAs($owner)->get("/{$site->name}/bookings")->assertOk();

    Livewire::actingAs($owner)->test(MarketplacePage::class, ['site' => $site])->call('toggle', 'bookings');
    expect($site->fresh()->hasFeature('bookings'))->toBeFalse();
    $this->actingAs($owner)->get("/{$site->name}/bookings")->assertNotFound();
});

test('tiles link to the pages they unlock, and disabled tiles say what they add to the menu', function () {
    [$owner, $site] = navSite();
    $site->enableFeature('store');

    Livewire::actingAs($owner)->test(MarketplacePage::class, ['site' => $site])
        ->assertSee('Orders →')                      // enabled store tile links to its pages
        ->assertSee('Adds to your menu')             // disabled tiles carry the hint
        ->assertSee('Bookings');
});
