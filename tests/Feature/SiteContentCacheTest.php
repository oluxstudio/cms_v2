<?php

use App\Livewire\ConnectReviewPage;
use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteContentCache;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

// Site Names are unique across sites: keep each test's sites out of the next run.
uses(DatabaseTransactions::class);

beforeEach(fn () => SiteContentCache::reset());

function cacheSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'cc-'.substr(uniqid(), -8), 'domain' => 'cc-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $c = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'test']);
    $page->components()->attach($c->id, ['order' => 1]);
    $c->nodes()->create(['parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'Hello', 'order' => 1]);

    return [$owner, $site->fresh(), $c];
}

function queriesFor(callable $fn): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $fn();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
}

test('the second visit is served from the cache with far fewer queries and the same content', function () {
    [, $site] = cacheSite();
    $url = "/api/sites/{$site->name}/content";

    $first = null;
    $cold = queriesFor(function () use ($url, &$first) {
        $first = $this->getJson($url)->assertOk()->json();
    });
    $second = null;
    $warm = queriesFor(function () use ($url, &$second) {
        $second = $this->getJson($url)->assertOk()->json();
    });

    expect($second)->toEqual($first)
        ->and($warm)->toBeLessThanOrEqual(3)            // site lookup + version only
        ->and($cold)->toBeGreaterThan($warm * 3);
});

test('an edit shows up straight away — model events and the Edit page both retire the cache', function () {
    [$owner, $site, $c] = cacheSite();
    $url = "/api/sites/{$site->name}/content";
    $heading = fn () => collect($this->getJson($url)->json('pages.0.components.0.nodes'))->firstWhere('label', 'Heading')['value'];

    expect($heading())->toBe('Hello');

    $c->nodes()->where('label', 'Heading')->first()->update(['value' => 'Welcome']);
    expect($heading())->toBe('Welcome');

    // A bulk update (no model events), as the Edit page does, followed by its save refresh.
    Node::where('component_id', $c->id)->where('label', 'Heading')->update(['value' => 'Bulk']);
    expect($heading())->toBe('Welcome');                 // still cached…
    Livewire\Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $c->id)->call('saveComponent');
    expect($heading())->toBe('Bulk');                    // …until the Edit page's save retires it

    SiteProperties::save($site, ['values' => ['site_name' => 'New Name']]);
    expect($this->getJson($url)->json('site.properties.name'))->toBe('New Name');

    $site->update(['description' => 'Changed']);
    expect($this->getJson($url)->json('site.description'))->toBe('Changed');
});

test('browsers and CDNs can revalidate by ETag and get 304 until something changes', function () {
    [, $site, $c] = cacheSite();
    $url = "/api/sites/{$site->name}/content";

    $res = $this->getJson($url)->assertOk()->assertHeader('ETag');
    expect($res->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=');
    $etag = $res->headers->get('ETag');

    $this->getJson($url, ['If-None-Match' => $etag])->assertStatus(304);

    $c->nodes()->first()->update(['value' => 'Changed']);
    $this->getJson($url, ['If-None-Match' => $etag])->assertOk();

    // The single-page endpoint is cached per page too.
    $this->getJson("/api/sites/{$site->name}/page?url=/")->assertOk()->assertHeader('ETag');
});
