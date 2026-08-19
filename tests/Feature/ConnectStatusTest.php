<?php

use App\Models\Page;
use App\Models\Site;
use App\Models\SiteConnection;
use App\Models\User;

// connectToken() is a shared helper in tests/Pest.php.

test('status reports collect mode before anything is published', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $token = connectToken($site);

    $this->withToken($token)
        ->getJson('/api/v1/connect/status?path=/')
        ->assertOk()
        ->assertJsonPath('mode', 'collect')
        ->assertJsonPath('schemaVersion', 2)
        ->assertJsonPath('pageJsonUrl', null);
});

test('status reports hydrate mode with a resolved page.json url', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    Page::factory()->create(['site_id' => $site->id, 'url' => '/about', 'name' => 'About']);
    SiteConnection::create(['site_id' => $site->id, 'mode' => 'hydrate']);
    $token = connectToken($site);

    $this->withToken($token)
        ->getJson('/api/v1/connect/status?path=/about')
        ->assertOk()
        ->assertJsonPath('mode', 'hydrate')
        ->assertJsonPath('pageJsonUrl', route('api.v1.page-json', ['siteName' => $site->name, 'slug' => 'about']));
});

test('status requires a valid token', function () {
    $this->getJson('/api/v1/connect/status?path=/')->assertUnauthorized();
});

test('connect.js is served from the CMS domain as javascript under the size budget', function () {
    $res = $this->get('/connect.js');
    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('javascript');

    $bytes = strlen(file_get_contents(resource_path('site-connect/connect.js')));
    expect($bytes)->toBeLessThan(28 * 1024); // size budget (hydrate + collect + edit modes)
});
