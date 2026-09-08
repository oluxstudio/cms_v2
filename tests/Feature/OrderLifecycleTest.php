<?php

use App\Livewire\OrdersPage;
use App\Models\Site;
use App\Models\User;
use App\Payments\PaymentManager;
use Livewire\Livewire;
use Tests\Fakes\FakePaymentGateway;

function lifecycleSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'ord-'.uniqid(), 'domain' => 'ord-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');
    $product = $site->products()->create(['name' => 'Wax', 'slug' => 'wax', 'price_cents' => 1200, 'currency' => 'gbp', 'inventory' => 10, 'is_active' => true]);
    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 2400, 'currency' => 'gbp', 'customer_email' => 'b@example.com']);
    $order->items()->create(['product_id' => $product->id, 'name' => 'Wax', 'price_cents' => 1200, 'qty' => 2]);

    return [$site, $product, $order, $owner];
}

test('every lifecycle step is recorded as an order event with the acting user', function () {
    [$site, $product, $order, $owner] = lifecycleSite();

    $order->markPaid('pi_123');
    $order->transitionTo('shipped', $owner->id);
    $order->transitionTo('delivered', $owner->id);

    expect($order->events()->pluck('status')->all())->toBe(['paid', 'shipped', 'delivered'])
        ->and($order->events()->where('status', 'shipped')->first()->user_id)->toBe($owner->id);
});

test('a returned order restocks the items and records a return_restock movement', function () {
    [$site, $product, $order, $owner] = lifecycleSite();
    $order->markPaid();
    expect($product->fresh()->inventory)->toBe(8);

    $order->transitionTo('return_requested', $owner->id);
    expect($product->fresh()->inventory)->toBe(8); // request alone moves nothing

    $order->transitionTo('returned', $owner->id);
    expect($product->fresh()->inventory)->toBe(10)
        ->and($order->fresh()->returned_at)->not->toBeNull()
        ->and($product->stockMovements()->where('reason', 'return_restock')->where('delta', 2)->exists())->toBeTrue()
        ->and($order->events()->pluck('status')->all())->toBe(['paid', 'return_requested', 'returned']);

    // A later refund never restocks again.
    $order->transitionTo('refunded', $owner->id);
    expect($product->fresh()->inventory)->toBe(10)
        ->and($order->fresh()->refunded_at)->not->toBeNull();
});

test('refundOrder sends the refund through the gateway and marks the order refunded', function () {
    [$site, $product, $order, $owner] = lifecycleSite();
    $fake = new FakePaymentGateway;
    app(PaymentManager::class)->fake($fake);
    $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    $order->markPaid('pi_refund_me');

    Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->call('refundOrder', $order->id);

    expect($fake->refunds)->toHaveCount(1)
        ->and($fake->refunds[0]['ref'])->toBe('pi_refund_me')
        ->and($order->fresh()->status)->toBe('refunded')
        ->and($order->fresh()->refunded_at)->not->toBeNull()
        ->and($order->events()->pluck('status')->all())->toBe(['paid', 'refunded']);
});

test('a failed gateway refund changes nothing and surfaces an error', function () {
    [$site, $product, $order, $owner] = lifecycleSite();
    $fake = new FakePaymentGateway;
    $fake->refundThrows = true;
    app(PaymentManager::class)->fake($fake);
    $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    $order->markPaid('pi_bad');

    $lw = Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->call('refundOrder', $order->id);

    expect($order->fresh()->status)->toBe('paid')
        ->and($lw->get('errorMessage'))->toContain('could not be processed');
});

test('legacy orders without event rows still render the drawer (timestamp fallback)', function () {
    [$site, $product, $order, $owner] = lifecycleSite();
    $order->update(['status' => 'delivered', 'paid_at' => now()->subDays(2), 'shipped_at' => now()->subDay(), 'delivered_at' => now()]);
    expect($order->events()->count())->toBe(0);

    Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->call('open', $order->id)
        ->assertSee('Order history')->assertSee('Delivered');
});
