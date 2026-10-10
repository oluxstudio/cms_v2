<?php

use App\Livewire\SiteComponent;
use App\Models\AccountSubscription;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

function lightboxUser(string $plan = 'growth'): User
{
    $user = User::factory()->create();
    AccountSubscription::create(['user_id' => $user->id, 'plan' => $plan, 'status' => 'active', 'started_at' => now()]);

    return $user;
}

test('the address follows the business name, is checked live, and the site gets the business details', function () {
    $user = lightboxUser();
    $name = 'Grace Way '.substr(uniqid(), -6);
    $slug = Illuminate\Support\Str::slug($name);
    $type = array_key_first(App\Services\Blueprints\BlueprintRegistry::types());

    Livewire::actingAs($user)->test(SiteComponent::class)
        ->call('openCreate')
        ->assertSee('Create a site')->assertSee('of 3 sites used')
        ->set('form.owner', $name)
        ->assertSet('form.name', $slug)->assertSet('available', true)
        ->set('type', $type)->assertSet('starter', 'business')
        ->set('form.description', 'A welcoming church — service times, events and sermons.')
        ->set('contactEmail', 'hello@graceway.test')
        ->set('contactPhone', '+44 20 7946 0000')
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('onboarding-updated');

    $site = Site::where('name', $slug)->firstOrFail();
    expect($site->owner)->toBe($name)
        ->and($site->domain)->toEndWith('.'.(config('publishing.subdomain_base') ?: 'oluxstudio.com'))
        ->and($site->getAttr('business_type'))->toBe($type)
        ->and($site->getAttr('site_purpose'))->toContain('service times')
        ->and(SiteProperties::value($site, 'email'))->toBe('hello@graceway.test')
        ->and(App\Models\ContentVersion::where('site_id', $site->id)->exists())->toBeFalse();
});

test('name, description and a free address are required; a taken address is refused', function () {
    $user = lightboxUser();
    $taken = Site::factory()->create(['name' => 'taken-'.substr(uniqid(), -6)]);

    Livewire::actingAs($user)->test(SiteComponent::class)
        ->call('openCreate')
        ->call('create')
        ->assertHasErrors(['form.owner', 'form.description'])
        ->set('form.owner', 'Taken Business')
        ->set('form.name', $taken->name)->assertSet('available', false)
        ->set('form.description', 'Something long enough to pass.')
        ->call('create')
        ->assertHasErrors(['form.name']);
});

test('at the plan limit the lightbox becomes an upgrade prompt', function () {
    $user = lightboxUser('starter');
    Site::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)->test(SiteComponent::class)
        ->call('openCreate')
        ->assertSee('Your plan is full')->assertSee('Compare plans')
        ->assertDontSee('What is the site for?');
});
