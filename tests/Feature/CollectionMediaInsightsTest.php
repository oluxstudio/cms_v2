<?php

use App\Livewire\CollectionsPage;
use App\Models\Collection;
use App\Models\CollectionItemEvent;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function mediaSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'med-'.uniqid(), 'domain' => 'med-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Media '.uniqid(), 'type' => 'list', 'is_public' => true]);
    $a = $col->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Sunday recap', 'type' => 'video']]);
    $b = $col->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Choir set', 'type' => 'audio']]);

    return [$owner, $site, $col, $a, $b];
}

test('the beacon records views and plays per item and no-ops silently on misses', function () {
    [, $site, $col, $a, $b] = mediaSite();

    $this->postJson("/api/sites/{$site->name}/collections/{$col->id}/items/{$a->id}/event", ['event' => 'view'])->assertNoContent();
    $this->postJson("/api/sites/{$site->name}/collections/{$col->id}/items/{$a->id}/event", ['event' => 'play', 'session' => 's1'])->assertNoContent();
    $this->postJson("/api/sites/{$site->name}/collections/{$col->id}/items/{$b->id}/event", ['event' => 'view'])->assertNoContent();
    expect(CollectionItemEvent::where('site_id', $site->id)->count())->toBe(3)
        ->and(CollectionItemEvent::where('collection_item_id', $a->id)->where('event', 'play')->count())->toBe(1);

    // Unknown item / unpublished item / foreign site: 204, nothing stored.
    $this->postJson("/api/sites/{$site->name}/collections/{$col->id}/items/nope/event", ['event' => 'view'])->assertNoContent();
    $pending = $col->items()->create(['site_id' => $site->id, 'status' => 'pending', 'data' => ['title' => 'Draft']]);
    $this->postJson("/api/sites/{$site->name}/collections/{$col->id}/items/{$pending->id}/event", ['event' => 'view'])->assertNoContent();
    expect(CollectionItemEvent::where('site_id', $site->id)->count())->toBe(3);

    // Bad event names are rejected.
    $this->postJson("/api/sites/{$site->name}/collections/{$col->id}/items/{$a->id}/event", ['event' => 'download'])->assertStatus(422);
});

test('the collections page ranks items by engagement for the owner', function () {
    [$owner, $site, $col, $a, $b] = mediaSite();
    foreach (range(1, 3) as $i) {
        CollectionItemEvent::create(['site_id' => $site->id, 'collection_id' => $col->id, 'collection_item_id' => $a->id, 'event' => 'view', 'created_at' => now()]);
    }
    CollectionItemEvent::create(['site_id' => $site->id, 'collection_id' => $col->id, 'collection_item_id' => $a->id, 'event' => 'play', 'created_at' => now()]);
    CollectionItemEvent::create(['site_id' => $site->id, 'collection_id' => $col->id, 'collection_item_id' => $b->id, 'event' => 'view', 'created_at' => now()]);

    $page = Livewire::actingAs($owner)->test(CollectionsPage::class, ['site' => $site]);
    $page->call('toggleInsights', $col->id);
    $ins = $page->get('mediaInsights');
    expect($ins['views'])->toBe(4)->and($ins['plays'])->toBe(1)->and($ins['rate'])->toBe(25)
        ->and(array_key_first($ins['top_viewed']))->toBe('Sunday recap')
        ->and($ins['top_viewed']['Sunday recap'])->toBe(3);
    $page->assertSee('engagement, last 30 days');
});

test('public collection payload items carry their ids for beacons', function () {
    [, $site, $col, $a] = mediaSite();
    $this->getJson("/api/sites/{$site->name}/collections")->assertOk()
        ->assertJsonPath('collections.0.items.0.id', $a->id);
});
