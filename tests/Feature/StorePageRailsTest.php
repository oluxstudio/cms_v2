<?php

use App\Livewire\ProductsPage;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function storeRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'srail-'.uniqid(), 'domain' => 'srail-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');

    return [$site, $owner];
}

function storeRailsProduct(Site $site, string $name, array $attrs = []): Product
{
    return $site->products()->create($attrs + [
        'name' => $name, 'slug' => Str::slug($name), 'price_cents' => 1000, 'currency' => 'gbp',
        'inventory' => 20, 'is_active' => true, 'image' => 'https://example.test/'.Str::slug($name).'.jpg',
    ]);
}

test('the store page renders on the 3-rail layout with grid as the default', function () {
    [$site, $owner] = storeRailsSite();
    storeRailsProduct($site, 'Argan Oil', ['category' => 'Oils']);

    Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])
        ->assertSet('viewMode', 'grid')
        ->assertSee('Argan Oil')
        ->assertSee('Out of stock')
        ->assertSee('Revenue this month')
        ->assertSee('Orders to fulfil')
        ->assertSee('Best seller')
        ->assertSee('Sales · last 30 days')
        ->assertSee('Top products')
        ->assertSee('Related')
        ->assertSee('New product');
});

test('store rail stats count stock states, catalogue gaps and sales', function () {
    [$site, $owner] = storeRailsSite();
    $oil = storeRailsProduct($site, 'Oil');
    storeRailsProduct($site, 'Comb', ['inventory' => 0]);
    storeRailsProduct($site, 'Brush', ['inventory' => 3]);
    storeRailsProduct($site, 'Gift', ['is_active' => false, 'image' => null, 'price_cents' => 0, 'inventory' => null]);

    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 3000, 'currency' => 'gbp', 'customer_email' => 'a@example.com']);
    $order->items()->create(['product_id' => $oil->id, 'name' => 'Oil', 'price_cents' => 1000, 'qty' => 3]);
    $order->markPaid();

    $st = Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])->get('storeStats');

    expect($st['total'])->toBe(4)
        ->and($st['active'])->toBe(3)
        ->and($st['hidden'])->toBe(1)
        ->and($st['out'])->toBe(1)
        ->and($st['low'])->toBe(1)
        ->and($st['noImage']->pluck('name')->all())->toBe(['Gift'])
        ->and($st['noPrice']->pluck('name')->all())->toBe(['Gift'])
        ->and($st['toFulfil'])->toBe(1)
        ->and($st['orders30'])->toBe(1)
        ->and($st['units30'])->toBe(3)
        ->and($st['revenue30Cents'])->toBe(3000)
        ->and($st['top'][0]['name'])->toBe('Oil')
        ->and($st['top'][0]['units'])->toBe(3);
});

test('filter pills, category and sort narrow and order the grid and live in the URL', function () {
    [$site, $owner] = storeRailsSite();
    $cheap = storeRailsProduct($site, 'Cheap Comb', ['price_cents' => 300, 'category' => 'Tools']);
    storeRailsProduct($site, 'Dear Dryer', ['price_cents' => 9000, 'category' => 'Tools']);
    storeRailsProduct($site, 'Empty Tub', ['inventory' => 0, 'category' => 'Care']);
    storeRailsProduct($site, 'Last Jar', ['inventory' => 2, 'category' => 'Care']);
    storeRailsProduct($site, 'Secret', ['is_active' => false]);

    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 300, 'currency' => 'gbp']);
    $order->items()->create(['product_id' => $cheap->id, 'name' => 'Cheap Comb', 'price_cents' => 300, 'qty' => 5]);
    $order->markPaid();

    $lw = Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site]);
    $names = fn () => collect($lw->instance()->products->items())->pluck('name')->all();

    $lw->call('setFilter', 'out');
    expect($names())->toBe(['Empty Tub']);
    $lw->call('setFilter', 'low');
    expect($names())->toBe(['Last Jar']);
    $lw->call('setFilter', 'hidden');
    expect($names())->toBe(['Secret']);
    $lw->call('setFilter', 'bogus')->assertSet('filter', 'all');

    $lw->set('categoryFilter', 'Tools')->set('sort', 'price_desc');
    expect($names())->toBe(['Dear Dryer', 'Cheap Comb']);
    $lw->set('sort', 'price_asc');
    expect($names())->toBe(['Cheap Comb', 'Dear Dryer']);

    $lw->set('categoryFilter', '')->set('sort', 'best');
    expect($names()[0])->toBe('Cheap Comb');

    $lw->set('sort', 'nonsense')->assertSet('sort', 'newest');
});

test('the store layout choice persists per site', function () {
    [$site, $owner] = storeRailsSite();

    Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])->call('setViewMode', 'list');

    expect($site->fresh()->getAttr('layout:store'))->toBe('list');
    Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site->fresh()])->assertSet('viewMode', 'list');
});

test('the store page warns when payments are not connected and offers an empty state', function () {
    [$site, $owner] = storeRailsSite();

    Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])
        ->assertSee("Stripe isn't connected", false)
        ->assertSee('Payments not connected')
        ->assertSee('No products yet')
        ->assertSee('Add your first product');
});

test('product CRUD still works from the redesigned store page', function () {
    [$site, $owner] = storeRailsSite();

    $lw = Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])
        ->call('create')
        ->set('name', 'Shine Spray')->set('price', '12.50')->set('inventory', '4')
        ->set('category', 'Finish')->set('tagsInput', 'gloss, hold')
        ->set('imageUrl', '@media/spray.jpg')
        ->call('save');

    $p = $site->products()->where('name', 'Shine Spray')->first();
    expect($p)->not->toBeNull()
        ->and($p->price_cents)->toBe(1250)
        ->and($p->image)->toBe('@media/spray.jpg')
        ->and($p->tags)->toBe(['gloss', 'hold']);

    $lw->call('toggleActive', $p->id);
    expect($p->fresh()->is_active)->toBeFalse();

    $lw->call('delete', $p->id);
    expect($site->products()->count())->toBe(0);
});
