<?php

use App\Livewire\CollectionDetailPage;
use App\Livewire\CollectionsPage;
use App\Livewire\ConnectReviewPage;
use App\Models\AccountMember;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Media;
use App\Models\Node;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\PageJsonGenerator;
use App\Support\CollectionQuery;
use App\Support\MediaValue;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** A site with an "Events" collection and an "Events Grid" block reading it. */
function cbqSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id, 'domain' => 'cbq-'.uniqid().'.test']);
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Events', 'slug' => 'events-'.uniqid(), 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'title', 'name' => 'title', 'type' => 'text'], ['key' => 'category', 'name' => 'category', 'type' => 'text'], ['key' => 'day', 'name' => 'day', 'type' => 'number']]]);
    $rows = [
        ['title' => 'Bible study', 'category' => 'Study', 'day' => '9'],
        ['title' => 'Wedding fair', 'category' => 'Weddings', 'day' => '10'],
        ['title' => 'Choir night', 'category' => 'Music', 'day' => '2'],
        ['title' => 'Wedding prep', 'category' => 'Weddings', 'day' => '30'],
        ['title' => 'Draft only', 'category' => 'Music', 'day' => '1'],
    ];
    $items = [];
    foreach ($rows as $i => $data) {
        $items[] = CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'data' => $data, 'position' => $i,
            'status' => $data['title'] === 'Draft only' ? 'draft' : 'published']);
        DB::table('collection_items')->where('id', end($items)->id)->update(['created_at' => now()->subDays(10 - $i)]);
    }
    $block = Component::create(['site_id' => $site->id, 'name' => 'Events Grid', 'author' => 'api', 'source' => 'api', 'collection_id' => $col->id]);
    Node::create(['component_id' => $block->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'Upcoming', 'order' => 0]);

    return [$user, $site, $col, $block, $items];
}

test('the query engine limits, sorts, searches and filters published entries', function () {
    [, , $col] = cbqSite();
    $titles = fn (array $q) => CollectionQuery::apply($col, $q)->map(fn ($i) => $i->data['title'])->all();

    expect($titles([]))->toBe(['Bible study', 'Wedding fair', 'Choir night', 'Wedding prep'])   // manual order, no drafts
        ->and($titles(['limit' => 2]))->toBe(['Bible study', 'Wedding fair'])
        ->and($titles(['sort' => 'newest', 'limit' => 3]))->toBe(['Wedding prep', 'Choir night', 'Wedding fair'])
        ->and($titles(['sort' => 'oldest', 'limit' => 1]))->toBe(['Bible study'])
        ->and($titles(['sort' => 'field', 'field' => 'day', 'dir' => 'asc']))->toBe(['Choir night', 'Bible study', 'Wedding fair', 'Wedding prep'])   // natural order: 2 < 9 < 10 < 30
        ->and($titles(['sort' => 'field', 'field' => 'title', 'dir' => 'desc', 'limit' => 2]))->toBe(['Wedding prep', 'Wedding fair'])
        ->and($titles(['search' => 'WEDDING']))->toBe(['Wedding fair', 'Wedding prep'])
        ->and($titles(['filter_field' => 'category', 'filter_value' => 'music']))->toBe(['Choir night'])
        ->and($titles(['search' => 'wedding', 'sort' => 'newest', 'limit' => 1]))->toBe(['Wedding prep']);

    // Unknown fields, silly limits and blank values normalise away.
    expect(CollectionQuery::normalize(['limit' => 999, 'sort' => 'field', 'field' => 'nope', 'search' => '  ', 'filter_field' => 'title', 'filter_value' => ''], ['title']))
        ->toBe(['limit' => 100]);
    expect(CollectionQuery::normalize(['limit' => '', 'sort' => 'manual'], ['title']))->toBe([]);
});

