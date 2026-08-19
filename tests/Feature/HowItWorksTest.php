<?php

use App\Models\Site;
use App\Models\User;

test('the how-it-works page requires auth', function () {
    $this->get('/how-it-works')->assertRedirect(route('login'));
});

test('the how-it-works page renders the full journey including bookings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSee('How Olux works')
        ->assertSee('Create your site')
        ->assertSee('Capture leads')
        ->assertSee('Take bookings while you sleep', false)
        ->assertSee('Publish');
});

test('stage CTAs deep-link to the user\'s site when they have one', function () {
    $user = User::factory()->create();
    $site = Site::create(['user_id' => $user->id, 'name' => 'hiw-'.uniqid(), 'domain' => 'hiw.test', 'owner' => $user->name, 'description' => 't']);

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSee(route('site.forms', $site->name), false)
        ->assertSee(route('site.marketplace', $site->name), false)
        ->assertDontSee('Create a site first to unlock this.');
});

test('with no site, stages show the create-first fallback', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSee('Create a site first to unlock this.');
});
