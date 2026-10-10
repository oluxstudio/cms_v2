<?php

use App\Livewire\SubscriptionPage;
use App\Models\User;
use App\Services\PlatformBilling;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Stripe\Exception\CardException;

uses(DatabaseTransactions::class);

beforeEach(fn () => Mail::fake());

function fakeBilling(array $methods): void
{
    $mock = Mockery::mock(PlatformBilling::class)->makePartial();
    foreach ($methods as $name => $value) {
        $mock->shouldReceive($name)->andReturnUsing(is_callable($value) ? $value : fn () => $value);
    }
    app()->instance(PlatformBilling::class, $mock);
}

test('choosing a paid plan opens the card form on the page — no redirect', function () {
    $user = User::factory()->create();
    fakeBilling([
        'configured' => true, 'inlineConfigured' => true, 'switchPaidPlan' => false, 'downgradeBlocker' => null,
        'preparePlanPayment' => ['subscription' => 'sub_123', 'secret' => 'pi_123_secret_x', 'amount' => 1900],
    ]);

    Livewire::actingAs($user)->test(SubscriptionPage::class)
        ->call('viewPlan', 'starter')
        ->assertSee('Get started with Starter')
        ->call('choose', 'starter')
        ->assertNoRedirect()
        ->assertSet('payPlan', 'starter')
        ->assertSet('paySubscription', 'sub_123')
        ->assertSet('paySecret', 'pi_123_secret_x')
        ->assertSee('Pay £19.00 and start Starter')
        ->assertSee('Secured by Stripe');
});

test('a confirmed payment activates the plan; a failed one explains why', function () {
    $user = User::factory()->create();
    $state = 'active';
    fakeBilling([
        'configured' => true, 'inlineConfigured' => true, 'switchPaidPlan' => false, 'downgradeBlocker' => null,
        'preparePlanPayment' => ['subscription' => 'sub_9', 'secret' => 'sec', 'amount' => 1900],
        'completePlanPayment' => function () use (&$state) {
            return $state;
        },
    ]);

    $page = Livewire::actingAs($user)->test(SubscriptionPage::class)->call('choose', 'starter');

    $state = 'unpaid';
    $page->call('paymentConfirmed')->assertSet('paidPlan', null)->assertSee('didn&#039;t go through', false);

    $state = 'active';
    $page->call('paymentConfirmed')->assertSet('paidPlan', 'starter')->assertSee("You're on Starter", false);
});

test('without the publishable key it falls back to hosted Checkout', function () {
    $user = User::factory()->create();
    fakeBilling(['configured' => true, 'inlineConfigured' => false, 'switchPaidPlan' => false, 'downgradeBlocker' => null,
        'checkoutUrl' => 'https://checkout.stripe.test/s/abc']);

    Livewire::actingAs($user)->test(SubscriptionPage::class)
        ->call('choose', 'starter')
        ->assertRedirect('https://checkout.stripe.test/s/abc');
});

test('the invoice.paid webhook activates an on-page plan subscription', function () {
    $user = User::factory()->create();
    $billing = Mockery::mock(PlatformBilling::class)->makePartial();
    $billing->fulfilPlanInvoice((object) [
        'customer' => 'cus_1',
        'parent' => (object) ['subscription_details' => (object) [
            'subscription' => 'sub_77',
            'metadata' => (object) ['kind' => 'plan', 'user_id' => $user->id, 'plan' => 'growth'],
        ]],
    ]);

    $sub = $user->fresh()->currentSubscription();
    expect($sub->plan)->toBe('growth')->and($sub->status)->toBe('active')
        ->and($sub->stripe_subscription_id)->toBe('sub_77');
});

test('plan cards follow the public pricing section', function () {
    Livewire::actingAs(User::factory()->create())->test(SubscriptionPage::class)
        ->assertSee('Start free, grow when you do')->assertSee('Most popular')->assertSee('Get started')
        ->assertSee('Your plan');
});

test('a declined saved card on upgrade leaves the plan alone and opens the card form', function () {
    $user = User::factory()->create();
    fakeBilling([
        'configured' => true, 'inlineConfigured' => true, 'downgradeBlocker' => null,
        'switchPaidPlan' => fn () => throw CardException::factory('Your card was declined.', 402),
        'preparePlanPayment' => ['subscription' => 'sub_new', 'secret' => 'sec_new', 'amount' => 3900],
    ]);
    $before = $user->currentSubscription()->plan;

    Livewire::actingAs($user)->test(SubscriptionPage::class)
        ->call('choose', 'growth')
        ->assertDispatched('toast', level: 'error', title: 'Card declined')
        ->assertSet('paySecret', 'sec_new')
        ->assertSet('payPlan', 'growth');

    expect($user->fresh()->currentSubscription()->plan)->toBe($before);
});

test('a paid plan is never switched on without payment outside local development', function () {
    $user = User::factory()->create();
    fakeBilling(['configured' => false, 'inlineConfigured' => false, 'downgradeBlocker' => null]);
    $before = $user->currentSubscription()->plan;
    app()->detectEnvironment(fn () => 'production');

    try {
        Livewire::actingAs($user)->test(SubscriptionPage::class)
            ->call('choose', 'pro')
            ->assertNoRedirect()
            ->assertDispatched('toast', level: 'error', title: 'Payment unavailable');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect($user->fresh()->currentSubscription()->plan)->toBe($before);
});