test('the block panel previews and saves which entries the block shows', function () {
    [$user, $site, $col, $block] = cbqSite();

    $page = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $block->id)
        ->assertSee('Source ↗')
        ->assertSeeHtml(route('collections.show', [$site->name, $col->id]).'?item=')
        ->assertSee('Items in this block')
        ->assertSet("edit.lists.{$col->id}.shown", 4);

    // Live preview while editing — before saving.
    $page->set("edit.queries.{$col->id}.search", 'wedding')
        ->set("edit.queries.{$col->id}.sort", 'newest')
        ->set("edit.queries.{$col->id}.limit", '1')
        ->assertSet("edit.lists.{$col->id}.shown", 1)
        ->assertSet('edit.collection.items.0.label', 'Wedding prep')
        ->assertSee('Showing 1 of 4');
    expect($block->fresh()->collection_queries)->toBeNull();

    $page->call('saveComponent');
    expect($block->fresh()->collectionQuery($col->id))->toEqualCanonicalizing(['limit' => 1, 'sort' => 'newest', 'search' => 'wedding']);

    // Clearing everything removes the saved query.
    $page->set("edit.queries.{$col->id}.search", '')->set("edit.queries.{$col->id}.sort", 'manual')->set("edit.queries.{$col->id}.limit", '')
        ->call('saveComponent');
    expect($block->fresh()->collection_queries)->toBeNull();
});

test('the content api sends each block its selection without trimming the full list', function () {
    [, $site, $col, $block, $items] = cbqSite();
    $block->update(['collection_queries' => [$col->id => ['limit' => 2, 'sort' => 'newest']]]);

    $res = $this->getJson('/api/sites/'.$site->name.'/collections')->assertOk();
    $c = collect($res->json('collections'))->firstWhere('id', $col->id);
    expect($c['items'])->toHaveCount(4)
        ->and($c['views']['events-grid']['ids'])->toBe([$items[3]->id, $items[2]->id])
        ->and($c['views']['events-grid']['block'])->toBe('Events Grid');

    // A block without a query adds nothing.
    $block->update(['collection_queries' => null]);
    $c = collect($this->getJson('/api/sites/'.$site->name.'/collections')->json('collections'))->firstWhere('id', $col->id);
    expect($c)->not->toHaveKey('views');
});

test('a collection field in a static export follows its block query, in manual order otherwise', function () {
    [, $site, $col, $block] = cbqSite();
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'api', 'source' => 'api',
        'collection_queries' => [$col->id => ['filter_field' => 'category', 'filter_value' => 'weddings']]]);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Highlights', 'type' => 'collection', 'value' => $col->id, 'order' => 0]);

    $gen = app(PageJsonGenerator::class);
    $m = new ReflectionMethod($gen, 'linkedItems');
    $m->setAccessible(true);

    expect(collect($m->invoke($gen, $site, $col->id))->pluck('title')->all())->toBe(['Bible study', 'Wedding fair', 'Choir night', 'Wedding prep'])
        ->and(collect($m->invoke($gen, $site, $col->id, $hero->collectionQuery($col->id)))->pluck('title')->all())->toBe(['Wedding fair', 'Wedding prep']);
});

test('collections?open=&item= opens that entry, and only for this site', function () {
    [$user, $site, $col, , $items] = cbqSite();
    [, $otherSite, $otherCol, , $otherItems] = cbqSite();

    Livewire::withQueryParams(['open' => $col->id, 'item' => $items[1]->id])->actingAs($user)
        ->test(CollectionsPage::class, ['site' => $site])
        ->assertSet('viewingId', $col->id)->assertSet('editingItemId', $items[1]->id);

    // Another site's collection/item is ignored.
    Livewire::withQueryParams(['open' => $otherCol->id, 'item' => $otherItems[1]->id])->actingAs($user)
        ->test(CollectionsPage::class, ['site' => $site])
        ->assertSet('viewingId', null);
});

test('a block can reorder and hide entries for itself without touching the collection', function () {
    [$user, $site, $col, $block, $items] = cbqSite();
    $ids = fn ($page) => collect($page->get("edit.lists.{$col->id}.items"))->pluck('id')->all();

    $page = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $block->id);
    expect($ids($page))->toBe([$items[0]->id, $items[1]->id, $items[2]->id, $items[3]->id]);

    $page->call('blockMove', $col->id, $items[2]->id, -1)      // Choir night before Wedding fair
        ->call('blockHide', $col->id, $items[0]->id);           // Bible study out of this block
    expect($ids($page))->toBe([$items[2]->id, $items[1]->id, $items[3]->id])
        ->and($page->get("edit.lists.{$col->id}.hidden.0.id"))->toBe($items[0]->id);
    $page->assertSee('custom order')->assertSee('1 hidden');

    $page->call('saveComponent');
    $q = $block->fresh()->collectionQuery($col->id);
    expect($q['exclude'])->toBe([$items[0]->id])
        ->and(array_slice($q['order'], 0, 3))->toBe([$items[2]->id, $items[1]->id, $items[3]->id]);

    // The collection itself is untouched: same items, same positions.
    expect($col->items()->where('status', 'published')->pluck('id')->all())->toBe([$items[0]->id, $items[1]->id, $items[2]->id, $items[3]->id]);

    // The API view follows the block's order; show again + reset restore it.
    $c = collect($this->getJson('/api/sites/'.$site->name.'/collections')->json('collections'))->firstWhere('id', $col->id);
    expect($c['views']['events-grid']['ids'])->toBe([$items[2]->id, $items[1]->id, $items[3]->id])->and($c['items'])->toHaveCount(4);

    $page->call('blockUnhide', $col->id, $items[0]->id)->call('blockResetOrder', $col->id)->call('saveComponent');
    expect($block->fresh()->collection_queries)->toBeNull();
});

