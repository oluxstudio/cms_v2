<?php

use App\Models\Block;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Form;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function structureSite(): Site
{
    $owner = User::factory()->create();

    return Site::create(['user_id' => $owner->id, 'name' => 'st-'.uniqid(), 'domain' => 'example.com', 'owner' => $owner->name, 'description' => 't']);
}

function seedContent(Site $site): array
{
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);

    $collection = Collection::create(['site_id' => $site->id, 'name' => 'Team', 'type' => 'list', 'is_public' => true, 'fields' => [['key' => 'name', 'type' => 'text']]]);
    CollectionItem::create(['collection_id' => $collection->id, 'site_id' => $site->id, 'data' => ['name' => 'Ada'], 'status' => 'published']);

    $component = Component::create(['site_id' => $site->id, 'name' => 'About', 'author' => 'System', 'source' => 'app']);
    // A root node + a child (for node_tree nesting) + a collection-typed node.
    $root = Node::create(['component_id' => $component->id, 'label' => 'heading', 'type' => 'text', 'value' => 'Hi', 'parent' => '0', 'order' => 0]);
    Node::create(['component_id' => $component->id, 'label' => 'sub', 'type' => 'text', 'value' => 'child', 'parent' => $root->id, 'order' => 0]);
    Node::create(['component_id' => $component->id, 'label' => 'team', 'type' => 'collection', 'value' => $collection->id, 'parent' => '0', 'order' => 1]);
    $page->components()->attach($component->id, ['order' => 0]);

    // A BlockKit form block on the page + its backing Form.
    $block = Block::create(['id' => 'blk_'.Str::lower(Str::random(12)), 'page_id' => $page->id, 'type' => 'form', 'position' => 0, 'props' => [], 'style' => [], 'meta' => []]);
    $form = Form::create(['site_id' => $site->id, 'name' => 'blockkit-'.$block->id, 'title' => 'Contact', 'fields' => [['key' => 'email', 'type' => 'email']], 'is_active' => true]);

    return compact('page', 'collection', 'component', 'form');
}

test('the content endpoint nests components, collections and forms under each page', function () {
    $site = structureSite();
    seedContent($site);

    $res = $this->getJson("/api/sites/{$site->name}/content")->assertOk();

    // Non-breaking: legacy top-level keys still present.
    $res->assertJsonStructure(['site', 'pages']);

    $page = $res->json('pages.0');
    expect($page)->toHaveKeys(['name', 'url', 'attributes', 'components', 'collections', 'forms', 'block_tree']);

    // Component carries BOTH the flat nodes and the nested node_tree.
    $component = $page['components'][0];
    expect($component)->toHaveKeys(['nodes', 'node_tree'])
        ->and($component['node_tree'][0]['children'])->not->toBeEmpty();   // root → child nesting

    // Page collections are full (fields + items), from the component's collection-node.
    expect($page['collections'])->toHaveCount(1)
        ->and($page['collections'][0]['items'][0]['data']['name'])->toBe('Ada');

    // Page forms are the page's blockkit form.
    expect($page['forms'])->toHaveCount(1)
        ->and($page['forms'][0]['title'])->toBe('Contact');

    // Top-level site-wide sets present too.
    expect($res->json('collections'))->toHaveCount(1)
        ->and($res->json('forms'))->toHaveCount(1);
});

test('the single-page endpoint mirrors the nested structure', function () {
    $site = structureSite();
    seedContent($site);

    $res = $this->getJson("/api/sites/{$site->name}/page?url=/")->assertOk();
    $page = $res->json('page');

    expect($page)->toHaveKeys(['components', 'collections', 'forms'])
        ->and($page['collections'])->toHaveCount(1)
        ->and($page['forms'])->toHaveCount(1);
    expect($res->json('collections'))->toHaveCount(1);
});

test('embedded collections match the /collections endpoint shape', function () {
    $site = structureSite();
    seedContent($site);

    $fromContent = $this->getJson("/api/sites/{$site->name}/content")->json('collections.0');
    $fromEndpoint = $this->getJson("/api/sites/{$site->name}/collections")->json('collections.0');

    expect(array_keys($fromContent))->toEqualCanonicalizing(array_keys($fromEndpoint));
});
