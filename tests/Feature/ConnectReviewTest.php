<?php

use App\Livewire\ConnectReviewPage;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\ContentVersion;
use App\Models\Form;
use App\Models\Media;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteConnect\PageJsonGenerator;
use Livewire\Livewire;

function previewSite(): array
{
    $user = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $user->id]);

    // Content created directly (as via the API) — not attached to any page.
    $hero = Component::create(['site_id' => $site->id, 'name' => 'Hero', 'author' => 'api', 'source' => 'api']);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Heading', 'type' => 'text', 'value' => 'Original', 'order' => 0]);

    $services = Collection::create(['site_id' => $site->id, 'name' => 'Services', 'slug' => 'services', 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'text']]]);
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Cut']]);

    $form = Form::create(['site_id' => $site->id, 'name' => 'contact', 'title' => 'Contact', 'is_active' => true,
        'fields' => [['key' => 'email', 'name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true]]]);

    return [$user, $site, $hero, $services, $form];
}

test('the connect page renders for the site owner', function () {
    [$user, $site] = previewSite();

    $this->actingAs($user)->get("/{$site->name}/connect")
        ->assertOk()
        ->assertSee('Edit mode');
});

test('setting the client URL builds the edit-mode embed url', function () {
    [$user, $site] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->set('urlInput', 'http://localhost:3000')
        ->call('saveClientUrl')
        ->assertSet('clientUrl', 'http://localhost:3000')
        ->assertSee('http://localhost:3000?olx-edit=1', false);

    expect($site->fresh()->getAttr('client_url'))->toBe('http://localhost:3000');
});

test('the page dropdown navigates the preview and closes the editor', function () {
    [$user, $site, $hero] = previewSite();
    Page::factory()->create(['site_id' => $site->id, 'name' => 'About', 'url' => '/about']);
    $site->setAttr('client_url', 'http://localhost:3000');

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site->fresh()])
        ->call('select', 'component', $hero->id)
        ->set('previewPath', '/about')
        ->assertSet('selectedId', null)
        ->assertSee('http://localhost:3000/about?olx-edit=1', false);
});

test('the click bridge selects a component by id and loads it for edit', function () {
    [$user, $site, $hero] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', $hero->id, null, 'component')
        ->assertSet('selectedId', $hero->id)
        ->assertSet('mode', 'edit')
        ->assertSet('edit.type', 'component');
});

test('the click bridge resolves a hand-authored data-olx-key to the component', function () {
    [$user, $site, $hero] = previewSite();

    // Client markup used data-olx-key="hero" (camelCase of the component name).
    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'hero', 'component')
        ->assertSet('selectedId', $hero->id);
});

test('editing a component persists and refreshes the preview', function () {
    [$user, $site, $hero] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->set('edit.nodes.0.value', 'Edited from preview')
        ->call('saveComponent')
        ->assertDispatched('toast');

    expect($hero->nodes()->first()->fresh()->value)->toBe('Edited from preview');
});

test('a collection can gain and edit items inline', function () {
    [$user, $site, , $services] = previewSite();
    $start = $services->items()->count();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $services->id)
        ->call('addItem')
        ->set('edit.items.'.$start.'.data.title', 'Colour')
        ->call('saveCollection');

    expect($services->items()->count())->toBe($start + 1)
        ->and($services->items()->get()->pluck('data.title'))->toContain('Colour');
});

test('a form gains a field and an endpoint override', function () {
    [$user, $site, , , $form] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'form', $form->id)
        ->call('addFormField')
        ->set('edit.fields.1.label', 'Phone')
        ->set('edit.endpoint', 'https://hooks.example.com/x')
        ->call('saveForm');

    $form->refresh();
    expect($form->fields)->toHaveCount(2)
        ->and($form->fields[1]['key'])->toBe('phone')
        ->and($form->delivery['external_action'])->toBe('https://hooks.example.com/x');
});

test('a blank-endpoint form submit creates a form response', function () {
    [$user, $site, , , $form] = previewSite();

    $this->postJson("/api/sites/{$site->name}/form/{$form->name}", ['email' => 'jo@example.com', 'website' => ''])
        ->assertSuccessful();

    expect($form->responses()->count())->toBe(1);
});