test('the collection page views, edits, reorders, publishes, soft-deletes and restores entries', function () {
    [$user, $site, $col, $block, $items] = cbqSite();

    $this->actingAs($user)->get(route('collections.show', [$site->name, $col->id]))->assertOk()->assertSee('Events')->assertSee('Used by')->assertSee('Events Grid');

    // ?item opens the side panel in VIEW mode, with created/updated times.
    $page = Livewire::withQueryParams(['item' => $items[1]->id])->actingAs($user)
        ->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->assertSet('panelId', $items[1]->id)->assertSet('panelMode', 'view')->assertSet('editingItemId', null)
        ->assertSee('Wedding fair')->assertSee('Created '.$items[1]->fresh()->created_at->format('j M Y'));

    // Edit → save → back to view.
    $page->call('editItem')->assertSet('panelMode', 'edit')->assertSet('itemForm.title', 'Wedding fair')
        ->set('itemForm.title', 'Wedding <b>fair</b> 2026')->call('saveItem')
        ->assertSet('panelMode', 'view')->assertSet('panelId', $items[1]->id);
    expect($items[1]->fresh()->data['title'])->toBe('Wedding <b>fair</b> 2026');
    $page->assertSee('Wedding fair 2026')->assertDontSeeHtml('Wedding <b>fair</b>'); // view mode shows text, not HTML

    $page->call('moveItem', $items[3]->id, -1);
    expect($col->items()->pluck('id')->take(4)->all())->toBe([$items[0]->id, $items[1]->id, $items[3]->id, $items[2]->id]);

    $page->call('toggleStatus', $items[0]->id);
    expect($items[0]->fresh()->status)->toBe('draft');

    // A new entry goes last and opens in view mode after saving.
    $page->call('addEntry')->assertSet('panelId', '')->set('itemForm.title', 'New event')->call('saveItem');
    $new = $col->items()->get()->last();
    expect($new->data['title'])->toBe('New event')->and($new->position)->toBeGreaterThan($items[3]->fresh()->position);
    $page->assertSet('panelId', $new->id)->assertSet('panelMode', 'view');

    // Delete is soft: gone everywhere, recorded, restorable.
    $before = $items[2]->fresh()->position;
    $page->call('deleteItem', $items[2]->id)->assertSee('Deleted entries');
    expect(CollectionItem::find($items[2]->id))->toBeNull()
        ->and(CollectionItem::withTrashed()->find($items[2]->id)->deleted_at)->not->toBeNull()
        ->and(collect($this->getJson('/api/sites/'.$site->name.'/collections')->json('collections'))->firstWhere('id', $col->id)['items'])
        ->each(fn ($i) => $i->id->not->toBe($items[2]->id));

    $page->call('restoreItem', $items[2]->id);
    expect(CollectionItem::find($items[2]->id))->not->toBeNull()->and($items[2]->fresh()->position)->toBe($before);

    // Another site's collection 404s.
    [, $other, $otherCol] = cbqSite();
    $this->actingAs($user)->get(route('collections.show', [$site->name, $otherCol->id]))->assertNotFound();
});

test('a viewer can open the collection page but not change it', function () {
    [$owner, $site, $col, , $items] = cbqSite();
    $viewer = User::factory()->create();
    $role = Role::create(['account_id' => $owner->id, 'name' => 'Viewer', 'slug' => 'viewer-'.uniqid(), 'permissions' => ['collections.view'], 'is_system' => false]);
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $viewer->id, 'role_id' => $role->id, 'site_id' => $site->id]);

    $this->actingAs($viewer)->get(route('collections.show', [$site->name, $col->id]))->assertOk()->assertDontSee('Add entry');
    Livewire::actingAs($viewer)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('deleteItem', $items[0]->id)->assertForbidden();
    expect(CollectionItem::find($items[0]->id))->not->toBeNull();
});

