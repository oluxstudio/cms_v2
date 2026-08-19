<?php

use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\PageJsonPublisher;
use Illuminate\Support\Facades\Storage;

function connectPublishedSite(string $heading = 'Hello'): Page
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $page = Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'test', 'source' => 'app']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => $heading, 'order' => 0]);
    $page->components()->attach($hero->id, ['order' => 0]);

    return $page->fresh();
}

test('publishing writes a versioned page.json to the disk and bumps the version', function () {
    Storage::fake(config('site_connect.disk'));
    $page = connectPublishedSite();

    $result = app(PageJsonPublisher::class)->publish($page);

    expect($result['version'])->toBe(1)
        ->and($result['path'])->toBe("tenants/{$page->site_id}/pages/index.json");
    Storage::disk(config('site_connect.disk'))->assertExists($result['path']);

    // Republish bumps the version and flips the connection to hydrate mode.
    $second = app(PageJsonPublisher::class)->publish($page->fresh());
    expect($second['version'])->toBe(2)
        ->and($page->site->connection->mode)->toBe('hydrate');
});

test('the public endpoint serves the published page.json', function () {
    Storage::fake(config('site_connect.disk'));
    $page = connectPublishedSite('Look your best');
    app(PageJsonPublisher::class)->publish($page);

    $this->getJson(route('api.v1.page-json', ['siteName' => $page->site->name, 'slug' => 'index']))
        ->assertOk()
        ->assertJsonPath('schemaVersion', 2)
        ->assertJsonPath('componentData.0.fields.heading', 'Look your best');
});

test('the endpoint generates live when nothing is published yet', function () {
    $page = connectPublishedSite('Draft heading');

    $this->getJson(route('api.v1.page-json', ['siteName' => $page->site->name, 'slug' => 'index']))
        ->assertOk()
        ->assertJsonPath('componentData.0.fields.heading', 'Draft heading');
});

test('a slug only resolves within its own site — no cross-tenant read', function () {
    $siteA = connectPublishedSite('Site A secret')->site;
    // Site B has no "about" page; asking site B for it must 404, never leak A.
    $siteB = connectPublishedSite('Site B')->site;

    $this->getJson(route('api.v1.page-json', ['siteName' => $siteB->name, 'slug' => 'about']))
        ->assertNotFound();
});
