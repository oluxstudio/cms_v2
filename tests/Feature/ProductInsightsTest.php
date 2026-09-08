<?php

use App\Livewire\ProductDetailPage;
use App\Livewire\ProductsPage;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function insightSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ins-'.uniqid(), 'domain' => 'ins-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');
    $product = $site->products()->create(['name' => 'Oil', 'slug' => 'oil', 'price_cents' => 1500, 'currency' => 'gbp', 'inventory' => 10, 'is_active' => true]);

    return [$site, $product, $owner];
}

test('stock movements are recorded for sale, cancel restock, restock and manual edits', function () {
    [$site, $product, $owner] = insightSite();

    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 3000, 'currency' => 'gbp']);
    $order->items()->create(['product_id' => $product->id, 'name' => 'Oil', 'price_cents' => 1500, 'qty' => 2]);

    $order->markPaid();
    expect($product->fresh()->inventory)->toBe(8);
    $sale = $product->stockMovements()->first();
    expect($sale->reason)->toBe('sale')->and($sale->delta)->toBe(-2)
        ->and($sale->stock_after)->toBe(8)->and($sale->order_id)->toBe($order->id);

    $order->transitionTo('cancelled');
    expect($product->fresh()->inventory)->toBe(10)
        ->and($product->stockMovements()->where('reason', 'cancel_restock')->where('delta', 2)->exists())->toBeTrue();

    // Quick restock from the admin drawer.
    Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])
        ->set('restockQty', '5')->call('addStock', $product->id);
    $restock = $product->stockMovements()->where('reason', 'restock')->first();
    expect($product->fresh()->inventory)->toBe(15)
        ->and($restock->delta)->toBe(5)->and($restock->user_id)->toBe($owner->id);

    // Direct inventory edit -> "manual" movement with the difference.
    Livewire::actingAs($owner)->test(ProductDetailPage::class, ['site' => $site, 'product' => $product->fresh()])
        ->set('inventory', '12')->call('save');
    $manual = $product->stockMovements()->where('reason', 'manual')->first();
    expect($product->fresh()->inventory)->toBe(12)
        ->and($manual->delta)->toBe(-3)->and($manual->stock_after)->toBe(12);
});

test('product detail page renders history, analytics and moderates reviews', function () {
    [$site, $product, $owner] = insightSite();

    $product->events()->create(['site_id' => $site->id, 'event' => 'view']);
    $product->events()->create(['site_id' => $site->id, 'event' => 'add_to_cart']);
    $review = $product->reviews()->create(['site_id' => $site->id, 'name' => 'Ann', 'rating' => 5, 'body' => 'Lovely oil', 'status' => 'pending']);

    $this->actingAs($owner)->get("/{$site->name}/store/{$product->id}")
        ->assertOk()->assertSee('Oil')->assertSee('Inventory history');

    $lw = Livewire::actingAs($owner)->test(ProductDetailPage::class, ['site' => $site, 'product' => $product]);
    expect($lw->get('interest')['total_views'])->toBe(1)
        ->and($lw->get('interest')['total_adds'])->toBe(1);

    $lw->call('approveReview', $review->id);
    expect($review->fresh()->status)->toBe('approved');

    // Reviews toggle OFF blocks storefront submissions.
    $lw->call('toggleReviews');
    expect($product->fresh()->reviews_enabled)->toBeFalse();

    $lw->call('deleteReview', $review->id);
    expect($product->reviews()->count())->toBe(0);
});

test('store page leaderboards rank best sellers, most popular and most reviewed', function () {
    [$site, $product, $owner] = insightSite();
    $second = $site->products()->create(['name' => 'Balm', 'slug' => 'balm', 'price_cents' => 900, 'currency' => 'gbp', 'inventory' => 10, 'is_active' => true]);

    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 4500, 'currency' => 'gbp']);
    $order->items()->create(['product_id' => $product->id, 'name' => 'Oil', 'price_cents' => 1500, 'qty' => 3]);
    $order->markPaid();

    $second->events()->create(['site_id' => $site->id, 'event' => 'view']);
    $second->events()->create(['site_id' => $site->id, 'event' => 'view']);
    $second->reviews()->create(['site_id' => $site->id, 'name' => 'Bo', 'rating' => 4, 'body' => 'Nice', 'status' => 'approved']);

    $ins = Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])->get('insights');
    expect($ins['best_labels'])->toBe(['Oil'])
        ->and($ins['best_units'])->toBe([3])
        ->and(array_key_first($ins['popular']))->toBe('Balm')
        ->and(array_key_first($ins['reviewed']))->toContain('Balm')->toContain('★4');
});