test('an inline edit from the preview updates a component node by field key', function () {
    [$user, $site, $hero] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineFieldEdit', $hero->id, null, 'component', 'heading', 'Typed in the preview')
        ->assertDispatched('toast');

    expect($hero->nodes()->where('label', 'Heading')->first()->value)->toBe('Typed in the preview');
});

test('an inline edit resolves a dotted field key through nested nodes', function () {
    [$user, $site, $hero] = previewSite();
    $cta = Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Cta', 'type' => 'text', 'value' => '', 'order' => 1]);
    $label = Node::create(['component_id' => $hero->id, 'parent' => $cta->id, 'label' => 'Label', 'type' => 'text', 'value' => 'Book', 'order' => 0]);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineFieldEdit', null, 'hero', 'component', 'cta.label', 'Reserve now');

    expect($label->fresh()->value)->toBe('Reserve now');
});

test('an inline edit updates a collection item, site-scoped', function () {
    [$user, $site, , $services] = previewSite();
    $item = $services->items()->first();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineFieldEdit', $services->id, null, 'collection', 'title', 'Restyle', $item->id);
    expect($item->fresh()->data['title'])->toBe('Restyle');

    // An item belonging to another site must not be reachable.
    $other = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $foreignCol = Collection::create(['site_id' => $other->id, 'name' => 'X', 'slug' => 'x', 'type' => 'grid',
        'fields' => [['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'text']]]);
    $foreign = CollectionItem::create(['collection_id' => $foreignCol->id, 'site_id' => $other->id, 'status' => 'published', 'data' => ['title' => 'Keep']]);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineFieldEdit', $services->id, null, 'collection', 'title', 'Hacked', $foreign->id);
    expect($foreign->fresh()->data['title'])->toBe('Keep');
});

test('preview + / ✕ controls add and remove collection items', function () {
    [$user, $site, , $services] = previewSite();
    $item = $services->items()->first();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineItemAdd', null, 'services')
        ->call('inlineItemRemove', $services->id, null, $item->id);

    $titles = $services->items()->get();
    expect($titles)->toHaveCount(1)
        ->and($titles->first()->data['title'])->toBe('')
        ->and($titles->first()->status)->toBe('published');
});

test('the inspector can add and remove component fields', function () {
    [$user, $site, $hero] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->call('addNode')
        ->set('edit.nodes.1.label', 'Tagline')
        ->set('edit.nodes.1.value', 'Walk in, shine out')
        ->call('removeNode', 0) // remove the original Heading
        ->call('saveComponent');

    $nodes = $hero->nodes()->get();
    expect($nodes)->toHaveCount(1)
        ->and($nodes->first()->label)->toBe('Tagline')
        ->and($nodes->first()->value)->toBe('Walk in, shine out');
});

test('inline mutations are forbidden without components.manage', function () {
    [, $site, $hero, $services] = previewSite();
    $viewer = User::factory()->create();
    $site->members()->attach($viewer->id, ['role' => 'viewer']);
    $item = $services->items()->first();

    Livewire::actingAs($viewer)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineFieldEdit', $hero->id, null, 'component', 'heading', 'nope')
        ->assertForbidden();
    Livewire::actingAs($viewer)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineItemRemove', $services->id, null, $item->id)
        ->assertForbidden();

    expect($hero->nodes()->first()->value)->toBe('Original')
        ->and($services->items()->count())->toBe(1);
});

test('the preview API returns the site content with ids, tenant-scoped', function () {
    [$user, $site, $hero, $services, $form] = previewSite();

    $this->getJson(route('api.site.preview', ['siteName' => $site->name]))
        ->assertOk()
        ->assertJsonPath('components.0.id', $hero->id)
        ->assertJsonPath('collections.0.id', $services->id)
        ->assertJsonPath('forms.0.id', $form->id);

    $other = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $this->getJson(route('api.site.preview', ['siteName' => $other->name]))
        ->assertOk()
        ->assertJsonCount(0, 'components');
});

// ── Marker-first registration (client markup → CMS content) ─────────────

test('an unmatched component marker creates the component with typed nodes, attached to the page', function () {
    [$user, $site] = previewSite();
    $page = Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('registerMarkers', [[
            'kind' => 'component', 'key' => 'promoBanner',
            'fields' => [
                ['field' => 'heading', 'type' => 'text', 'value' => 'Summer sale'],
                ['field' => 'image', 'type' => 'image', 'value' => '/img/promo.jpg'],
                ['field' => 'cta.href', 'type' => 'url', 'value' => '/sale'],
            ],
        ]])
        ->assertDispatched('toast');

    $promo = $site->contentComponents()->where('name', 'Promo Banner')->first();
    expect($promo)->not->toBeNull()
        ->and($promo->nodes()->where('label', 'Heading')->value('value'))->toBe('Summer sale')
        ->and($promo->nodes()->where('label', 'Image')->value('type'))->toBe('image')
        ->and($page->components()->whereKey($promo->id)->exists())->toBeTrue();

    // The nested cta.href round-trips through the generator's field keys.
    $doc = app(PageJsonGenerator::class)->generate($page->fresh());
    $rec = collect($doc['componentData'])->firstWhere('key', 'promoBanner');
    expect($rec['fields']['cta']['href'])->toBe('/sale');
});

test('a matched component gains only its missing fields as new nodes', function () {
    [$user, $site, $hero] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('registerMarkers', [[
            'kind' => 'component', 'key' => 'hero',
            'fields' => [
                ['field' => 'heading', 'type' => 'text', 'value' => 'MUST NOT OVERWRITE'],
                ['field' => 'tagline', 'type' => 'text', 'value' => 'Walk in, shine out'],
            ],
        ]]);

    expect($hero->nodes()->where('label', 'Heading')->value('value'))->toBe('Original')
        ->and($hero->nodes()->where('label', 'Tagline')->value('value'))->toBe('Walk in, shine out');
});

test('registering the same key twice creates nothing new', function () {
    [$user, $site] = previewSite();
    $marker = ['kind' => 'component', 'key' => 'promoBanner',
        'fields' => [['field' => 'heading', 'type' => 'text', 'value' => 'x']]];

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site]);
    $lw->call('registerMarkers', [$marker]);
    $lw->call('registerMarkers', [$marker]);

    expect($site->contentComponents()->where('name', 'Promo Banner')->count())->toBe(1);
});

test('unmatched collection and form markers create their models', function () {
    [$user, $site] = previewSite();
    Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('registerMarkers', [
            ['kind' => 'collection', 'key' => 'teamMembers',
                'schema' => ['name', 'role'], 'item' => ['name' => 'Ada', 'role' => 'Stylist']],
            ['kind' => 'form', 'key' => 'quote',
                'fields' => [['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true]]],
        ]);

    $col = Collection::where('site_id', $site->id)->where('slug', 'teammembers')->first();
    expect($col)->not->toBeNull()
        ->and($col->items()->first()->data['name'])->toBe('Ada')
        ->and(Form::where('site_id', $site->id)->where('name', 'quote')->first()->fields[0]['key'])->toBe('email');
});

test('marker registration rejects junk keys and is permission-gated', function () {
    [$user, $site] = previewSite();
    $before = $site->contentComponents()->count();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('registerMarkers', [
            ['kind' => 'component', 'key' => '<script>x</script>', 'fields' => []],
            ['kind' => 'component', 'key' => str_repeat('k', 61), 'fields' => []],
        ]);
    expect($site->contentComponents()->count())->toBe($before);

    $viewer = User::factory()->create();
    $site->members()->attach($viewer->id, ['role' => 'viewer']);
    Livewire::actingAs($viewer)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('registerMarkers', [['kind' => 'component', 'key' => 'nope', 'fields' => []]])
        ->assertForbidden();
});

test('picking an asset fills the image node with its @media ref and save keeps it', function () {
    [$user, $site, $hero] = previewSite();
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Image', 'type' => 'image', 'value' => '', 'order' => 1]);
    Media::create(['site_id' => $site->id, 'name' => 'team.jpg', 'file_type' => 'image',
        'url' => '/storage/media/'.$site->name.'/team.jpg', 'size' => '1 KB', 'bytes' => 1024]);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->call('onMediaPicked', ['scope' => 'connect', 'nodeIndex' => 1], '@media/team.jpg', '/storage/media/x/team.jpg')
        ->assertSet('edit.nodes.1.value', '@media/team.jpg')
        ->call('saveComponent');

    expect($hero->nodes()->where('label', 'Image')->value('value'))->toBe('@media/team.jpg');
});

test('a media-picked event for another scope is ignored', function () {
    [$user, $site, $hero] = previewSite();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->call('onMediaPicked', ['nodeIndex' => 0], '@media/x.jpg', '')
        ->assertSet('edit.nodes.0.value', 'Original');
});

// ── Collections inside components (collection-typed nodes) ──────────────

test('adding a collection-type field to a component auto-creates and links a collection', function () {
    [$user, $site, $hero] = previewSite();
    Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/'])
        ->components()->attach($hero->id, ['order' => 0]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->call('addNode')
        ->set('edit.nodes.1.label', 'Gallery')
        ->set('edit.nodes.1.type', 'collection')
        ->call('saveComponent');

    $node = $hero->nodes()->where('label', 'Gallery')->first();
    $linked = Collection::where('site_id', $site->id)->find($node->value);
    expect($node->type)->toBe('collection')
        ->and($linked)->not->toBeNull()
        ->and($linked->name)->toBe('Hero Gallery');

    // Items of the linked collection embed into the component's page.json field.
    $lw->call('select', 'collection', $linked->id)
        ->set('newField.label', 'caption')->call('addCollectionField');
    CollectionItem::create(['collection_id' => $linked->id, 'site_id' => $site->id,
        'status' => 'published', 'data' => ['caption' => 'First photo']]);

    $page = $site->pages()->where('url', '/')->first();
    $doc = app(PageJsonGenerator::class)->generate($page);
    $heroRec = collect($doc['componentData'])->firstWhere('key', 'hero');
    expect($heroRec['fields']['gallery'])->toBe([
        ['id' => $linked->items()->first()->id, 'caption' => 'First photo'],
    ]);
});

test('addCollectionField backfills every item with the default and seeds new items', function () {
    [$user, $site, , $services] = previewSite();
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Colour']]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $services->id)
        ->set('newField.label', 'Badge')
        ->set('newField.type', 'text')
        ->set('newField.default', 'Popular')
        ->call('addCollectionField')
        ->assertSet('newField.label', '')
        ->assertSet('edit.schema', ['title', 'badge']);

    expect($services->items()->get()->every(fn ($i) => ($i->data['badge'] ?? null) === 'Popular'))->toBeTrue()
        ->and(collect($services->fresh()->fields)->firstWhere('key', 'badge')['default'])->toBe('Popular')
        ->and(ContentVersion::where('subject_id', $services->id)->exists())->toBeTrue();

    $lw->call('inlineItemAdd', $services->id, null);
    expect($services->items()->orderByDesc('id')->first()->data['badge'])->toBe('Popular');
});

test('openLinkedCollection navigates the inspector to the linked collection', function () {
    [$user, $site, $hero] = previewSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Hero Gallery', 'slug' => 'hero-gallery-x1', 'type' => 'grid', 'is_public' => true, 'fields' => []]);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Gallery', 'type' => 'collection', 'value' => $col->id, 'order' => 1]);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->call('openLinkedCollection', $col->id)
        ->assertSet('selectedKind', 'collection')
        ->assertSet('selectedId', $col->id);
});

test('preview item controls work on collections embedded inside a component', function () {
    [$user, $site, $hero] = previewSite();
    $gallery = Collection::create(['site_id' => $site->id, 'name' => 'Hero Gallery', 'slug' => 'hero-gallery-z9', 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'caption', 'name' => 'caption', 'label' => 'Caption', 'type' => 'text', 'default' => 'New photo']]]);
    Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Gallery', 'type' => 'collection', 'value' => $gallery->id, 'order' => 1]);
    $item = CollectionItem::create(['collection_id' => $gallery->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['caption' => 'First']]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site]);

    // + Add: resolved via componentKey + field path (the marker is the component).
    $lw->call('inlineItemAdd', null, 'hero', 'hero', 'gallery');
    expect($gallery->items()->count())->toBe(2)
        // ULIDs made in the same millisecond don't sort by time — check the set, not "newest".
        ->and($gallery->items()->get()->pluck('data.caption')->sort()->values()->all())->toBe(['First', 'New photo']);

    // Inline text edit: kind arrives as 'component' but itemId wins.
    $lw->call('inlineFieldEdit', null, 'hero', 'component', 'caption', 'Edited inline', $item->id);
    expect($item->fresh()->data['caption'])->toBe('Edited inline');

    // ✕: collection resolved from the item id itself.
    $lw->call('inlineItemRemove', null, 'hero', $item->id);
    expect($gallery->items()->whereKey($item->id)->exists())->toBeFalse()
        // history captured on the LINKED collection for all three mutations
        ->and(ContentVersion::where('subject_id', $gallery->id)->count())->toBeGreaterThanOrEqual(2);
});

test('picking an asset applies immediately — no separate save needed', function () {
    [$user, $site, $hero, $services] = previewSite();
    $imgNode = Node::create(['component_id' => $hero->id, 'parent' => '0', 'label' => 'Image', 'type' => 'image', 'value' => '/old.jpg', 'order' => 1]);
    Media::create(['site_id' => $site->id, 'name' => 'new.jpg', 'file_type' => 'image',
        'url' => '/storage/media/'.$site->name.'/new.jpg', 'size' => '1 KB', 'bytes' => 1024]);
    $item = $services->items()->first();

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'component', $hero->id)
        ->call('onMediaPicked', ['scope' => 'connect', 'nodeIndex' => 1], '@media/new.jpg', '/storage/media/x/new.jpg')
        ->assertDispatched('toast');
    // Persisted WITHOUT calling saveComponent:
    expect($imgNode->fresh()->value)->toBe('@media/new.jpg')
        // and captured in history (revertible)
        ->and(ContentVersion::where('subject_id', $hero->id)->exists())->toBeTrue();

    // Collection item pick persists immediately too.
    $lw->call('select', 'collection', $services->id)
        ->call('onMediaPicked', ['scope' => 'connect', 'itemIndex' => 0, 'itemKey' => 'photo'], '@media/new.jpg', '/storage/x/new.jpg');
    expect($item->fresh()->data['photo'])->toBe('@media/new.jpg'); // the portable ref, resolved on output
});

