<?php

use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\LiveShell;

test('live-served pages carry the analytics beacon', function () {
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => $n = 'beacon-'.uniqid(), 'domain' => $n.'.test',
        'owner' => $owner->name, 'description' => 't', 'template' => 'graceway', 'live' => true,
    ]);

    $html = app(LiveShell::class)->respond($site)->getContent();

    expect($html)->toContain('/api/sites/'.$site->name.'/track')
        ->and($html)->toContain('sendBeacon')
        ->and($html)->toContain('pushState'); // SPA navigations tracked too
})->skip(fn () => ! is_file(public_path('nuxt-preview/index.html')) && ! is_file(public_path('nuxt-preview/graceway/index.html')), 'no shell built locally');

test('the track endpoint records a visit', function () {
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => $n = 'track-'.uniqid(), 'domain' => $n.'.test',
        'owner' => $owner->name, 'description' => 't',
    ]);

    $before = Visit::where('site_id', $site->id)->count();

    // text/plain body, exactly like the injected beacon sends it.
    $this->call('POST', '/api/sites/'.$site->name.'/track', [], [], [],
        ['CONTENT_TYPE' => 'text/plain', 'HTTP_ORIGIN' => 'http://'.$site->domain],
        json_encode(['path' => '/about', 'referrer' => 'https://google.com', 'language' => 'en-GB', 'session_id' => 'tab-1'])
    )->assertNoContent();

    expect(Visit::where('site_id', $site->id)->count())->toBe($before + 1);
    $v = Visit::where('site_id', $site->id)->latest('id')->first();
    expect($v->path)->toBe('/about');
});

test('beacons from the free subdomain host are accepted', function () {
    config(['publishing.subdomain_base' => 'olux.host']);
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => $n = 'sub-'.uniqid(), 'domain' => $n.'.test',
        'owner' => $owner->name, 'description' => 't',
    ]);

    $this->call('POST', '/api/sites/'.$site->name.'/track', [], [], [],
        ['CONTENT_TYPE' => 'text/plain', 'HTTP_ORIGIN' => 'https://'.$site->name.'.olux.host'],
        json_encode(['path' => '/', 'session_id' => 'tab-2'])
    )->assertNoContent();

    expect(Visit::where('site_id', $site->id)->count())->toBe(1);
});
