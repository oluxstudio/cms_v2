<?php

use App\Livewire\ConnectReviewPage;
use App\Livewire\PageDetailPage;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function pageDetailSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'pd-'.uniqid(),
        'domain' => 'pd-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Services', 'url' => '/services', 'keywords' => 'cuts', 'is_published' => true]);

    return [$owner, $site, $page];
}

test('the page detail route renders with tabs and loads existing metadata', function () {
    [$owner, $site, $page] = pageDetailSite();
    $page->setAttr('description', 'All our services');
    $page->setAttr('robots', 'noindex, follow');

    $this->actingAs($owner)->get("/{$site->name}/pages/{$page->id}/details")
        ->assertOk()->assertSee('Services')->assertSee('Page attributes');

    $lw = Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page]);
    expect($lw->get('metaDescription'))->toBe('All our services')
        ->and($lw->get('robots'))->toBe('noindex, follow')
        ->and($lw->get('metaKeywords'))->toBe('cuts');
});

test('the edit tab saves name, url and published state with validation', function () {
    [$owner, $site, $page] = pageDetailSite();

    $lw = Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page])
        ->set('url', 'no-leading-slash')->call('saveEdit')->assertHasErrors(['url']);

    $lw->set('name', 'Our Services')->set('url', '/our-services')->set('isPublished', false)->call('saveEdit');
    $page->refresh();
    expect($page->name)->toBe('Our Services')
        ->and($page->url)->toBe('/our-services')
        ->and($page->is_published)->toBeFalse();
});

test('the metadata tab saves every meta tag and custom attributes', function () {
    [$owner, $site, $page] = pageDetailSite();
    $page->setAttr('stale_attr', 'bye');

    Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page])
        ->set('metaTitle', 'Services — Glow Salon')
        ->set('metaDescription', 'Cuts, colour and treatments in Leeds.')
        ->set('metaKeywords', 'cuts, colour')
        ->set('ogTitle', 'Glow Salon services')
        ->set('ogDescription', 'Book online today')
        ->set('ogImage', 'https://example.com/og.jpg')
        ->set('canonicalUrl', 'https://glow.example/services')
        ->set('robots', 'index, nofollow')
        ->set('attrRows', [['key' => 'accent', 'value' => 'gold']])
        ->call('saveMeta');

    $page->refresh();
    expect($page->getAttr('title'))->toBe('Services — Glow Salon')
        ->and($page->getAttr('description'))->toBe('Cuts, colour and treatments in Leeds.')
        ->and($page->keywords)->toBe('cuts, colour')
        ->and($page->getAttr('og_title'))->toBe('Glow Salon services')
        ->and($page->getAttr('og_description'))->toBe('Book online today')
        ->and($page->getAttr('og_image'))->toBe('https://example.com/og.jpg')
        ->and($page->getAttr('canonical_url'))->toBe('https://glow.example/services')
        ->and($page->getAttr('robots'))->toBe('index, nofollow')
        ->and($page->getAttr('accent'))->toBe('gold')
        ->and($page->getAttr('stale_attr'))->toBeNull(); // removed row → forgotten

    // The content API exposes it all (description directly, the rest via attributes).
    $res = $this->getJson("/api/sites/{$site->name}/content")->assertOk()->json();
    $payload = collect($res['pages'] ?? [])->firstWhere('url', '/services');
    expect($payload['description'] ?? null)->toBe('Cuts, colour and treatments in Leeds.')
        ->and($payload['attributes']['og_image'] ?? null)->toBe('https://example.com/og.jpg');
});

test('a page from another site 404s', function () {
    [$owner, $site, $page] = pageDetailSite();
    [$other, $otherSite, $otherPage] = pageDetailSite();

    $this->actingAs($owner)->get("/{$site->name}/pages/{$otherPage->id}/details")->assertNotFound();
});

