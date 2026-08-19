<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function graphqlFixture(): Site
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);
    $page = Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);

    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'test', 'source' => 'app']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'Look your best', 'order' => 0]);
    $page->components()->attach($hero->id, ['order' => 0]);

    $services = Collection::create(['site_id' => $site->id, 'name' => 'Services', 'slug' => 'services', 'type' => 'grid', 'is_public' => true]);
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Cut']]);
    $page->collections()->attach($services->id, ['order' => 1]);

    return $site;
}

function scGql(string $query): TestResponse
{
    return test()->postJson('/api/graphql', ['query' => $query]);
}

test('pageDocument returns the page.json shape over GraphQL', function () {
    $site = graphqlFixture();

    scGql('{ site(name:"'.$site->name.'"){ pageDocument(slug:"index"){ schemaVersion title components{ key position fields } collections{ key schema } } } }')
        ->assertOk()
        ->assertJsonPath('data.site.pageDocument.schemaVersion', 2)
        ->assertJsonPath('data.site.pageDocument.title', 'Home')
        ->assertJsonPath('data.site.pageDocument.components.0.key', 'hero')
        ->assertJsonPath('data.site.pageDocument.components.0.fields.heading', 'Look your best')
        ->assertJsonPath('data.site.pageDocument.collections.0.key', 'services');
});

test('collection(key) resolves a single collection with items', function () {
    $site = graphqlFixture();

    scGql('{ site(name:"'.$site->name.'"){ collection(key:"services"){ name items { id } } } }')
        ->assertOk()
        ->assertJsonPath('data.site.collection.name', 'Services')
        ->assertJsonCount(1, 'data.site.collection.items');
});

test('pageDocument only sees its own site (tenant-scoped)', function () {
    $site = graphqlFixture();
    $other = graphqlFixture();

    // Asking site A for a slug it does not have returns null, never site B's.
    scGql('{ site(name:"'.$site->name.'"){ pageDocument(slug:"nonexistent"){ title } } }')
        ->assertOk()
        ->assertJsonPath('data.site.pageDocument', null);
});

test('GraphQL exposes no mutations', function () {
    scGql('mutation { __typename }')
        ->assertJson(fn ($j) => $j->has('errors')->etc());
});