test('the edit page opens a block from ?component=', function () {
    [$user, $site, , $block] = cbqSite();
    Livewire::withQueryParams(['component' => $block->id])->actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->assertSet('edit.id', $block->id)->assertSet('edit.type', 'component');
});

test('dragging rows reorders the block, keeping rows hidden by a limit in place', function () {
    [$user, $site, $col, $block, $items] = cbqSite();
    $ids = fn ($page) => collect($page->get("edit.lists.{$col->id}.items"))->pluck('id')->all();

    $page = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $block->id)
        ->set("edit.queries.{$col->id}.sort", 'newest')        // shown: 3, 2, 1, 0
        ->set("edit.queries.{$col->id}.limit", '3');           // shown: 3, 2, 1  (0 beyond the limit)
    expect($ids($page))->toBe([$items[3]->id, $items[2]->id, $items[1]->id]);

    // Drag the last visible row to the top.
    $page->call('blockReorder', $col->id, [$items[1]->id, $items[3]->id, $items[2]->id]);
    expect($ids($page))->toBe([$items[1]->id, $items[3]->id, $items[2]->id])
        ->and($page->get("edit.queries.{$col->id}.sort"))->toBe('manual')
        ->and($page->get("edit.queries.{$col->id}.order"))->toBe([$items[1]->id, $items[3]->id, $items[2]->id, $items[0]->id]);

    // Stale or foreign ids are ignored.
    $page->call('blockReorder', $col->id, ['nope', $items[2]->id]);
    expect($ids($page))->toBe([$items[1]->id, $items[3]->id, $items[2]->id]);

    $page->call('saveComponent');
    $c = collect($this->getJson('/api/sites/'.$site->name.'/collections')->json('collections'))->firstWhere('id', $col->id);
    expect($c['views']['events-grid']['ids'])->toBe([$items[1]->id, $items[3]->id, $items[2]->id]);
});

test('field types are set on the collection page and entries edit and save as those types', function () {
    [$user, $site, $col, , $items] = cbqSite();
    $page = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id]);

    $page->call('openFields')->assertSet('editingFields', true)
        ->assertSet('fieldRows.0.key', 'title')
        // title → textarea, category → radio (needs options), day → slider, + new fields
        ->set('fieldRows.0.type', 'textarea')
        ->set('fieldRows.1.type', 'radio')->set('fieldRows.1.options', 'Study')
        ->set('fieldRows.2.type', 'slider')->set('fieldRows.2.min', '1')->set('fieldRows.2.max', '31')
        ->call('addFieldRow')->set('fieldRows.3.label', 'Rating')->set('fieldRows.3.type', 'number')->set('fieldRows.3.step', '0.1')
        ->call('addFieldRow')->set('fieldRows.4.label', 'Featured')->set('fieldRows.4.type', 'checkbox')
        ->call('addFieldRow')->set('fieldRows.5.label', 'Contact email')->set('fieldRows.5.type', 'email')
        ->call('saveFields')->assertHasErrors('fieldRows.1.options')   // a radio needs 2+ options
        ->set('fieldRows.1.options', 'Study, Weddings, Music')
        ->call('saveFields')->assertHasNoErrors()->assertSet('editingFields', false);

    $f = collect($col->fresh()->fields)->keyBy('key');
    expect($f['title']['type'])->toBe('textarea')
        ->and($f['category']['options'])->toBe(['Study', 'Weddings', 'Music'])
        ->and([$f['day']['type'], $f['day']['min'], $f['day']['max'], $f['day']['step']])->toBe(['slider', 1, 31, 1])
        ->and([$f['rating']['type'], $f['rating']['step']])->toBe(['number', 0.1])
        ->and($f['featured']['type'])->toBe('checkbox')
        ->and($f['contact_email']['type'])->toBe('email');

    // The entry form renders each type; values save typed.
    $page->call('editItem', $items[0]->id)
        ->assertSeeHtml('type="radio"')->assertSeeHtml('type="range"')->assertSeeHtml('step="0.1"')
        ->assertSeeHtml('type="checkbox"')->assertSeeHtml('type="email"')
        ->set('itemForm.rating', 'four')->call('saveItem')->assertHasErrors('itemForm.rating')
        ->set('itemForm.rating', '4.5')->set('itemForm.contact_email', 'nope')->call('saveItem')->assertHasErrors('itemForm.contact_email')
        ->set('itemForm.contact_email', 'hi@example.com')->set('itemForm.featured', true)->set('itemForm.day', '12')
        ->call('saveItem')->assertHasNoErrors()->assertSet('panelMode', 'view');

    $d = $items[0]->fresh()->data;
    expect($d['rating'])->toBe(4.5)->and($d['featured'])->toBeTrue()->and($d['day'])->toBe(12)->and($d['contact_email'])->toBe('hi@example.com');
    $page->assertSee('Yes'); // checkbox in view mode

    // Removing a field keeps its stored values.
    $page->call('openFields')->call('removeFieldRow', 5)->call('saveFields');
    expect(collect($col->fresh()->fields)->pluck('key'))->not->toContain('contact_email')
        ->and($items[0]->fresh()->data['contact_email'])->toBe('hi@example.com');
});

