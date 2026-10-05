<?php

use App\Livewire\GoLivePage;
use App\Livewire\SignupWizard;
use App\Models\Site;
use App\Models\SiteAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(fn () => config(['publishing.subdomain_base' => 'sites.test']));

function addressSite(string $name = 'church', array $attrs = []): array
{
    $owner = User::factory()->create();
    $site = Site::create($attrs + [
        'user_id' => $owner->id, 'name' => $name, 'domain' => '',
        'owner' => $owner->name, 'description' => 't',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('changing the address renames the site and keeps the old one as an alias', function () {
    [, $site] = addressSite('church', ['domain' => 'church.sites.test']);

    $site->changeAddress('olux-studio');
    $site->refresh();

    expect($site->name)->toBe('olux-studio')
        ->and($site->subdomainHost())->toBe('olux-studio.sites.test')
        ->and($site->domain)->toBe('olux-studio.sites.test')   // the auto subdomain follows
        ->and(SiteAlias::where('name', 'church')->value('site_id'))->toBe($site->id)
        ->and(Site::forOldName('church')?->id)->toBe($site->id)
        ->and(Site::forOldName('olux-studio'))->toBeNull()
        ->and(Site::nameTaken('church'))->toBeTrue();
});

test('a custom domain is left alone, and taking back an old address drops its alias', function () {
    [, $site] = addressSite('church', ['domain' => 'mychurch.org']);

    $site->changeAddress('grace');
    $site->changeAddress('church');
    $site->refresh();

    expect($site->name)->toBe('church')
        ->and($site->domain)->toBe('mychurch.org')
        ->and(SiteAlias::where('site_id', $site->id)->pluck('name')->all())->toBe(['grace']);
});

test('invalid, reserved or taken addresses are refused', function () {
    [, $site] = addressSite('church');
    addressSite('taken');
    [, $other] = addressSite('mover');
    $other->changeAddress('mover-2');   // "mover" is now someone else's old address

    expect($site->addressError('church'))->not->toBeNull()
        ->and($site->addressError('ab'))->not->toBeNull()
        ->and($site->addressError('www'))->not->toBeNull()
        ->and($site->addressError('taken'))->toBe('That address is taken. Try another.')
        ->and($site->addressError('mover'))->toBe('That address is taken. Try another.')
        ->and($site->addressError('fresh-name'))->toBeNull();

    expect(fn () => $site->changeAddress('taken'))->toThrow(InvalidArgumentException::class);
    expect($site->fresh()->name)->toBe('church');
});

test('old admin links redirect to the new address', function () {
    [$owner, $site] = addressSite('church');
    $site->changeAddress('olux-studio');

    $this->actingAs($owner)->get('/church/publish?tab=1')
        ->assertRedirect('/olux-studio/publish?tab=1')
        ->assertStatus(301);
    $this->actingAs($owner)->get('/olux-studio/publish')->assertOk();
});

test('API calls on the old name resolve to the site without a redirect', function () {
    [, $site] = addressSite('church');
    $site->changeAddress('olux-studio');

    $old = $this->getJson('/api/sites/church/content')->assertOk()->json();
    $new = $this->getJson('/api/sites/olux-studio/content')->assertOk()->json();

    expect($old)->toEqual($new);
});

test('the old subdomain redirects to the new one, path included', function () {
    [, $site] = addressSite('church');
    $site->changeAddress('olux-studio');

    $this->get('http://church.sites.test/about?x=1')
        ->assertStatus(301)
        ->assertRedirect('https://olux-studio.sites.test/about?x=1');
});

test('the Publish page changes the address and returns to the new URL', function () {
    [$owner, $site] = addressSite('church');
    addressSite('taken');

    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->assertSee('Change web address')
        ->call('openAddress')
        ->assertSet('newAddress', 'church')
        ->set('newAddress', 'Taken')
        ->assertSet('newAddress', 'taken')
        ->assertSet('addressError', 'That address is taken. Try another.')
        ->set('newAddress', 'Olux Studio')
        ->assertSet('newAddress', 'olux-studio')
        ->assertSet('addressError', '')
        ->assertSee('olux-studio.sites.test is available.')
        ->call('changeAddress')
        ->assertRedirect(route('site.publish', 'olux-studio'));

    expect($site->fresh()->name)->toBe('olux-studio');
});

test('signup will not offer another site’s old address', function () {
    [, $site] = addressSite('church');
    $site->changeAddress('olux-studio');

    Livewire::actingAs(User::factory()->create())->test(SignupWizard::class)
        ->set('subdomain', 'church')
        ->call('checkAvailability')
        ->assertSet('available', false);
})->skip(fn () => ! method_exists(SignupWizard::class, 'checkAvailability'));