test('preview row clicks match the entry by visible text, not DOM position', function () {
    [$user, $site, $hero, $services] = previewSite();
    // Two more entries; the template shows them sorted, so a clicked row's
    // index rarely equals the CMS order.
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Beard Sculpting']]);
    CollectionItem::create(['collection_id' => $services->id, 'site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'Hot Towel Shave']]);

    // Click reports index 0 (first row on screen) but the text of the THIRD item.
    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'services', 'collection', 0, 'Hot Towel Shave · Book now')
        ->assertDispatched('olx-editor-focus', target: 'item', index: 2);

    // No text match (too short / unknown) → the index fallback still works.
    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'services', 'collection', 1, 'zz')
        ->assertDispatched('olx-editor-focus', target: 'item', index: 1);
});

test('a data-olx-panel marker on a component section falls back to the component', function () {
    [$user, $site, $hero] = previewSite();

    // The author marked a plain component section with data-olx-panel, so the
    // agent reports kind=collection — no such collection exists, the
    // component of that name must load instead of nothing.
    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'hero', 'collection')
        ->assertSet('selectedId', $hero->id)
        ->assertSet('edit.type', 'component');
});

test('a node-less component linked to a collection opens the collection instead', function () {
    [$user, $site, , $services] = previewSite();
    // Page-wrapper components (legal doc, archives…) have no nodes of their
    // own — their content lives in the linked data-source collection.
    Component::create(['site_id' => $site->id, 'name' => 'Legal Wrap', 'author' => 'api', 'source' => 'api',
        'collection_id' => $services->id]);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'legalWrap', 'component')
        ->assertSet('edit.type', 'collection')
        ->assertSet('selectedId', $services->id);
});

