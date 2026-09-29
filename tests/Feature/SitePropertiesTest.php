<?php

use App\Livewire\SitePropertiesPage;
use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteProperties;
use Livewire\Livewire;

function propsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'props-'.uniqid(), 'domain' => 'props-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);

    return [$owner, $site];
}

test('the owner opens the properties page, a viewer without the permission cannot', function () {
    [$owner, $site] = propsSite();

    $this->actingAs($owner)->get("/{$site->name}/properties")
        ->assertOk()
        ->assertSee('Properties')
        ->assertSee('Custom variables');

    $viewer = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $viewer->id,
        'role_id' => Role::forAccount($owner)->firstWhere('slug', 'viewer')->id, 'site_id' => $site->id]);
    $this->actingAs($viewer)->get("/{$site->name}/properties")->assertForbidden();
});

test('name, logo, emails, phone numbers and typed variables are saved; blank rows are dropped', function () {
    [$owner, $site] = propsSite();

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('name', 'Grace Way Church')
        ->set('logo', '/storage/sites/x/logo.png')
        ->set('email', 'hello@graceway.test')
        ->call('addPhone')->set('phones.0', ['label' => 'Office', 'value' => '+44 20 7946 0000'])
        ->call('addPhone')->set('phones.1', ['label' => 'Mobile', 'value' => '07700 900123'])
        ->call('addPhone') // left blank → dropped
        ->call('addEmail')->set('emails.0', ['label' => 'Bookings', 'value' => 'book@graceway.test'])
        ->call('addVariable', 'text')->set('variables.0', ['key' => 'opening_hours', 'type' => 'text', 'value' => 'Sun 10am'])
        ->call('addVariable', 'image')->set('variables.1', ['key' => 'hero_image', 'type' => 'image', 'value' => '/storage/sites/x/hero.jpg'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $p = SiteProperties::get($site->fresh());
    expect($p['name'])->toBe('Grace Way Church')
        ->and($p['email'])->toBe('hello@graceway.test')
        ->and($p['phones'])->toHaveCount(2)
        ->and($p['phones'][1])->toBe(['label' => 'Mobile', 'value' => '07700 900123'])
        ->and($p['emails'][0]['value'])->toBe('book@graceway.test')
        ->and(collect($p['variables'])->pluck('type', 'key')->all())->toBe(['opening_hours' => 'text', 'hero_image' => 'image']);

    // Reopening the page loads what was saved.
    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site->fresh()])
        ->assertSet('name', 'Grace Way Church')
        ->assertSet('variables.1.key', 'hero_image');
});

test('invalid rows are rejected with messages and nothing is saved', function () {
    [$owner, $site] = propsSite();

    Livewire::actingAs($owner)->test(SitePropertiesPage::class, ['site' => $site])
        ->set('email', 'not-an-email')
        ->set('phones', [['label' => 'Office', 'value' => 'call me maybe']])
        ->set('emails', [['label' => 'Support', 'value' => '']])
        ->set('variables', [
            ['key' => 'Bad Key', 'type' => 'text', 'value' => 'x'],
            ['key' => 'dup', 'type' => 'text', 'value' => 'a'],
            ['key' => 'dup', 'type' => 'banana', 'value' => 'b'],
        ])
        ->call('save')
        ->assertHasErrors(['email', 'phones.0.value', 'emails.0.value', 'variables.0.key', 'variables.1.key', 'variables.2.type'])
        ->assertDispatched('properties-error');

    expect($site->fresh()->getAttr(SiteProperties::PHONES))->toBeNull();
});

test('templates get the properties from the content API, and emails fall back to the site logo', function () {
    [$owner, $site] = propsSite();
    SiteProperties::save($site, [
        'name' => 'Grace Way', 'logo' => '/storage/logo.png', 'email' => 'hi@gw.test',
        'phones' => [['label' => 'Office', 'value' => '0123']], 'emails' => [],
        'variables' => [['key' => 'hero_image', 'type' => 'image', 'value' => '/storage/hero.jpg'], ['key' => 'motto', 'type' => 'text', 'value' => 'Welcome home']],
    ]);

    $this->getJson("/api/sites/{$site->name}/content")
        ->assertOk()
        ->assertJsonPath('site.properties.name', 'Grace Way')
        ->assertJsonPath('site.properties.logo', url('/storage/logo.png'))
        ->assertJsonPath('site.properties.phones.0.value', '0123')
        ->assertJsonPath('site.properties.variables.motto', 'Welcome home')
        ->assertJsonPath('site.properties.variables.hero_image', url('/storage/hero.jpg'))
        ->assertJsonPath('site.properties.variable_types.hero_image', 'image');

    expect($site->brandLogo())->toBe('/storage/logo.png');
    $site->setAttr('email.logo', '/storage/email-logo.png');
    expect($site->brandLogo())->toBe('/storage/email-logo.png');
});
