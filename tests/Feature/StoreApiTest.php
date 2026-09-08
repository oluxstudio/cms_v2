<?php

use App\Livewire\ProductsPage;
use App\Models\Site;
use App\Models\User;
use App\Payments\PaymentManager;
use Tests\Fakes\FakePaymentGateway;

function storeSite(bool $payments = true): Site
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'shop-'.uniqid(), 'domain' => 'shop-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');
    if ($payments) {
        app(PaymentManager::class)->fake(new FakePaymentGateway);
        $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    }
    $site->products()->create(['name' => 'Serum', 'slug' => 'serum', 'price_cents' => 2400, 'currency' => 'gbp', 'inventory' => 5, 'is_active' => true]);
    $site->products()->create(['name' => 'Hidden', 'slug' => 'hidden', 'price_cents' => 100, 'currency' => 'gbp', 'inventory' => 5, 'is_active' => false]);

    return $site;
}

test('products API lists only active products with stock info; show works by slug', function () {
    $site = storeSite(payments: false);

    $res = $this->getJson("/api/sites/{$site->name}/products")->assertOk()->json('products');
    expect(collect($res)->pluck('slug'))->toContain('serum')->not->toContain('hidden')
        ->and($res[0])->toHaveKeys(['price', 'price_cents', 'inventory', 'in_stock', 'image']);

    $this->getJson("/api/sites/{$site->name}/products/serum")->assertOk()
        ->assertJsonPath('product.name', 'Serum')->assertJsonPath('product.in_stock', true);
    $this->getJson("/api/sites/{$site->name}/products/hidden")->assertNotFound();
});

test('headless checkout creates a pending order and returns the gateway URL; stock is enforced', function () {
    $site = storeSite();

    $res = $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 2]],
        'email' => 'buyer@example.com', 'name' => 'Test Buyer', 'phone' => '+44 7700 900000', 'fulfilment' => 'delivery',
        'address_line1' => '1 High Street', 'city' => 'Leeds', 'postcode' => 'LS1 1AA',
        'return_url' => 'https://shop.example/shop',
    ])->assertOk()->json();

    expect($res['checkout_url'])->not->toBeEmpty();
    $order = $site->orders()->find($res['order']);
    expect($order->status)->toBe('pending')
        ->and($order->total_cents)->toBe(4800)
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()->qty)->toBe(2);

    // Over-stock rejected with the product named.
    $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 9]],
        'email' => 'buyer@example.com', 'name' => 'Test Buyer', 'phone' => '+44 7700 900000', 'fulfilment' => 'delivery',
        'address_line1' => '1 High Street', 'city' => 'Leeds', 'postcode' => 'LS1 1AA',
    ])->assertStatus(422)->assertJsonFragment(['message' => '“Serum” only has 5 left in stock.']);
});

test('products carry category & tags; the API filters by both and lists categories', function () {
    $site = storeSite(payments: false);
    $site->products()->where('slug', 'serum')->update(['category' => 'Treatments', 'tags' => json_encode(['overnight', 'repair'])]);
    $site->products()->create(['name' => 'Comb', 'slug' => 'comb', 'price_cents' => 850, 'currency' => 'gbp', 'inventory' => 3, 'is_active' => true, 'category' => 'Tools', 'tags' => ['detangle']]);

    $res = $this->getJson("/api/sites/{$site->name}/products")->assertOk()->json();
    expect(collect($res['products'])->firstWhere('slug', 'serum'))
        ->toMatchArray(['category' => 'Treatments', 'tags' => ['overnight', 'repair']])
        ->and($res['categories'])->toBe(['Tools', 'Treatments']);

    expect($this->getJson("/api/sites/{$site->name}/products?category=Tools")->json('products'))->toHaveCount(1);
    expect(collect($this->getJson("/api/sites/{$site->name}/products?tag=repair")->json('products'))->pluck('slug'))->toContain('serum')->not->toContain('comb');
});

test('admin saves category and tags round-trip', function () {
    $site = storeSite(payments: false);
    $product = $site->products()->where('slug', 'serum')->first();

    Livewire\Livewire::actingAs($site->user)->test(ProductsPage::class, ['site' => $site])
        ->call('edit', $product->id)
        ->set('category', 'Wash & Care')
        ->set('tagsInput', 'colour-safe, daily , colour-safe')
        ->call('save');

    $fresh = $product->fresh();
    expect($fresh->category)->toBe('Wash & Care')
        ->and($fresh->tags)->toBe(['colour-safe', 'daily']);
});