test('the store product grid edits like a collection grid', function () {
    [$user, $site] = previewSite();
    $p1 = $site->products()->create(['slug' => 'book-a', 'name' => 'Book A', 'price_cents' => 1000, 'currency' => 'gbp', 'is_active' => true, 'sort' => 0]);
    $site->products()->create(['slug' => 'book-b', 'name' => 'Book B', 'price_cents' => 2000, 'currency' => 'gbp', 'is_active' => true, 'sort' => 1]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'products', 'collection', null, 'Book B on screen');
    expect($lw->get('edit.type'))->toBe('collection')
        ->and($lw->get('edit.products'))->toBeTrue()
        ->and($lw->get('edit.items'))->toHaveCount(2)
        ->and($lw->get('edit.items.0.data.price'))->toBe('10.00');
    // Text match focused the right card (Book B = index 1).
    $lw->assertDispatched('olx-editor-focus', target: 'item', index: 1);

    // Edit + add + save round-trips into Product models.
    $lw->set('edit.items.0.data.price', '12.50')
        ->call('addItem')
        ->set('edit.items.2.data.name', 'Book C')
        ->set('edit.items.2.data.price', '5')
        ->call('saveCollection');
    expect((int) $p1->fresh()->price_cents)->toBe(1250)
        ->and($site->products()->where('name', 'Book C')->value('price_cents'))->toBe(500);

    // Remove deletes the Product.
    $lw->call('removeItem', 1);
    expect($site->products()->where('slug', 'book-b')->exists())->toBeFalse();
});

