<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use Tests\Fakes\FakePaymentGateway;

function paySite(bool $enabled, bool $keys = true): Site
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'pay-'.uniqid(), 'domain' => 'pay-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('store');
    $site->enableFeature('invoices');
    SitePaymentSettings::create([
        'site_id' => $site->id, 'provider' => 'stripe', 'enabled' => $enabled,
        'stripe_publishable' => $keys ? 'pk_test_x' : null, 'stripe_secret' => $keys ? 'sk_test_x' : null,
    ]);

    return $site;
}

function payProduct(Site $site): Product
{
    return Product::create(['site_id' => $site->id, 'name' => 'Thing', 'slug' => 'thing-'.uniqid(), 'price_cents' => 2500, 'currency' => 'gbp', 'is_active' => true, 'inventory' => 5, 'sort' => 0]);
}

test('the off switch blocks every flow gracefully, even with keys saved', function () {
    $site = paySite(enabled: false);
    $product = payProduct($site);

    expect($site->paymentsEnabled())->toBeFalse()
        ->and($site->stripeReady())->toBeFalse(); // deprecated alias honours the switch

    // Store: friendly redirect back, no order left pending.
    $this->post("/preview/{$site->name}/store/checkout", ['product_id' => $product->id])
        ->assertSessionHas('store_error');
    expect($site->orders()->count())->toBe(0);

    // Invoice pay link: friendly notice, not a 404.
    $invoice = Invoice::create(['site_id' => $site->id, 'number' => 'INV-'.uniqid(), 'customer_name' => 'C', 'customer_email' => 'c@x.test', 'currency' => 'gbp', 'status' => 'sent', 'items' => [['description' => 'Work', 'unit_cents' => 1000, 'qty' => 1]], 'subtotal_cents' => 1000, 'total_cents' => 1000, 'due_date' => now()->addWeek()]);
    $this->post("/preview/{$site->name}/invoice/{$invoice->public_token}/pay")
        ->assertRedirect()->assertSessionHas('invoice_error');
});

test('enabled + gateway: store checkout records the session and redirects to the gateway', function () {
    $site = paySite(enabled: true);
    $product = payProduct($site);
    app(PaymentManager::class)->fake($fake = new FakePaymentGateway);

    $this->post("/preview/{$site->name}/store/checkout", ['product_id' => $product->id, 'qty' => 2, 'email' => 'buyer@x.test'])
        ->assertRedirect('https://fake-pay.test/checkout');

    $order = $site->orders()->first();
    expect($order->status)->toBe('pending')
        ->and($order->stripe_session_id)->toStartWith('fake_sess_')
        ->and($fake->checkouts[0]->lines[0]->unitAmountCents)->toBe(2500)
        ->and($fake->checkouts[0]->lines[0]->quantity)->toBe(2);
});

test('a completed webhook marks the order paid and backfills the payer', function () {
    $site = paySite(enabled: true);
    $product = payProduct($site);
    app(PaymentManager::class)->fake($fake = new FakePaymentGateway);

    $this->post("/preview/{$site->name}/store/checkout", ['product_id' => $product->id]);
    $order = $site->orders()->first();

    $fake->nextEvent = new WebhookEvent(
        kind: WebhookEventKind::Completed, sessionId: $order->stripe_session_id,
        metadata: ['order_id' => $order->id], payerEmail: 'payer@x.test', payerName: 'Pat Payer',
        paymentRef: 'pi_fake_1', isPaid: true,
    );
    $this->post("/preview/{$site->name}/store/webhook", [], ['X-Fake-Signature' => 'sig'])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->customer_email)->toBe('payer@x.test');
});

test('a gateway without keys is unavailable even when the switch is on', function () {
    $site = paySite(enabled: true, keys: false);
    expect($site->paymentsEnabled())->toBeFalse();
});

test('the migration grandfathers configured sites on, and blank ones stay off', function () {
    // Rows created AFTER the migration default to off — the explicit switch.
    $site = paySite(enabled: false);
    expect($site->paymentSettings->enabled)->toBeFalse()
        ->and($site->paymentSettings->provider)->toBe('stripe');
});