test('media values are detected and previewed in any field', function () {
    $siteId = Site::factory()->create(['user_id' => User::factory()->create()->id, 'domain' => 'mv-'.uniqid().'.test'])->id;
    $m = Media::create(['site_id' => $siteId, 'name' => 'hero.mp4', 'file_type' => 'video', 'url' => '/storage/media/x/hero.mp4']);

    expect(MediaValue::detect('/storage/media/x/team.JPG'))->toMatchArray(['kind' => 'image', 'name' => 'team.JPG'])
        ->and(MediaValue::detect('https://cdn.example.com/a/clip.webm?x=1')['kind'])->toBe('video')
        ->and(MediaValue::detect('/assets/song.mp3')['kind'])->toBe('audio')
        ->and(MediaValue::detect('/docs/menu.pdf')['kind'])->toBe('document')
        ->and(MediaValue::detect('@media/hero.mp4', $siteId))->toMatchArray(['kind' => 'video', 'url' => $m->url])
        ->and(MediaValue::detect('Our wedding was perfect.'))->toBeNull()
        ->and(MediaValue::detect('image.png'))->toBeNull()      // bare words aren't paths
        ->and(MediaValue::detect('/about-us'))->toBeNull();

    // A plain text field holding an image path gets the picker + preview, in edit and view mode.
    [$user, $site, $col, , $items] = cbqSite();
    $items[0]->update(['data' => ['title' => 'Bible study', 'category' => '/storage/media/x/cover.png', 'day' => '9']]);
    Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('viewItem', $items[0]->id)->assertSeeHtml('src="/storage/media/x/cover.png"')
        ->call('editItem')->assertSeeHtml('placeholder="Pick from Assets, or paste a path / URL"')->assertSeeHtml('src="/storage/media/x/cover.png"');
});

