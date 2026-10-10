<?php

use App\Livewire\SiteComponent;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

test('clicking a site tile switches to that site dashboard', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'switch-'.uniqid(), 'domain' => 'd.test', 'owner' => $owner->name, 'description' => 't']);

    Livewire::actingAs($owner)->test(SiteComponent::class)
        ->call('selected', $site->id)
        ->assertRedirect('/'.$site->name.'/dashboard');
});

test('a user cannot switch to a site they cannot access', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'priv-'.uniqid(), 'domain' => 'd.test', 'owner' => $owner->name, 'description' => 't']);
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)->test(SiteComponent::class)
        ->call('selected', $site->id)
        ->assertStatus(403);
});

it('has a How to use button that opens the guide on the sites page', function () {
    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);

    $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain("\$dispatch('open-how-to')")
        ->toContain('id="how-to-use"')
        ->toContain('@open-how-to.window')
        ->toContain('From a new site to your first customer');
});
