<?php

use App\Livewire\SitePaymentsPage;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use Livewire\Livewire;

function paymentsRailsSite(array $features = ['store', 'donations']): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'prr-'.uniqid(), 'domain' => 'prr-'.uniqid().'.test', 'owner' => 'x', 'description' => 't', 'currency' => 'gbp']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    foreach ($features as $f) {
        $site->enableFeature($f);
    }

    return [$owner, $site];
}

test('an unconnected site shows the rose connection tile, the step card and what uses payments', function () {
    [$owner, $site] = paymentsRailsSite();

    $this->actingAs($owner)->get("/{$site->name}/payments")->assertOk()
        ->assertSee('Stripe connection')->assertSee('Not connected')
        ->assertSee('Taken this month')->assertSee('Platform fee')
        ->assertSee('Start taking payments in 3 steps')
        ->assertSee('What uses payments')->assertSee('Needs setup')
        ->assertSee('Accept payments')->assertSee('Your Stripe account')->assertSee('Currency')
        ->assertSee('Related');
});

test('money taken this month is summed per enabled feature', function () {
    [$owner, $site] = paymentsRailsSite(['store', 'donations']);
    $site->donations()->create(['donor_name' => 'A', 'amount_cents' => 1500, 'currency' => 'gbp', 'status' => 'paid', 'paid_at' => now()]);
    $site->donations()->create(['donor_name' => 'B', 'amount_cents' => 900, 'currency' => 'gbp', 'status' => 'paid', 'paid_at' => now()->subMonths(2)]);
    $site->donations()->create(['donor_name' => 'C', 'amount_cents' => 400, 'currency' => 'gbp', 'status' => 'pending']);

    $c = Livewire::actingAs($owner)->test(SitePaymentsPage::class, ['site' => $site]);
    $month = $c->instance()->monthTakings;
    expect($month['rows'])->toHaveKeys(['store', 'donations'])
        ->and($month['rows'])->not->toHaveKey('invoices')
        ->and($month['rows']['donations'])->toBe(1500)
        ->and($month['total'])->toBe(1500);

    $features = collect($c->instance()->moneyFeatures)->keyBy('key');
    expect($features['store']['enabled'])->toBeTrue()
        ->and($features['invoices']['enabled'])->toBeFalse();
});

test('own API keys show account details and test mode once accepting payments', function () {
    [$owner, $site] = paymentsRailsSite();
    SitePaymentSettings::create(['site_id' => $site->id, 'provider' => 'stripe', 'enabled' => true,
        'stripe_publishable' => 'pk_test_x', 'stripe_secret' => 'sk_test_x']);

    $c = Livewire::actingAs($owner)->test(SitePaymentsPage::class, ['site' => $site->fresh()]);
    expect($c->instance()->testMode)->toBeTrue();
    $c->assertSee('Your site is taking payments')->assertSee('Own API keys')->assertSee('Test mode')
        ->assertDontSee('Start taking payments in 3 steps');
});

test('the save and switch logic is unchanged', function () {
    [$owner, $site] = paymentsRailsSite();

    $c = Livewire::actingAs($owner)->test(SitePaymentsPage::class, ['site' => $site])
        ->set('acceptPayments', true)->call('save');
    expect($c->get('errorMessage'))->toContain('before switching payments on');
    $c->assertSee('before switching payments on');
});
