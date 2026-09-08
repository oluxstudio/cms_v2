<?php

use App\Livewire\OrdersPage;
use App\Mail\CourierInvite;
use App\Mail\NewOrderNotification;
use App\Mail\OrderConfirmed;
use App\Models\Site;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Fakes\FakePaymentGateway;

function orderDeliverySite(): array
{
    $owner = User::factory()->create(['email' => 'owner-'.uniqid().'@salon.test']);
    $site = Site::create(['user_id' => $owner->id, 'name' => 'del-'.uniqid(), 'domain' => 'del-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    $site->enableFeature('store');
    $product = $site->products()->create(['name' => 'Gel', 'slug' => 'gel', 'price_cents' => 800, 'currency' => 'gbp', 'inventory' => 9, 'is_active' => true]);
    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 1600, 'currency' => 'gbp', 'customer_email' => 'buyer@example.com', 'customer_name' => 'Bea Buyer']);
    $order->items()->create(['product_id' => $product->id, 'name' => 'Gel', 'price_cents' => 800, 'qty' => 2]);

    return [$site, $order, $owner];
}

test('marking an order paid emails the buyer (with the status link) and the owner — once', function () {
    Mail::fake();
    [$site, $order, $owner] = orderDeliverySite();

    $order->markPaid('pi_x');

    expect($order->public_token)->not->toBeNull();
    Mail::assertQueued(OrderConfirmed::class, fn ($m) => $m->hasTo('buyer@example.com')
        && str_contains($m->order->statusUrl(), $order->public_token));
    Mail::assertQueued(NewOrderNotification::class, fn ($m) => $m->hasTo($owner->email));

    $order->refresh()->markPaid('pi_x'); // idempotent — no duplicate mails
    Mail::assertQueued(OrderConfirmed::class, 1);
    Mail::assertQueued(NewOrderNotification::class, 1);
});

test('an order with no customer email only notifies the owner', function () {
    Mail::fake();
    [$site, $order, $owner] = orderDeliverySite();
    $order->update(['customer_email' => null]);

    $order->markPaid();

    Mail::assertNotQueued(OrderConfirmed::class);
    Mail::assertQueued(NewOrderNotification::class, 1);
});

test('confirmOrder backfills the shipping address and phone from the gateway session', function () {
    Mail::fake();
    [$site, $order, $owner] = orderDeliverySite();
    $sess = 'sess_'.uniqid();
    $fake = new FakePaymentGateway;
    $fake->details = new WebhookEvent(
        WebhookEventKind::Completed, $sess, [], 'buyer@example.com', 'Bea Buyer', 'pi_9', true,
        "Bea Buyer\n1 High Street\nLeeds LS1 1AA\nGB", '+447700900123',
    );
    app(PaymentManager::class)->fake($fake);
    $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    $order->update(['stripe_session_id' => $sess]);

    $this->postJson("/api/sites/{$site->name}/store/orders/{$order->id}/confirm")
        ->assertOk()->assertJsonPath('status', 'paid');

    $order->refresh();
    expect($order->shipping_address)->toContain('1 High Street')
        ->and($order->customer_phone)->toBe('+447700900123');
});

test('inviting a courier stores the token and emails the delivery link', function () {
    Mail::fake();
    [$site, $order, $owner] = orderDeliverySite();
    $order->markPaid();

    Livewire::actingAs($owner)->test(OrdersPage::class, ['site' => $site])
        ->set('courierEmail', 'rider@courier.test')
        ->call('inviteCourier', $order->id);

    $order->refresh();
    expect($order->courier_token)->not->toBeNull()
        ->and($order->courier_email)->toBe('rider@courier.test')
        ->and($order->courier_invited_at)->not->toBeNull();
    Mail::assertQueued(CourierInvite::class, fn ($m) => $m->hasTo('rider@courier.test'));
});

test('the courier page shows the address and marking delivered walks the lifecycle', function () {
    Mail::fake();
    [$site, $order, $owner] = orderDeliverySite();
    $order->update(['shipping_address' => "Bea Buyer\n1 High Street\nLeeds LS1 1AA"]);
    $order->markPaid();
    $token = Str::random(40);
    $order->update(['courier_email' => 'rider@courier.test', 'courier_token' => $token]);

    $this->get("/preview/{$site->name}/deliver/{$token}")
        ->assertOk()->assertSee('1 High Street')->assertSee('Mark as delivered');

    $this->post("/preview/{$site->name}/deliver/{$token}/status", ['status' => 'delivered'])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('delivered')
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->events()->where('status', 'delivered')->first()->note)->toContain('rider@courier.test');

    // Buyer status page reflects it; a wrong token 404s.
    $this->get("/preview/{$site->name}/order/{$order->public_token}")
        ->assertOk()->assertSee('Delivered');
    $this->get("/preview/{$site->name}/order/definitely-wrong-token")->assertNotFound();
});
