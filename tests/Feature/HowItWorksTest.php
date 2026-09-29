<?php

use App\Features\FeatureRegistry;
use App\Livewire\OnboardingChecklist;
use App\Models\Site;
use App\Models\User;
use App\Support\Money;

test('the how-it-works page requires auth', function () {
    $this->get('/how-it-works')->assertRedirect(route('login'));
});

test('the getting started guide covers the journey, plans, add-ons, going live and FAQs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSee('Getting started')
        ->assertSee('Create your site')
        ->assertSee('Choose a template')
        ->assertSee('Update your pages')
        ->assertSee('Get a domain name')
        ->assertSee('Put your site live')
        ->assertSee(config('plans.tiers.pro.name'))
        ->assertSee(FeatureRegistry::get('bookings')['name'])
        ->assertSee('Do I need to buy a domain?');
});

test('the plans section lists public plans and marks the reader\'s own', function () {
    $user = User::factory()->create();
    $user->currentSubscription()->update(['plan' => 'business', 'status' => 'active']);

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSeeInOrder([config('plans.tiers.business.name'), 'Your plan'])
        ->assertSee(Money::format((int) config('plans.tiers.business.price_cents'), 'gbp'));
});

test('first login shows the intro pack with plans; after hiding it the button brings it back', function () {
    $user = User::factory()->create();

    Livewire\Livewire::actingAs($user)->test(OnboardingChecklist::class)
        ->assertSeeInOrder(['Welcome', 'Your 5 steps', 'Plans', 'What’s next'])
        ->assertSee('Pick your')
        ->assertSee('Choose a template')
        ->assertSee(config('plans.tiers.starter.name'))
        ->call('dismiss')
        ->assertDontSee('Your 5 steps');

    $this->actingAs($user->fresh())->get('/select-site')
        ->assertOk()
        ->assertSee('Show introduction')
        ->assertDontSee('Your 5 steps');

    // The button reopens the same panel, without resetting "shown once".
    Livewire\Livewire::actingAs($user->fresh())->test(OnboardingChecklist::class)
        ->assertSet('open', false)
        ->dispatch('show-intro')
        ->assertSet('open', true)
        ->assertSee('Your 5 steps');
});

test('stage CTAs deep-link to the user\'s site when they have one', function () {
    $user = User::factory()->create();
    $site = Site::create(['user_id' => $user->id, 'name' => 'hiw-'.uniqid(), 'domain' => 'hiw.test', 'owner' => $user->name, 'description' => 't']);

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSee(route('site.design', $site->name), false)
        ->assertSee(url($site->name.'/connect'), false)
        ->assertSee(route('site.publish', $site->name), false)
        ->assertDontSee('Create a site first to unlock this.');
});

test('with no site, stages show the create-first fallback', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/how-it-works')
        ->assertOk()
        ->assertSee('Create a site first to unlock this.');
});