test('removing an item from the panel refreshes the preview immediately', function () {
    [$user, $site, , $services] = previewSite();
    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $services->id)
        ->call('removeItem', 0);
    expect($services->items()->count())->toBe(0);
    // Collection-grid changes hard-reload the frame (useSiteContent snapshots
    // rows at setup, so the in-place refetch can't re-render them).
    $lw->assertDispatched('olx-reload-frame');

    // Products variant reloads the frame (the store fetches at mount).
    $site->products()->create(['slug' => 'p1', 'name' => 'P1', 'price_cents' => 100, 'currency' => 'gbp', 'is_active' => true]);
    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'products', 'collection')
        ->call('removeItem', 0)
        ->assertDispatched('olx-reload-frame');
    expect($site->products()->count())->toBe(0);
});

test('display-form field names (Headline, CTA Label) register once and never loop', function () {
    [$user, $site] = previewSite();
    Page::factory()->create(['site_id' => $site->id, 'name' => 'Donate', 'url' => '/donate']);
    $marker = ['kind' => 'component', 'key' => 'donateCta',
        'fields' => [
            ['field' => 'Headline', 'type' => 'text', 'value' => 'Give today'],
            ['field' => 'Text', 'type' => 'text', 'value' => 'Every gift counts'],
            ['field' => 'CTA Label', 'type' => 'text', 'value' => 'Donate'],
        ]];

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site]);
    $lw->call('registerMarkers', [$marker]);
    $cta = $site->contentComponents()->where('name', 'Donate Cta')->first();
    expect($cta)->not->toBeNull()->and($cta->nodes()->count())->toBe(3);

    // Re-registering (what every preview reload does) must add NOTHING —
    // this was the endless "New fields added: Donate Cta" reload loop.
    $lw->call('registerMarkers', [$marker]);
    $lw->call('registerMarkers', [$marker]);
    expect($cta->nodes()->count())->toBe(3)
        ->and($cta->nodes()->pluck('label')->sort()->values()->all())->toBe(['Cta Label', 'Headline', 'Text']);
});

