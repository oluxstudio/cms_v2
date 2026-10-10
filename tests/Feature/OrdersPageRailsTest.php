<?php

use App\Livewire\OrdersPage;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

function ordersRailsSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'orail-'.uniqid(), 'domain' => 'orail-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');
    $product = $site->products()->create(['name' => 'Wax', 'slug' => 'wax', 'price_cents' => 1000, 'currency' => 'gbp', 'inventory' => 50, 'is_active' => true]);

    return [$site, $product, $owner];
}

function ordersRailsOrder(Site $site, $product, string $status, string $email, int $qty = 1, ?string $name = null): Order
{
    $order = $site->orders()->create([
        'status' => 'pending', 'total_cents' => 1000 * $qty, 'currency' => 'gbp',
        'customer_email' => $email, 'customer_name' => $name,
    ]);
    $order->items()->create(['product_id' => $product->id, 'name' => 'Wax', 'price_cents' => 1000, 'qty' => $qty]);
    if ($status !== 'pending') {
        $order->markPaid();
        if ($status !== 'paid') {
            $order->transitionTo($status);
        }
    }

    return $order->fresh();
}

test('the orders page renders on the 3-rail layout with grid cards by default', function () {
    [$site, $product, $owner] = ordersRailsSite();
    $order = ordersRailsOrder($site, $product, 'paid', 'ada@example.com', 2, 'Ada Lovelace');

    Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->assertSet('viewMode', 'grid')
        ->assertSee($order->displayNumber())
        ->assertSee('Ada Lovelace')
        ->assertSee('Unfulfilled')
        ->assertSee('New or unfulfilled')
        ->assertSee('Repeat customers')
        ->assertSee('Revenue · last 14 days')
        ->assertSee('Top customers')
        ->assertSee('Needs attention')
        ->assertSee('Related');
});

test('status pills filter by group, keep raw statuses working and count each group', function () {
    [$site, $product, $owner] = ordersRailsSite();
    ordersRailsOrder($site, $product, 'pending', 'a@example.com');
    ordersRailsOrder($site, $product, 'paid', 'b@example.com');
    ordersRailsOrder($site, $product, 'shipped', 'c@example.com');
    ordersRailsOrder($site, $product, 'delivered', 'd@example.com');
    ordersRailsOrder($site, $product, 'refunded', 'e@example.com');
    ordersRailsOrder($site, $product, 'cancelled', 'f@example.com');

    $lw = Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site]);
    $emails = fn () => $lw->instance()->orders->pluck('customer_email')->sort()->values()->all();

    $counts = $lw->instance()->statusCounts;
    expect($counts['all'])->toBe(6)
        ->and($counts['group:unfulfilled'])->toBe(2)
        ->and($counts['group:paid'])->toBe(3)
        ->and($counts['group:fulfilled'])->toBe(2)
        ->and($counts['group:refunded'])->toBe(1)
        ->and($counts['group:cancelled'])->toBe(1);

    $lw->call('setStatusFilter', 'unfulfilled');
    expect($emails())->toBe(['a@example.com', 'b@example.com']);
    $lw->call('setStatusFilter', 'fulfilled');
    expect($emails())->toBe(['c@example.com', 'd@example.com']);
    $lw->call('setStatusFilter', 'pending');
    expect($emails())->toBe(['a@example.com']);
    $lw->call('setStatusFilter', 'nope')->assertSet('statusFilter', 'all');
});

test('orders search matches order number and customer; sort is validated', function () {
    [$site, $product, $owner] = ordersRailsSite();
    $big = ordersRailsOrder($site, $product, 'paid', 'big@example.com', 5, 'Bianca');
    ordersRailsOrder($site, $product, 'paid', 'small@example.com', 1, 'Sam');

    $lw = Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site]);

    $lw->set('search', $big->order_number);
    expect($lw->instance()->orders->pluck('id')->all())->toBe([$big->id]);
    $lw->set('search', 'Sam');
    expect($lw->instance()->orders->pluck('customer_name')->all())->toBe(['Sam']);

    $lw->set('search', '')->set('sort', 'amount');
    expect($lw->instance()->orders->first()->id)->toBe($big->id);
    $lw->set('sort', 'amount_asc');
    expect($lw->instance()->orders->last()->id)->toBe($big->id);
    $lw->set('sort', 'weird')->assertSet('sort', 'newest');
});

test('order rail insights count refunds, repeat and top customers', function () {
    [$site, $product, $owner] = ordersRailsSite();
    ordersRailsOrder($site, $product, 'paid', 'loyal@example.com', 2, 'Lou');
    ordersRailsOrder($site, $product, 'delivered', 'loyal@example.com', 3, 'Lou');
    ordersRailsOrder($site, $product, 'paid', 'once@example.com', 1, 'Olly');
    ordersRailsOrder($site, $product, 'refunded', 'gone@example.com', 4);

    $ins = Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])->get('insights');

    expect($ins['refunds'])->toBe(1)
        ->and($ins['repeatCustomers'])->toBe(1)
        ->and($ins['customersAll'])->toBe(2)
        ->and($ins['topCustomers'][0]['email'])->toBe('loyal@example.com')
        ->and($ins['topCustomers'][0]['orders'])->toBe(2)
        ->and($ins['topCustomers'][0]['cents'])->toBe(5000)
        ->and($ins['daily'])->toHaveCount(14)
        ->and(collect($ins['daily'])->sum('cents'))->toBe(6000);
});

test('the orders layout persists per site and the drawer actions still work', function () {
    [$site, $product, $owner] = ordersRailsSite();
    $order = ordersRailsOrder($site, $product, 'paid', 'x@example.com');

    Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->call('setViewMode', 'compact')
        ->assertSet('viewMode', 'compact')
        ->call('open', $order->id)
        ->assertSee('Order history')
        ->assertSee('Mark as shipped')
        ->call('markShipped', $order->id);

    expect($site->fresh()->getAttr('layout:orders'))->toBe('compact')
        ->and($order->fresh()->status)->toBe('shipped');
});

test('an empty orders page shows a helpful empty state', function () {
    [$site, , $owner] = ordersRailsSite();

    Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->assertSee('No orders yet')
        ->assertSee('Manage products');
});
