<?php

use App\Livewire\MarketplacePage;
use App\Livewire\SiteComponent;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function planUser(string $plan = 'trial'): User
{
    $user = User::factory()->create();
    $user->currentSubscription()->update(['plan' => $plan, 'status' => $plan === 'trial' ? 'trialing' : 'active']);

    return $user;
}

test('the subscription helper reads limits from config', function () {
    $trial = planUser('trial');
    expect($trial->currentSubscription()->sitesLimit())->toBe(1)
        ->and($trial->currentSubscription()->allowsPremium())->toBeTrue();

    $starter = planUser('starter');
    expect($starter->currentSubscription()->allowsPremium())->toBeFalse();

    $ent = planUser('enterprise');
    expect($ent->currentSubscription()->sitesLimit())->toBeNull()          // unlimited
        ->and($ent->currentSubscription()->canCreateSite())->toBeTrue();
});

test('a trial account is blocked from a second site with an upgrade prompt', function () {
    $user = planUser('trial');
    Site::create(['user_id' => $user->id, 'name' => 's1-'.uniqid(), 'domain' => 'a.test', 'owner' => $user->name, 'description' => 't']);

    expect($user->currentSubscription()->canCreateSite())->toBeFalse();

    Livewire::actingAs($user)->test(SiteComponent::class)
        ->set('form.name', 'second-'.uniqid())->set('form.domain', 'b.test')->set('form.owner', $user->name)
        ->call('create')
        ->assertDispatched('upgrade-required');

    expect($user->sites()->count())->toBe(1); // not created
});

test('an enterprise account can create multiple sites', function () {
    $user = planUser('enterprise');
    foreach (range(1, 3) as $i) {
        Livewire::actingAs($user)->test(SiteComponent::class)
            ->set('form.name', "e{$i}-".uniqid())->set('form.domain', "e{$i}.test")->set('form.owner', $user->name)
            ->call('create')->assertNotDispatched('upgrade-required');
    }
    expect($user->sites()->count())->toBe(3);
});

test('enabling a premium module is blocked on a non-premium plan', function () {
    $user = planUser('starter'); // premium = false
    $site = Site::create(['user_id' => $user->id, 'name' => 'm-'.uniqid(), 'domain' => 'm.test', 'owner' => $user->name, 'description' => 't']);

    Livewire::actingAs($user)->test(MarketplacePage::class, ['site' => $site])
        ->call('toggle', 'bookings')          // premium feature
        ->assertDispatched('upgrade-required');
    expect($site->fresh()->hasFeature('bookings'))->toBeFalse();
});

test('a premium plan can enable premium modules', function () {
    $user = planUser('pro'); // premium = true
    $site = Site::create(['user_id' => $user->id, 'name' => 'p-'.uniqid(), 'domain' => 'p.test', 'owner' => $user->name, 'description' => 't']);

    Livewire::actingAs($user)->test(MarketplacePage::class, ['site' => $site])
        ->call('toggle', 'bookings')
        ->assertNotDispatched('upgrade-required');
    expect($site->fresh()->hasFeature('bookings'))->toBeTrue();
});