test('camelCase field keys (titleHighlight) match their extracted field instead of duplicating it', function () {
    [$user, $site] = previewSite();
    Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);
    $marker = ['kind' => 'component', 'key' => 'hero',
        'fields' => [['field' => 'title', 'type' => 'text', 'value' => 'Connecting believers,']]];

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site]);
    $lw->call('registerMarkers', [$marker]);
    $hero = $site->contentComponents()->where('name', 'Hero')->first();
    // the extractor's two-word labels, as a template apply creates them
    $hero->nodes()->create(['label' => 'Title Highlight', 'type' => 'text', 'value' => 'strengthening faith', 'parent' => '0', 'order' => 1]);
    $hero->nodes()->create(['label' => 'Join Cta Label', 'type' => 'text', 'value' => 'Join the Church', 'parent' => '0', 'order' => 2]);

    $before = $hero->nodes()->count();

    // The rewriter marks those fields as data-olx-field="titleHighlight" etc.
    $marker['fields'][] = ['field' => 'titleHighlight', 'type' => 'text', 'value' => 'strengthening faith'];
    $marker['fields'][] = ['field' => 'joinCtaLabel', 'type' => 'text', 'value' => 'Join the Church ↗'];
    $lw->call('registerMarkers', [$marker]);
    expect($hero->nodes()->count())->toBe($before)
        ->and($hero->nodes()->pluck('label')->all())->not->toContain('Titlehighlight')->not->toContain('Joinctalabel');

    // An inline edit on the marker updates the existing field.
    $lw->call('inlineFieldEdit', $hero->id, null, 'component', 'titleHighlight', 'growing together');
    expect($hero->nodes()->count())->toBe($before)
        ->and($hero->nodes()->where('label', 'Title Highlight')->value('value'))->toBe('growing together');
});

