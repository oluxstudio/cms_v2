<?php

use App\Livewire\GoLivePage;
use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function goLiveSite(?string $domain = null): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'golive-'.uniqid(),
        'domain' => $domain ?? 'pending-'.uniqid().'.example', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

test('domains are normalized and junk is rejected', function () {
    expect(Site::normalizeDomain('https://www.Example.COM/path?x=1'))->toBe('example.com')
        ->and(Site::normalizeDomain('shop.my-brand.co.uk'))->toBe('shop.my-brand.co.uk')
        ->and(Site::normalizeDomain('not a domain'))->toBeNull()
        ->and(Site::normalizeDomain('localhost'))->toBeNull();
});

test('owner can connect a domain; duplicates are refused', function () {
    [$owner, $site] = goLiveSite();
    $wanted = 'brand-'.uniqid().'.com';

    Livewire::actingAs($owner)
        ->test(GoLivePage::class, ['site' => $site])
        ->set('domain', 'https://WWW.'.$wanted.'/about')
        ->call('saveDomain');

    expect($site->fresh()->domain)->toBe($wanted);

    // Another site cannot claim the same domain.
    [$other, $otherSite] = goLiveSite();
    $component = Livewire::actingAs($other)
        ->test(GoLivePage::class, ['site' => $otherSite])
        ->set('domain', $wanted)
        ->call('saveDomain');
    expect($otherSite->fresh()->domain)->not->toBe($wanted)
        ->and($component->get('errorMessage'))->toContain('already connected');
});

test('changing the domain resets verification and live state', function () {
    [$owner, $site] = goLiveSite();
    $site->update(['live' => true, 'domain_verified_at' => now()]);

    Livewire::actingAs($owner)
        ->test(GoLivePage::class, ['site' => $site])
        ->set('domain', 'switched-'.uniqid().'.com')
        ->call('saveDomain');

    $site->refresh();
    expect($site->live)->toBeFalse()
        ->and($site->domain_verified_at)->toBeNull();
});

test('go-live is gated by the publish.manage permission', function () {
    [$owner, $site] = goLiveSite();
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $viewer->id]);

    Livewire::actingAs($member)->test(GoLivePage::class, ['site' => $site])->assertStatus(403);
    $this->actingAs($member)->get("/{$site->name}/publish")->assertForbidden();
    $this->actingAs($owner)->get("/{$site->name}/publish")->assertOk();
});

test('a live domain serves the site shell with identity injected', function () {
    $domain = 'live-'.uniqid().'.example';
    [, $site] = goLiveSite($domain);
    $site->update(['live' => true, 'domain_verified_at' => now()]);

    $response = $this->get("http://{$domain}/about-us");

    $response->assertOk()
        ->assertHeader('X-Olux-Live', $site->name);
    expect($response->getContent())->toContain('__OLUX_SITE__')
        ->and($response->getContent())->toContain($site->name);

    // www. resolves to the same site.
    $this->get("http://www.{$domain}/")->assertOk()->assertHeader('X-Olux-Live', $site->name);
});

test('an offline or unknown domain never serves a site', function () {
    $domain = 'offline-'.uniqid().'.example';
    [, $site] = goLiveSite($domain);
    // live = false (default): request falls through to normal routing (auth redirect).
    $response = $this->get("http://{$domain}/");
    expect($response->headers->has('X-Olux-Live'))->toBeFalse();

    $unknown = $this->get('http://never-registered-'.uniqid().'.example/');
    expect($unknown->headers->has('X-Olux-Live'))->toBeFalse();
});

test('api paths on a live domain pass through to the backend', function () {
    $domain = 'api-'.uniqid().'.example';
    [, $site] = goLiveSite($domain);
    $site->update(['live' => true]);

    $response = $this->get("http://{$domain}/api/sites/{$site->name}/content");
    expect($response->headers->has('X-Olux-Live'))->toBeFalse(); // handled by the API, not the shell
});
