<?php

use App\Livewire\ConnectReviewPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Site;
use App\Models\User;
use App\Support\CollectionQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/** Events: Bible study · Wedding fair · Choir night · Wedding prep (published) + a Music draft; an "Events Grid" block reads them. */
function ruleSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'rule-'.uniqid().'.test']);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Events', 'slug' => 'events-'.uniqid(), 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'title', 'name' => 'title', 'type' => 'text'], ['key' => 'category', 'name' => 'category', 'type' => 'text'], ['key' => 'day', 'name' => 'day', 'type' => 'number']]]);
    $items = [];
    foreach ([['Bible study', 'Study', '9'], ['Wedding fair', 'Weddings', '10'], ['Choir night', 'Music', '2'], ['Wedding prep', 'Weddings', '30'], ['Draft only', 'Music', '1']] as $i => [$t, $c, $d]) {
        $items[] = CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'position' => $i,
            'data' => ['title' => $t, 'category' => $c, 'day' => $d], 'status' => $t === 'Draft only' ? 'draft' : 'published']);
    }
    $block = Component::create(['site_id' => $site->id, 'name' => 'Events Grid', 'author' => 'api', 'source' => 'api', 'collection_id' => $col->id]);

    return [$user, $site, $col, $block, $items];
}

test('select() takes the first, last or a random N of the entries matching a rule', function () {
    [, , $col, , $items] = ruleSite();
    $title = fn (array $ids) => collect($ids)->map(fn ($id) => collect($items)->firstWhere('id', $id)->data['title'])->all();

    expect($title(CollectionQuery::select($col, ['limit' => 3])))->toBe(['Bible study', 'Wedding fair', 'Choir night'])
        ->and($title(CollectionQuery::select($col, ['take' => 'last', 'limit' => 2])))->toBe(['Choir night', 'Wedding prep'])
        ->and($title(CollectionQuery::select($col, ['filter_field' => 'category', 'filter_op' => 'is', 'filter_value' => 'weddings', 'take' => 'last', 'limit' => 1])))->toBe(['Wedding prep'])
        ->and($title(CollectionQuery::select($col, ['search' => 'wedding', 'sort' => 'field', 'field' => 'day', 'dir' => 'desc'])))->toBe(['Wedding prep', 'Wedding fair']);

    // Random: N distinct matching entries, kept in the collection's order.
    $random = CollectionQuery::select($col, ['take' => 'random', 'limit' => 2]);
    $all = CollectionQuery::select($col, []);
    expect($random)->toHaveCount(2)
        ->and(array_values(array_intersect($all, $random)))->toBe($random);

    // A rule never sees drafts, and ignores block state (pick/order/exclude).
    expect(CollectionQuery::select($col, ['filter_field' => 'category', 'filter_value' => 'music', 'pick' => [], 'exclude' => [$items[2]->id]]))->toBe([(string) $items[2]->id]);
});

test('a rule fills the block list as its own sortable list — replace or add — and saves', function () {
    [$user, $site, $col, $block, $items] = ruleSite();
    $id = (string) $col->id;

    $page = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $block->id)
        ->assertSee('Add entries by rule')
        ->set("edit.fills.$id.filter_field", 'category')
        ->set("edit.fills.$id.filter_value", 'Weddings')
        ->set("edit.fills.$id.take", 'first')
        ->set("edit.fills.$id.limit", '1')
        ->assertSee('2 entries match')
        ->call('blockFill', $id, 'replace');

    expect($page->get("edit.queries.$id.pick"))->toBe([(string) $items[1]->id]);   // Wedding fair

    // Add the last Music/Study entry on top, then reorder by drag.
    $page->set("edit.fills.$id.filter_value", 'Study')->call('blockFill', $id, 'append');
    expect($page->get("edit.queries.$id.pick"))->toBe([(string) $items[1]->id, (string) $items[0]->id]);
    $page->call('blockReorder', $id, [(string) $items[0]->id, (string) $items[1]->id])
        ->call('saveComponent');

    expect($block->fresh()->collectionQuery($id)['pick'])->toBe([(string) $items[0]->id, (string) $items[1]->id])
        // …and the block's view (what the template renders) is exactly that list.
        ->and($col->fresh()->blockViews()['events-grid']['ids'])->toBe([(string) $items[0]->id, (string) $items[1]->id]);

    // A rule matching nothing changes nothing (the builder resets after a save).
    $page->set("edit.fills.$id.filter_field", 'category')->set("edit.fills.$id.filter_value", 'Nope')->call('blockFill', $id, 'replace')->assertDispatched('toast');
    expect($page->get("edit.queries.$id.pick"))->toBe([(string) $items[0]->id, (string) $items[1]->id]);
});

test('the live block selection can show the last or a random few', function () {
    [, , $col] = ruleSite();

    expect(CollectionQuery::apply($col, ['take' => 'last', 'limit' => 1])->map(fn ($i) => $i->data['title'])->all())->toBe(['Wedding prep'])
        ->and(CollectionQuery::apply($col, ['take' => 'random', 'limit' => 3]))->toHaveCount(3)
        ->and(CollectionQuery::normalize(['take' => 'sideways', 'limit' => 2], []))->toBe(['limit' => 2]);
});