test('a collection resolves across separator drift (bible_studies vs bible-studies)', function () {
    [$user, $site] = previewSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Bible Studies', 'slug' => 'bible_studies',
        'type' => 'grid', 'is_public' => true, 'fields' => [['key' => 'title', 'name' => 'title', 'label' => 'Title', 'type' => 'text']]]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site]);
    $lw->call('onEditSelect', null, 'bible-studies', 'collection');
    expect($lw->get('edit')['type'] ?? null)->toBe('collection')
        ->and($lw->get('edit')['id'] ?? null)->toBe($col->id);

    // A key that matches nothing tells the user instead of silently no-oping.
    $lw->call('onEditSelect', null, 'ghost-panel', 'collection')->assertDispatched('toast');
});

test('the collection editor hides hidden fields, renders selects, and shows a sub-field only when its condition holds', function () {
    [$user, $site] = previewSite();
    $col = Collection::create(['site_id' => $site->id, 'name' => 'Events', 'type' => 'grid', 'is_public' => true, 'fields' => [
        ['key' => 'id', 'label' => 'Id', 'type' => 'text', 'hidden' => true],
        ['key' => 'when', 'label' => 'When', 'type' => 'datetime'],
        ['key' => 'featured', 'label' => 'Featured', 'type' => 'select', 'options' => ['yes', 'no']],
        ['key' => 'media', 'label' => 'Media', 'type' => 'rows', 'fields' => [
            ['key' => 'type', 'type' => 'select', 'options' => ['image', 'video', 'audio']],
            ['key' => 'img', 'type' => 'image', 'label' => 'Image / poster'],
            ['key' => 'src', 'type' => 'media', 'label' => 'Source file', 'show' => ['field' => 'type', 'in' => ['audio', 'video']]],
        ]],
    ]]);
    \App\Models\CollectionItem::create(['collection_id' => $col->id, 'site_id' => $site->id, 'status' => 'published', 'position' => 0,
        'data' => ['id' => 'secret-id-xyz', 'when' => '2026-10-18T19:00', 'featured' => 'yes',
            'media' => [['type' => 'image', 'img' => '', 'src' => ''], ['type' => 'video', 'img' => '', 'src' => '']]]]);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site]);
    $lw->call('select', 'collection', $col->id);
    $html = $lw->html();

    expect($html)->not->toContain('edit.items.0.data.id"')                   // hidden field not rendered
        ->and($html)->toContain('type="datetime-local"')
        ->and($html)->toContain('<option value="yes">')
        ->and($html)->toContain('Image / poster')
        // Source shows for the video row only
        ->and(substr_count($html, 'Source file'))->toBe(1)
        ->and($html)->toContain('edit.items.0.data.media.1.src')
        ->and($html)->not->toContain('edit.items.0.data.media.0.src');
});