test('the content tab embeds the connect editor scoped to this page with summary tiles', function () {
    [$owner, $site, $page] = pageDetailSite();
    $component = $site->contentComponents()->create(['name' => 'Hero', 'author' => 't', 'source' => 'app']);
    $component->nodes()->create(['label' => 'Headline', 'type' => 'text', 'value' => 'Hi', 'parent' => '0', 'order' => 0]);
    $page->components()->attach($component->id, ['order' => 1]);

    $lw = Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page])
        ->call('setTab', 'content');

    expect($lw->instance()->summary['components'])->toBe(1)
        ->and($lw->instance()->summary['fields'])->toBe(1);
    $lw->assertSee('sections')->assertSee('visits · 30 days')->assertSeeLivewire(ConnectReviewPage::class);

    // The embedded editor opens on THIS page's path.
    $connect = Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site, 'previewPath' => $page->url]);
    expect($connect->get('previewPath'))->toBe('/services');
});

test('inlineLinkEdit saves label and href for components and collection rows', function () {
    [$owner, $site, $page] = pageDetailSite();

    // Component: Cta with Label + Link nodes.
    $cta = $site->contentComponents()->create(['name' => 'Cta', 'author' => 't', 'source' => 'app']);
    $cta->nodes()->create(['label' => 'Label', 'type' => 'text', 'value' => 'Book now', 'parent' => '0', 'order' => 0]);
    $cta->nodes()->create(['label' => 'Link', 'type' => 'text', 'value' => '/old', 'parent' => '0', 'order' => 1]);

    $lw = Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site]);
    $lw->call('inlineLinkEdit', 'cta', 'component', 'label', 'Reserve a chair', '/appointment', null);
    expect($cta->nodes()->where('label', 'Label')->value('value'))->toBe('Reserve a chair')
        ->and($cta->nodes()->where('label', 'Link')->value('value'))->toBe('/appointment');

    // Collection row: nav items with label + href data keys.
    $nav = $site->collections()->create(['name' => 'Left Nav', 'slug' => 'left-nav', 'type' => 'list', 'fields' => [
        ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
        ['key' => 'href', 'label' => 'Href', 'type' => 'text'],
    ]]);
    $nav->items()->create(['site_id' => $site->id, 'status' => 'published', 'position' => 0, 'data' => ['label' => 'About', 'href' => '/about']]);

    $lw->call('inlineLinkEdit', 'left-nav', 'collection', 'label', 'Our Story', '/story', 0);
    $row = $nav->items()->first()->fresh();
    expect($row->data['label'])->toBe('Our Story')
        ->and($row->data['href'])->toBe('/story');
});

test('inlineLinkEdit fixes UNMARKED nav links by matching current values (header menus)', function () {
    [$owner, $site, $page] = pageDetailSite();
    $header = $site->contentComponents()->create(['name' => 'Site Header', 'author' => 't', 'source' => 'app']);
    foreach ([
        ['Left Nav 1 Label', 'About'], ['Left Nav 1 Href', '/about'],
        ['Right Nav 2 Label', 'Shop'], ['Right Nav 2 Href', '/shp'],
    ] as $i => [$label, $value]) {
        $header->nodes()->create(['label' => $label, 'type' => 'text', 'value' => $value, 'parent' => '0', 'order' => $i]);
    }

    // No labelField (unmarked anchor): resolves by old label, pairs href via the stem.
    Livewire::actingAs($owner)->test(ConnectReviewPage::class, ['site' => $site])
        ->call('inlineLinkEdit', 'site-header', 'component', '', 'Shop', '/shop', null, 'Shop', '/shp');

    expect($header->nodes()->where('label', 'Right Nav 2 Href')->value('value'))->toBe('/shop')
        ->and($header->nodes()->where('label', 'Right Nav 2 Label')->value('value'))->toBe('Shop')
        ->and($header->nodes()->where('label', 'Left Nav 1 Href')->value('value'))->toBe('/about'); // untouched
});
