<?php

use App\Livewire\CollectionsPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

test('the collections page counts entries, review queue and where each collection is used — and filters by it', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'rails-'.uniqid().'.test']);
    $mk = fn ($name, $extra = []) => Collection::create(['site_id' => $site->id, 'name' => $name, 'slug' => '', 'type' => 'grid', 'fields' => [['key' => 'title', 'type' => 'text']], 'is_public' => true] + $extra);

    $team = $mk('Team');            // data source of a block
    $words = $mk('Hero Words');     // shown through a block's collection field
    $faqs = $mk('Faqs', ['allow_submit' => true, 'auto_publish' => true]);   // not linked, has a pending submission
    $empty = $mk('Empty List');     // no entries, not linked
    foreach ([[$team, 'published'], [$team, 'published'], [$team, 'published'], [$words, 'published'], [$faqs, 'published'], [$faqs, 'pending']] as [$c, $status]) {
        CollectionItem::create(['collection_id' => $c->id, 'site_id' => $site->id, 'data' => ['title' => 'x'], 'status' => $status]);
    }
    Component::create(['site_id' => $site->id, 'name' => 'Team Grid', 'author' => 'api', 'source' => 'api', 'collection_id' => $team->id]);
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'api', 'source' => 'api']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Words', 'type' => 'collection', 'value' => $words->id, 'order' => 0]);

    $page = Livewire::actingAs($user)->test(CollectionsPage::class, ['site' => $site])
        ->assertViewHas('stats', fn ($s) => $s['entries'] === 5 && $s['pending'] === 1 && $s['empty'] === 1
            && $s['linked'] === 2 && $s['unlinked'] === 2 && $s['autoPublish'] === 1)
        ->assertSee('Needs attention')->assertSee('1 submission to review')->assertSee('Largest collections');

    $names = fn () => $page->viewData('collections')->pluck('name')->sort()->values()->all();
    $page->call('setFilter', 'linked');
    expect($names())->toBe(['Hero Words', 'Team']);
    $page->call('setFilter', 'unlinked');
    expect($names())->toBe(['Empty List', 'Faqs']);
    $page->call('setFilter', 'empty');
    expect($names())->toBe(['Empty List']);
    $page->call('setFilter', 'pending');
    expect($names())->toBe(['Faqs']);

    $page->call('setFilter', 'all')->set('sort', 'entries');
    expect($page->viewData('collections')->first()->name)->toBe('Team');
    $page->call('setFilter', 'bogus')->assertSet('filter', 'all');
});