test('checkout returns 409 when payments are off', function () {
    $site = storeSite(payments: false);

    $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 1]],
        'email' => 'b@example.com', 'name' => 'Test Buyer', 'phone' => '+44 7700 900000', 'fulfilment' => 'delivery',
        'address_line1' => '1 High Street', 'city' => 'Leeds', 'postcode' => 'LS1 1AA',
    ])->assertStatus(409);
});

test('paying decrements stock once; cancelling a paid order restocks; lifecycle walks to delivered', function () {
    $site = storeSite();
    $product = $site->products()->where('slug', 'serum')->first();

    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 4800, 'currency' => 'gbp']);
    $order->items()->create(['product_id' => $product->id, 'name' => 'Serum', 'price_cents' => 2400, 'qty' => 2]);

    $order->markPaid('pi_test');
    expect($product->fresh()->inventory)->toBe(3);
    $order->refresh()->markPaid('pi_test'); // idempotent
    expect($product->fresh()->inventory)->toBe(3);

    $order->refresh()->transitionTo('shipped');
    expect($order->fresh()->shipped_at)->not->toBeNull();
    $order->refresh()->transitionTo('delivered');
    expect($order->fresh()->delivered_at)->not->toBeNull()
        ->and($order->fresh()->displayStatus())->toBe('delivered');

    $order->refresh()->transitionTo('cancelled');
    expect($product->fresh()->inventory)->toBe(5); // restocked
});

test('addStock tops up inventory from the product drawer', function () {
    $site = storeSite(payments: false);
    $owner = $site->user;
    $product = $site->products()->where('slug', 'serum')->first();

    Livewire\Livewire::actingAs($owner)->test(ProductsPage::class, ['site' => $site])
        ->call('show', $product->id)
        ->set('restockQty', '7')
        ->call('addStock', $product->id);

    expect($product->fresh()->inventory)->toBe(12);
});

test('interest beacons record events; reviews flow through moderation and the reviews toggle', function () {
    $site = storeSite(payments: false);
    $product = $site->products()->where('slug', 'serum')->first();

    // View + add_to_cart beacons.
    $this->postJson("/api/sites/{$site->name}/products/serum/event", ['event' => 'view'])->assertNoContent();
    $this->postJson("/api/sites/{$site->name}/products/serum/event", ['event' => 'add_to_cart'])->assertNoContent();
    expect($product->events()->where('event', 'view')->count())->toBe(1)
        ->and($product->events()->where('event', 'add_to_cart')->count())->toBe(1);

    // Review submission lands as pending and is invisible until approved.
    $this->postJson("/api/sites/{$site->name}/products/serum/reviews", [
        'name' => 'Ann', 'rating' => 5, 'body' => 'Great serum',
    ])->assertStatus(201);
    $review = $product->reviews()->first();
    expect($review->status)->toBe('pending');
    $this->getJson("/api/sites/{$site->name}/products/serum/reviews")
        ->assertOk()->assertJsonPath('summary.count', 0);

    // Approved reviews surface in the list and on the product record.
    $review->update(['status' => 'approved']);
    $this->getJson("/api/sites/{$site->name}/products/serum/reviews")
        ->assertOk()->assertJsonPath('summary.count', 1)->assertJsonPath('summary.average', 5);
    $this->getJson("/api/sites/{$site->name}/products/serum")->assertOk()
        ->assertJsonPath('product.reviews_enabled', true)
        ->assertJsonPath('product.rating', 5)
        ->assertJsonPath('product.review_count', 1);

    // Turning reviews OFF blocks new submissions with a clear message.
    $product->update(['reviews_enabled' => false]);
    $this->postJson("/api/sites/{$site->name}/products/serum/reviews", [
        'name' => 'Bob', 'rating' => 1, 'body' => 'nope',
    ])->assertStatus(409);
});

test('the store-wide reviews switch overrides per-product settings', function () {
    $site = storeSite(payments: false);
    $product = $site->products()->where('slug', 'serum')->first();
    $product->reviews()->create(['site_id' => $site->id, 'name' => 'Ann', 'rating' => 5, 'body' => 'Great', 'status' => 'approved']);

    // Store-wide OFF: submissions blocked and ratings hidden, even with the product's own toggle ON.
    $site->setAttr('store_reviews_enabled', '0');
    $this->postJson("/api/sites/{$site->name}/products/serum/reviews", [
        'name' => 'Bob', 'rating' => 4, 'body' => 'Nice',
    ])->assertStatus(409);
    $this->getJson("/api/sites/{$site->name}/products/serum")->assertOk()
        ->assertJsonPath('product.reviews_enabled', false)
        ->assertJsonPath('product.rating', null)
        ->assertJsonPath('product.review_count', 0);

    // Store-wide back ON: the per-product toggle rules again.
    $site->setAttr('store_reviews_enabled', '1');
    $this->getJson("/api/sites/{$site->name}/products/serum")->assertOk()
        ->assertJsonPath('product.reviews_enabled', true)
        ->assertJsonPath('product.rating', 5)
        ->assertJsonPath('product.review_count', 1);
    $this->postJson("/api/sites/{$site->name}/products/serum/reviews", [
        'name' => 'Bob', 'rating' => 4, 'body' => 'Nice',
    ])->assertStatus(201);
});