test('nested media: template paths resolve to the asset library, lists edit as a gallery, nested leaves get pickers', function () {
    [$user, $site, $col, , $items] = cbqSite();
    // The install copied the template's /assets/... images into the Assets library.
    Media::create(['site_id' => $site->id, 'name' => 'port1.png', 'file_type' => 'image', 'url' => '/storage/media/'.$site->name.'/port1.png']);
    Media::create(['site_id' => $site->id, 'name' => 'port2.png', 'file_type' => 'image', 'url' => '/storage/media/'.$site->name.'/port2.png']);
    $col->update(['fields' => array_merge($col->fields, [['key' => 'images', 'type' => 'list', 'label' => 'Images'], ['key' => 'facts', 'type' => 'list', 'label' => 'Facts']])]);
    $items[0]->update(['data' => array_merge($items[0]->data, [
        'images' => ['/assets/images/portfolio/port1.png', '/assets/images/portfolio/port2.png'],
        'facts' => [['label' => 'Logo', 'value' => '/assets/images/portfolio/port1.png'], ['label' => 'Seats', 'value' => 120]],
    ])]);

    expect(MediaValue::detect('/assets/images/portfolio/port1.png', $site->id)['url'])->toBe('/storage/media/'.$site->name.'/port1.png')
        ->and(MediaValue::detect('/assets/images/other.png', $site->id)['url'])->toBe('/assets/images/other.png')   // not in the library → as is
        ->and(MediaValue::kind($items[0]->fresh()->data['images'], $site->id))->toBe('media-list')
        ->and(MediaValue::kind($items[0]->fresh()->data['facts'], $site->id))->toBe('rows')
        ->and(MediaValue::kind(120))->toBe('number');

    $page = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('viewItem', $items[0]->id)
        // View: gallery with library images, nested row media previewed too.
        ->assertSeeHtml('src="/storage/media/'.$site->name.'/port2.png"')->assertSee('Seats');

    // Edit: the list is a gallery (add from Assets, remove), nested leaves pick their own input.
    $page->call('editItem')
        ->assertSee('Add from Assets')
        ->assertSeeHtml("path: 'itemForm.facts.0.value'")      // media leaf inside a row → picker
        ->assertSeeHtml('type="number" step="any" wire:model.blur="itemForm.facts.1.value"');

    // Picking media appends to the gallery / sets a nested leaf; paths outside the form are refused.
    $page->dispatch('media-picked', context: ['scope' => 'nested-media', 'path' => 'itemForm.images', 'append' => true], mediaRef: '@media/x.png', url: '/storage/media/x.png')
        ->assertSet('itemForm.images.2', '/storage/media/x.png')
        ->dispatch('media-picked', context: ['scope' => 'nested-media', 'path' => 'itemForm.facts.0.value'], mediaRef: '@media/y.png', url: '/storage/media/y.png')
        ->assertSet('itemForm.facts.0.value', '/storage/media/y.png')
        ->call('nestedRemove', 'itemForm.images', 0)
        ->call('saveItem')->assertHasNoErrors();
    expect($items[0]->fresh()->data['images'])->toBe(['/assets/images/portfolio/port2.png', '/storage/media/x.png'])
        ->and($items[0]->fresh()->data['facts'][0]['value'])->toBe('/storage/media/y.png');

    $page->dispatch('media-picked', context: ['scope' => 'nested-media', 'path' => 'viewingId'], mediaRef: '', url: '/x.png')->assertForbidden();
});

test('several media paths in one text value show and edit as a gallery, and save back as lines', function () {
    [$user, $site, $col, , $items] = cbqSite();
    Media::create(['site_id' => $site->id, 'name' => 'port1.png', 'file_type' => 'image', 'url' => '/storage/media/'.$site->name.'/port1.png']);
    $col->update(['fields' => array_merge($col->fields, [['key' => 'images', 'type' => 'textarea', 'label' => 'Images']])]);
    $items[0]->update(['data' => array_merge($items[0]->data, ['images' => "/assets/images/portfolio/port1.png\n/assets/images/portfolio/port2.png\n/assets/images/portfolio/port3.webp"])]);

    expect(MediaValue::lines("/a/1.png\n/a/2.png"))->toBe(['/a/1.png', '/a/2.png'])
        ->and(MediaValue::lines("Hello\n/a/2.png"))->toBeNull()
        ->and(MediaValue::kind("/a/1.png\n/a/2.png"))->toBe('media-lines');

    $page = Livewire::actingAs($user)->test(CollectionDetailPage::class, ['site' => $site, 'collection' => $col->id])
        ->call('viewItem', $items[0]->id)
        ->assertSeeHtml('src="/storage/media/'.$site->name.'/port1.png"');   // library copy

    $page->call('editItem')
        ->assertSet('itemForm.images', ['/assets/images/portfolio/port1.png', '/assets/images/portfolio/port2.png', '/assets/images/portfolio/port3.webp'])
        ->assertSee('Add from Assets')
        ->call('nestedRemove', 'itemForm.images', 1)
        ->dispatch('media-picked', context: ['scope' => 'nested-media', 'path' => 'itemForm.images', 'append' => true], mediaRef: '@media/n.png', url: '/storage/media/n.png')
        ->call('saveItem')->assertHasNoErrors();

    expect($items[0]->fresh()->data['images'])->toBe("/assets/images/portfolio/port1.png\n/assets/images/portfolio/port3.webp\n/storage/media/n.png");
});

test('the rich text field ignores unrelated variables it inherits from the page', function () {
    // The Edit page loops `… as $group => $rows` (dynamic pages) before including fields;
    // Blade includes inherit that array — the editor must not echo it as its row count.
    $html = view('livewire.partials.rich-text', ['path' => 'edit.nodes.0.value', 'value' => 'Hello', 'rows' => ['a' => [1, 2]], 'blocks' => 'yes'])->render();
    expect($html)->toContain('rows="4"')->not->toContain('font-mono');

    $html = view('livewire.partials.rich-text', ['path' => 'p', 'value' => ['not', 'text'], 'rows' => 6])->render();
    expect($html)->toContain('rows="6"');
});
