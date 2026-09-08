<?php

use App\Models\Booking;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use App\Payments\Drivers\OffGateway;
use App\Payments\Drivers\StripeConnectGateway;
use App\Payments\PaymentManager;
use App\Payments\SitePaymentFulfilment;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;

function connectSite(array $settings = []): Site
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'con-'.uniqid(), 'domain' => 'con-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    SitePaymentSettings::create(array_merge([
        'site_id' => $site->id, 'provider' => 'stripe_connect', 'enabled' => true,
        'connect_account_id' => 'acct_'.uniqid(), 'connect_charges_enabled' => true,
    ], $settings));

    return $site;
}

test('the manager resolves the connect driver, and availability needs platform keys + a chargeable account', function () {
    config(['services.stripe_platform.secret' => 'sk_test_platform']);
    $site = connectSite();
    expect(app(PaymentManager::class)->for($site))->toBeInstanceOf(StripeConnectGateway::class)
        ->and($site->paymentsEnabled())->toBeTrue();

    // Onboarding incomplete → not available.
    $site->paymentSettings->update(['connect_charges_enabled' => false]);
    expect($site->fresh()->paymentsEnabled())->toBeFalse();

    // No platform keys → the whole driver is off.
    config(['services.stripe_platform.secret' => null]);
    expect(connectSite()->paymentsEnabled())->toBeFalse();

    // Switch off → OffGateway regardless of the account.
    config(['services.stripe_platform.secret' => 'sk_test_platform']);
    $off = connectSite(['enabled' => false]);
    expect(app(PaymentManager::class)->for($off))->toBeInstanceOf(OffGateway::class);
});

test('fulfilment applies a completed event to an order and an expiry to a booking hold', function () {
    $site = connectSite();
    $site->enableFeature('store');
    $order = $site->orders()->create(['status' => 'pending', 'total_cents' => 3000, 'currency' => 'gbp', 'stripe_session_id' => uniqid('cs_')]);
    $booking = Booking::create(['site_id' => $site->id, 'customer_name' => 'B', 'customer_email' => 'b@x.test', 'status' => 'awaiting_payment', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

    $f = app(SitePaymentFulfilment::class);
    $f->apply($site, new WebhookEvent(kind: WebhookEventKind::Completed, sessionId: $order->stripe_session_id,
        metadata: ['order_id' => $order->id], payerEmail: 'p@x.test', payerName: 'Pat', paymentRef: 'pi_1', isPaid: true));
    $f->apply($site, new WebhookEvent(kind: WebhookEventKind::Expired, metadata: ['booking_id' => $booking->id]));

    expect($order->fresh()->status)->toBe('paid')
        ->and($order->fresh()->customer_email)->toBe('p@x.test')
        ->and($booking->fresh()->status)->toBe('cancelled');
});

test('the platform connect webhook rejects unsigned calls and unknown accounts safely', function () {
    config(['services.stripe_platform.connect_webhook_secret' => null]);
    $this->post('/stripe/sites/webhook', [], ['Stripe-Signature' => 'bad'])->assertStatus(400);

    config(['services.stripe_platform.connect_webhook_secret' => 'whsec_test']);
    $this->post('/stripe/sites/webhook', [], ['Stripe-Signature' => 'bad'])->assertStatus(400);
});

test('the connect onboarding route is owner-gated and 404s without platform keys', function () {
    config(['services.stripe_platform.secret' => null]);
    $site = connectSite();
    $this->actingAs($site->user)->get("/{$site->name}/payments/connect")->assertRedirect()->assertSessionHas('mp-message');
    $this->actingAs(User::factory()->create())->get("/{$site->name}/payments/connect")->assertForbidden();
});
