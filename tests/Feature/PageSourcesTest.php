<?php

use App\Livewire\PageDetailPage;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function sourcesSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'src-'.uniqid(),
        'domain' => 'src-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');
    $page = Page::create(['site_id' => $site->id, 'name' => 'Shop', 'url' => '/shop', 'keywords' => '', 'is_published' => true]);

    return [$owner, $site, $page];
}

test('the Sources tab saves product/post filters and they reach the content API', function () {
    [$owner, $site, $page] = sourcesSite();
    $site->products()->create(['name' => 'Oil', 'slug' => 'oil-'.uniqid(), 'price_cents' => 100, 'currency' => 'gbp', 'is_active' => true, 'category' => 'Styling', 'tags' => ['repair']]);

    Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page])
        ->call('setTab', 'sources')
        ->set('srcProducts.enabled', true)
        ->set('srcProducts.category', 'Styling')
        ->set('srcProducts.tags', 'repair, bleach-care')
        ->set('srcProducts.limit', 9)
        ->set('srcPosts.enabled', true)
        ->set('srcPosts.category', 'Tips')
        ->set('srcPosts.limit', 4)
        ->call('saveSources');

    $cfg = json_decode((string) $page->fresh()->getAttr('content_sources'), true);
    expect($cfg['products']['category'])->toBe('Styling')
        ->and($cfg['products']['tags'])->toBe(['repair', 'bleach-care'])
        ->and($cfg['products']['limit'])->toBe(9)
        ->and($cfg['posts']['category'])->toBe('Tips');

    $res = $this->getJson("/api/sites/{$site->name}/content")->assertOk()->json();
    $payload = collect($res['pages'])->firstWhere('url', '/shop');
    expect($payload['sources']['products']['category'] ?? null)->toBe('Styling')
        ->and($payload['sources']['posts']['limit'] ?? null)->toBe(4);

    // The products API honours the saved filter server-side.
    $site->products()->create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'price_cents' => 100, 'currency' => 'gbp', 'is_active' => true, 'category' => 'Care']);
    $list = $this->getJson("/api/sites/{$site->name}/products?category=Styling")->assertOk()->json('products');
    expect(collect($list)->pluck('name'))->toContain('Oil')->not->toContain('Other');
});

test('collections attach per page with an item limit that the payload obeys', function () {
    [$owner, $site, $page] = sourcesSite();
    $col = $site->collections()->create(['name' => 'Testimonials', 'slug' => 'testimonials-'.uniqid(), 'type' => 'list', 'fields' => [['key' => 'quote', 'label' => 'Quote', 'type' => 'text']]]);
    for ($i = 0; $i < 5; $i++) {
        $col->items()->create(['site_id' => $site->id, 'status' => 'published', 'position' => $i, 'data' => ['quote' => "Quote {$i}"]]);
    }

    Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page])
        ->call('setTab', 'sources')
        ->set("srcCollections.{$col->id}.attached", true)
        ->set("srcCollections.{$col->id}.limit", '2')
        ->call('saveSources');

    expect($page->collections()->count())->toBe(1);
    $res = $this->getJson("/api/sites/{$site->name}/content")->assertOk()->json();
    $payload = collect($res['pages'])->firstWhere('url', '/shop');
    $colPayload = collect($payload['collections'])->first();
    expect(count($colPayload['items']))->toBe(2)
        ->and($colPayload['page_limit'])->toBe(2);

    // Unticking detaches.
    Livewire::actingAs($owner)->test(PageDetailPage::class, ['site' => $site, 'page' => $page])
        ->call('setTab', 'sources')
        ->set("srcCollections.{$col->id}.attached", false)
        ->call('saveSources');
    expect($page->collections()->count())->toBe(0);
});