test('the confirm endpoint verifies the session with the gateway and marks the order paid', function () {
    $site = storeSite();
    $res = $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 1]],
        'email' => 'buyer@example.com', 'name' => 'Test Buyer', 'phone' => '+44 7700 900000', 'fulfilment' => 'delivery',
        'address_line1' => '1 High Street', 'city' => 'Leeds', 'postcode' => 'LS1 1AA',
        'return_url' => 'https://shop.example/shop',
    ])->assertOk()->json();
    $order = $site->orders()->find($res['order']);

    // Gateway says NOT paid yet → stays pending.
    app(PaymentManager::class)->for($site)->paid = false;
    $this->postJson("/api/sites/{$site->name}/store/orders/{$order->id}/confirm")
        ->assertOk()->assertJsonPath('status', 'pending');
    expect($order->fresh()->status)->toBe('pending');

    // Gateway confirms → paid, stock decremented, idempotent on repeat.
    app(PaymentManager::class)->for($site)->paid = true;
    $this->postJson("/api/sites/{$site->name}/store/orders/{$order->id}/confirm")
        ->assertOk()->assertJsonPath('status', 'paid');
    expect($order->fresh()->status)->toBe('paid')
        ->and($site->products()->where('slug', 'serum')->first()->inventory)->toBe(4);
    $this->postJson("/api/sites/{$site->name}/store/orders/{$order->id}/confirm")->assertOk();
    expect($site->products()->where('slug', 'serum')->first()->inventory)->toBe(4);
});

test('checkout requires customer details and snapshots fulfilment, VAT, consent and an order number', function () {
    $site = storeSite();
    $site->enableFeature('store', ['vat_percent' => 20]);

    // Missing details → 422 naming each field.
    $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'phone', 'fulfilment']);

    // Delivery needs an address.
    $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 1]],
        'name' => 'Bea', 'email' => 'bea@example.com', 'phone' => '07700 900000', 'fulfilment' => 'delivery',
    ])->assertStatus(422)->assertJsonValidationErrors(['address_line1', 'city', 'postcode']);

    // Happy path: £48.00 at 20% inclusive → £8.00 VAT; OLX number; consent stored.
    $res = $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 2]],
        'name' => 'Bea Buyer', 'email' => 'bea@example.com', 'phone' => '07700 900000',
        'fulfilment' => 'delivery', 'address_line1' => '1 High Street', 'address_line2' => 'Flat 2',
        'city' => 'Leeds', 'postcode' => 'ls1 1aa', 'notes' => 'Leave with neighbour', 'consent' => true,
    ])->assertOk()->json();

    $order = $site->orders()->find($res['order']);
    expect($order->order_number)->toStartWith('OLX-')
        ->and($order->fulfilment)->toBe('delivery')
        ->and($order->vat_bp)->toBe(2000)
        ->and($order->vat_cents)->toBe(800)
        ->and($order->shipping_address)->toContain('1 High Street')->toContain('Leeds LS1 1AA')
        ->and($order->delivery_notes)->toBe('Leave with neighbour')
        ->and($order->marketing_consent)->toBeTrue()
        ->and($order->customer_phone)->toBe('07700 900000');

    // Consent lands on the contact once paid.
    $order->markPaid();
    $contact = $site->contacts()->where('email', 'bea@example.com')->first();
    expect($contact->phone)->toBe('07700 900000')
        ->and($contact->data['marketing_consent'] ?? false)->toBeTrue();
});

test('collection orders need no address and never ask Stripe for one', function () {
    $site = storeSite();
    $gateway = app(PaymentManager::class)->for($site);

    $this->postJson("/api/sites/{$site->name}/store/checkout", [
        'lines' => [['slug' => 'serum', 'qty' => 1]],
        'name' => 'Cal Collector', 'email' => 'cal@example.com', 'phone' => '07700 900001',
        'fulfilment' => 'collection',
    ])->assertOk();

    $order = $site->orders()->latest()->first();
    expect($order->fulfilment)->toBe('collection')
        ->and($order->shipping_address)->toBeNull()
        ->and(end($gateway->checkouts)->collectShipping)->toBeFalse();
});