test('a preview element can open Site Properties on just its fields, prefilled from the Site Profile, and saving syncs it back', function () {
    [$user, $site] = previewSite();
    $profile = Collection::create(['site_id' => $site->id, 'name' => 'Site Profile', 'slug' => '', 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'logoText', 'type' => 'textarea'], ['key' => 'logoSub', 'type' => 'text']]]);
    $row = $profile->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['logoText' => "CAC\nMount Zion", 'logoSub' => 'Blackburn.']]);
    App\Support\SiteProperties::component($site);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'site-properties', 'collection', null, null, 'logo,logo_text,logo_subtext');
    expect($lw->get('edit.siteProperties'))->toBeTrue()
        ->and($lw->get('edit.focus'))->toBe(['logo', 'logo_text', 'logo_subtext']);
    $html = $lw->html();
    expect($html)->toContain('Logo Text')->toContain('Logo Subtext')->toContain('Show all site properties')
        ->not->toContain('Legal Name');                           // other tabs stay out of the focused panel

    // prefilled from the Site Profile
    $i = collect($lw->get('edit.nodes'))->search(fn ($n) => $n['label'] === 'Logo Text');
    expect($lw->get("edit.nodes.$i.value"))->toBe("CAC\nMount Zion");

    $lw->set("edit.nodes.$i.value", "Grace Way\nChurch")->call('saveComponent');
    expect($row->fresh()->data['logoText'])->toBe("Grace Way\nChurch")
        ->and($lw->get('edit.focus'))->toBe(['logo', 'logo_text', 'logo_subtext']);   // panel stays focused after save

    $lw->call('showAllProperties');
    expect($lw->get('edit'))->not->toHaveKey('focus');
    expect($lw->html())->toContain('Legal Name');
});

test('clearing Logo Subtext in the focused Site Properties panel clears it on the site', function () {
    [$user, $site] = previewSite();
    $profile = Collection::create(['site_id' => $site->id, 'name' => 'Site Profile', 'slug' => '', 'type' => 'grid', 'is_public' => true,
        'fields' => [['key' => 'logoSub', 'type' => 'text']]]);
    $row = $profile->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['logoSub' => 'Blackburn.']]);
    App\Support\SiteProperties::component($site);

    $lw = Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('onEditSelect', null, 'site-properties', 'collection', null, null, 'logo_subtext');
    $i = collect($lw->get('edit.nodes'))->search(fn ($n) => $n['label'] === 'Logo Subtext');
    expect($lw->get("edit.nodes.$i.value"))->toBe('Blackburn.');
    $lw->set("edit.nodes.$i.value", '')->call('saveComponent');

    expect($row->fresh()->data['logoSub'])->toBe('');
    $j = collect($lw->get('edit.nodes'))->search(fn ($n) => $n['label'] === 'Logo Subtext');
    expect($lw->get("edit.nodes.$j.value"))->toBe('');   // the panel no longer refills the old value
});

test('a select fed by another collection shows that collection\'s entries in both editors', function () {
    [$user, $site] = previewSite();
    $series = Collection::create(['site_id' => $site->id, 'name' => 'Sermon Series', 'slug' => '', 'type' => 'grid', 'is_public' => true, 'fields' => []]);
    $series->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['slug' => 'rooted', 'name' => 'Rooted Series']]);
    $sermons = Collection::create(['site_id' => $site->id, 'name' => 'Sermons', 'slug' => '', 'type' => 'grid', 'is_public' => true, 'fields' => [
        ['key' => 'title', 'type' => 'text'],
        ['key' => 'seriesSlug', 'label' => 'Series', 'type' => 'select', 'optionsFrom' => ['collection' => 'sermon-series', 'value' => 'slug', 'label' => 'name']],
    ]]);
    $item = $sermons->items()->create(['site_id' => $site->id, 'status' => 'published', 'data' => ['title' => 'T', 'seriesSlug' => '']]);

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $sermons->id)
        ->assertSee('<option value="rooted">Rooted Series</option>', false);

    Livewire::actingAs($user)->test(App\Livewire\CollectionsPage::class, ['site' => $site])
        ->call('viewEntries', $sermons->id)->call('openItem', $item->id)
        ->assertSee('<option value="rooted">Rooted Series</option>', false);
});

test('editing an entry in the editor fills its system fields (a slug from the title)', function () {
    [$user, $site, , $services] = previewSite();
    $services->update(['fields' => array_merge((array) $services->fields, [['key' => 'slug', 'name' => 'slug', 'type' => 'slug', 'auto' => 'slug:title']])]);
    $first = $services->items()->orderBy('position')->first();

    Livewire::actingAs($user)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('select', 'collection', $services->id)
        ->set('edit.items.0.data.title', 'Wash & Blow Dry');

    expect($first->fresh()->data['title'])->toBe('Wash & Blow Dry')
        ->and($first->fresh()->data['slug'])->toBe('wash-blow-dry');
});
