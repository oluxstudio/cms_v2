<?php

use App\Livewire\CollectionsPage;
use App\Livewire\ComponentsPage;
use App\Models\Collection;
use App\Models\Component;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function groupingSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'cg-'.uniqid(), 'domain' => 'example.com', 'owner' => $owner->name, 'description' => 't']);

    return [$owner, $site];
}

function testimonial(Site $site, Collection $collection, string $name, int $order): Component
{
    $c = Component::create(['site_id' => $site->id, 'name' => $name, 'author' => 'System', 'source' => 'app', 'collection_id' => $collection->id, 'collection_order' => $order]);
    Node::create(['component_id' => $c->id, 'label' => 'quote', 'type' => 'text', 'value' => $name.' quote', 'parent' => '0', 'order' => 0]);

    return $c;
}

test('a collection groups components in order and each resolves back', function () {
    [$owner, $site] = groupingSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Testimonials', 'type' => 'list', 'is_public' => true]);

    testimonial($site, $col, 'Testimonial 2', 1);
    testimonial($site, $col, 'Testimonial 1', 0);
    testimonial($site, $col, 'Testimonial 3', 2);

    $names = $col->components()->pluck('name')->all();
    expect($names)->toBe(['Testimonial 1', 'Testimonial 2', 'Testimonial 3']);   // collection_order
    expect($col->components()->first()->collection->id)->toBe($col->id);          // back-reference
});

test('a collection can be attached to multiple pages, and a component to multiple pages', function () {
    [$owner, $site] = groupingSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Testimonials', 'type' => 'list', 'is_public' => true]);
    $home = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $about = Page::create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about', 'keywords' => '', 'is_published' => true]);

    $col->pages()->attach([$home->id => ['order' => 0], $about->id => ['order' => 0]]);
    expect($col->pages()->count())->toBe(2)
        ->and($home->collections()->count())->toBe(1)
        ->and($about->collections()->count())->toBe(1);

    // Components remain multi-page attachable (existing behaviour).
    $cmp = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'System', 'source' => 'app']);
    $cmp->pages()->attach([$home->id => ['order' => 1], $about->id => ['order' => 1]]);
    expect($cmp->pages()->count())->toBe(2);
});

test('the content API nests a page collection with its grouped components', function () {
    [$owner, $site] = groupingSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Testimonials', 'type' => 'list', 'is_public' => true]);
    testimonial($site, $col, 'Testimonial 1', 0);
    testimonial($site, $col, 'Testimonial 2', 1);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $col->pages()->attach($page->id, ['order' => 0]);

    $res = $this->getJson("/api/sites/{$site->name}/content")->assertOk();
    $pageColls = $res->json('pages.0.collections');

    expect($pageColls)->toHaveCount(1)
        ->and($pageColls[0]['components'])->toHaveCount(2)
        ->and($pageColls[0]['components'][0]['name'])->toBe('Testimonial 1')
        ->and($pageColls[0]['components'][0])->toHaveKey('node_tree');

    // Top-level site collections also carry their components.
    expect($res->json('collections.0.components'))->toHaveCount(2);
});

test('the components page assigns a component to a collection', function () {
    [$owner, $site] = groupingSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Testimonials', 'type' => 'list', 'is_public' => true]);

    Livewire::actingAs($owner)->test(ComponentsPage::class, ['site' => $site])
        ->call('open', '')
        ->set('cName', 'Testimonial A')
        ->set('collectionId', $col->id)
        ->set('nodes', [['label' => 'quote', 'type' => 'text', 'value' => 'Great', 'description' => '']])
        ->call('save');

    $cmp = Component::where('site_id', $site->id)->where('name', 'Testimonial A')->first();
    expect($cmp->collection_id)->toBe($col->id)
        ->and($cmp->collection_order)->not->toBeNull();
});

test('the collections page attaches a collection to pages and adds a member component', function () {
    [$owner, $site] = groupingSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Testimonials', 'type' => 'list', 'is_public' => true]);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/', 'keywords' => '', 'is_published' => true]);
    $free = Component::create(['site_id' => $site->id, 'name' => 'Testimonial X', 'author' => 'System', 'source' => 'app']);

    Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('openEdit', $col->id)
        ->set('pageIds', [$page->id])
        ->call('save');
    expect($col->pages()->count())->toBe(1);

    Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site])
        ->call('viewEntries', $col->id)
        ->call('addComponent', $free->id);

    expect($free->fresh()->collection_id)->toBe($col->id);
});
