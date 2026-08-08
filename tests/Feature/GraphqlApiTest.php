<?php

use App\Models\ApiToken;
use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function gqlSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'gql-'.uniqid(),
        'domain' => 'gql.test', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

function gql(object $test, string $query, array $headers = [])
{
    return $test->postJson('/api/graphql', ['query' => $query], $headers);
}

test('the site query returns the content graph: pages, components, nodes, attributes', function () {
    [$owner, $site] = gqlSite();
    $site->setAttr('theme', 'dark');
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $component = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'Test']);
    Node::create(['component_id' => $component->id, 'label' => 'Heading', 'type' => 'text', 'value' => 'Welcome!', 'parent' => 0, 'order' => 0]);
    $page->components()->attach($component->id, ['order' => 0]);

    $data = gql($this, "{ site(name: \"{$site->name}\") { name attributes pages { name url components { name nodes { label type value } } } } }")
        ->assertOk()->json('data.site');

    expect($data['name'])->toBe($site->name)
        ->and($data['attributes']['theme'] ?? null)->toBe('dark')
        ->and($data['pages'][0]['components'][0]['nodes'][0]['value'])->toBe('Welcome!');
});

test('drafts and private collections are hidden publicly but widen with an able token', function () {
    [$owner, $site] = gqlSite();
    Post::create(['site_id' => $site->id, 'title' => 'Live', 'slug' => 'live', 'body' => '', 'status' => 'published', 'published_at' => now()]);
    Post::create(['site_id' => $site->id, 'title' => 'Secret draft', 'slug' => 'secret-draft', 'body' => '', 'status' => 'draft']);
    $site->collections()->create(['name' => 'Private', 'slug' => 'Private', 'type' => 'list', 'is_public' => false]);

    $q = "{ site(name: \"{$site->name}\") { posts { slug status } collections { name } } }";

    // Public: only the published post, no private collection.
    $public = gql($this, $q)->assertOk()->json('data.site');
    expect(collect($public['posts'])->pluck('slug'))->not->toContain('secret-draft')
        ->and($public['collections'])->toHaveCount(0);

    // Token with view abilities: both widen.
    $raw = Str::random(64);
    ApiToken::create(['user_id' => $owner->id, 'name' => 'g', 'token' => hash('sha256', $raw)]);
    $widened = gql($this, $q, ['Authorization' => 'Bearer '.$raw])->assertOk()->json('data.site');
    expect(collect($widened['posts'])->pluck('slug'))->toContain('secret-draft')
        ->and($widened['collections'])->toHaveCount(1);
});

test('over-deep queries are rejected by the depth limit', function () {
    [, $site] = gqlSite();

    // 10 levels of introspection nesting — beyond the max depth of 8.
    $deep = '{ __schema { queryType { fields { type { ofType { ofType { ofType { ofType { ofType { ofType { ofType { ofType { ofType { name } } } } } } } } } } } } } }';
    $response = gql($this, $deep);

    expect($response->json('errors.0.message'))->toContain('Max query depth');
});

test('list limits are enforced and the limit argument works', function () {
    [, $site] = gqlSite();
    foreach (range(1, 3) as $i) {
        Post::create(['site_id' => $site->id, 'title' => "P$i", 'slug' => "p$i", 'body' => '', 'status' => 'published', 'published_at' => now()->subMinutes($i)]);
    }

    $one = gql($this, "{ site(name: \"{$site->name}\") { posts(limit: 1) { slug } } }")->assertOk()->json('data.site.posts');
    expect($one)->toHaveCount(1);

    // Absurd limits are capped server-side (still succeeds, never unbounded).
    gql($this, "{ site(name: \"{$site->name}\") { posts(limit: 5000) { slug } } }")->assertOk();
});

test('introspection is blocked without a token in production mode', function () {
    [, $site] = gqlSite();
    $this->app['env'] = 'production';

    $response = gql($this, '{ __schema { queryType { name } } }');
    expect((string) json_encode($response->json('errors')))->toContain('introspection');

    $this->app['env'] = 'testing';
});
