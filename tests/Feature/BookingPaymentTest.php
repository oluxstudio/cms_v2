<?php

use App\Models\Site;
use App\Models\User;
use App\Payments\PaymentManager;
use Tests\Fakes\FakePaymentGateway;

/** The CLIENT-SITE payment contract: paid services hand back a checkout_url. */
function paidBookingSite(bool $payments = true): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'bp-'.uniqid(), 'domain' => 'bp-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('bookings');
    $gateway = null;
    if ($payments) {
        $gateway = new FakePaymentGateway;
        app(PaymentManager::class)->fake($gateway);
        $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);
    }

    return [$site, $gateway];
}

function paidSlotStart(): string
{
    return now()->next('Monday')->setTime(10, 0)->format('Y-m-d H:i:s');
}

test('a full-payment service sends the visitor to checkout for the full price', function () {
    [$site, $gateway] = paidBookingSite();
    $svc = $site->services()->create(['name' => 'Colouring', 'slug' => 'colouring', 'kind' => 'slot',
        'duration_min' => 60, 'price_cents' => 6000, 'currency' => 'gbp', 'requires_payment' => true, 'is_active' => true]);

    $res = $this->postJson("/api/sites/{$site->name}/booking", [
        'service' => 'colouring', 'name' => 'Vera', 'email' => 'v@x.test', 'start' => paidSlotStart(),
    ])->assertStatus(201)->json();

    expect($res['checkout_url'])->toBe('https://fake-pay.test/checkout');
    $booking = $site->bookings()->first();
    expect($booking->status)->toBe('awaiting_payment')
        ->and($booking->params['charge_cents'])->toBe(6000)          // FULL price charged
        ->and($gateway->checkouts[0]->lines[0]->unitAmountCents)->toBe(6000);
});

test('a deposit service charges only the deposit online', function () {
    [$site, $gateway] = paidBookingSite();
    $site->services()->create(['name' => 'Balayage', 'slug' => 'balayage', 'kind' => 'slot',
        'duration_min' => 90, 'price_cents' => 10000, 'currency' => 'gbp',
        'requires_payment' => true, 'deposit_pct' => 30, 'is_active' => true]);

    $this->postJson("/api/sites/{$site->name}/booking", [
        'service' => 'balayage', 'name' => 'Dee', 'email' => 'd@x.test', 'start' => paidSlotStart(),
    ])->assertStatus(201);

    $booking = $site->bookings()->first();
    expect($booking->params['charge_cents'])->toBe(3000)             // 30% deposit
        ->and($gateway->checkouts[0]->lines[0]->unitAmountCents)->toBe(3000)
        ->and($gateway->checkouts[0]->lines[0]->name)->toContain('deposit')
        ->and($booking->total_cents)->toBe(10000);                   // balance tracked
});

test('without payments connected the booking falls back to the unpaid flow', function () {
    [$site] = paidBookingSite(payments: false);
    $site->services()->create(['name' => 'Trim', 'slug' => 'trim', 'kind' => 'slot',
        'duration_min' => 30, 'price_cents' => 2000, 'currency' => 'gbp',
        'requires_payment' => true, 'config' => ['auto_confirm' => true], 'is_active' => true]);

    $res = $this->postJson("/api/sites/{$site->name}/booking", [
        'service' => 'trim', 'name' => 'Ana', 'email' => 'a@x.test', 'start' => paidSlotStart(),
    ])->assertStatus(201)->json();

    expect($res)->not->toHaveKey('checkout_url')
        ->and($res['status'])->toBe('confirmed');                    // auto-confirm honoured
});

test('the donations API returns config and a checkout_url for client sites', function () {
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'don-'.uniqid(), 'domain' => 'don-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->enableFeature('donations', ['suggested_amounts' => '5, 10, 25', 'headline' => 'Keep us going']);
    app(PaymentManager::class)->fake(new FakePaymentGateway);
    $site->paymentSettings()->create(['enabled' => true, 'provider' => 'fake']);

    $this->getJson("/api/sites/{$site->name}/donate/config")->assertOk()
        ->assertJsonPath('headline', 'Keep us going')
        ->assertJsonPath('suggested', [5, 10, 25])
        ->assertJsonPath('available', true);

    $res = $this->postJson("/api/sites/{$site->name}/donate/checkout", ['amount' => 12.5, 'email' => 'd@x.test'])
        ->assertStatus(201)->json();
    expect($res['checkout_url'])->toBe('https://fake-pay.test/checkout')
        ->and($site->donations()->first()->amount_cents)->toBe(1250);
});
