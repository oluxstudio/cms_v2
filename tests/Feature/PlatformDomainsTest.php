<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformDomainsPage;
use App\Models\DomainOrder;
use App\Models\PlatformSetting;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainPurchase;
use App\Services\TwoFactor;
use App\Support\ConfigOverlay;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function dmSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

beforeEach(function () {
    config(['domains.driver' => 'fake']);
    $this->settingsBefore = (int) PlatformSetting::max('id');
});
afterEach(function () {
    PlatformSetting::where('id', '>', $this->settingsBefore)->delete();
    ConfigOverlay::refresh();
});

test('the domains page is super-only', function () {
    $this->actingAs(User::factory()->create())->get('/admin/domains')->assertForbidden();
    $this->actingAs(dmSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/domains')->assertOk()->assertSee('Domain orders');
});

test('a failed paid order can be retried and registers the domain', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'dm-'.uniqid().'.test']);
    $domain = 'retry-'.uniqid().'.co.uk';
    $order = DomainOrder::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'domain' => $domain, 'type' => 'register',
        'years' => 1, 'price_cents' => 999, 'status' => 'failed', 'error' => 'Registrar timeout',
    ]);

    Livewire::actingAs(dmSuper())->test(PlatformDomainsPage::class)->call('retry', $order->id);

    $order->refresh();
    expect($order->status)->toBe('registered')->and($order->error)->toBeNull()
        ->and($site->fresh()->domain)->toBe($domain);
});

test('a failed order can be marked refunded', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'dm-'.uniqid().'.test']);
    $order = DomainOrder::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'domain' => 'refund-'.uniqid().'.com',
        'years' => 1, 'price_cents' => 1299, 'status' => 'failed',
    ]);

    Livewire::actingAs(dmSuper())->test(PlatformDomainsPage::class)->call('markRefunded', $order->id);
    expect($order->fresh()->status)->toBe('refunded');
});

test('edited TLD prices drive checkout prices, matching the longest ending', function () {
    Livewire::actingAs(dmSuper())->test(PlatformDomainsPage::class)
        ->set('tab', 'prices')
        ->set('prices', [['tld' => 'uk', 'price' => '5.00'], ['tld' => '.co.uk', 'price' => '8.50'], ['tld' => 'dev', 'price' => '14']])
        ->call('savePrices')->assertHasNoErrors();

    $p = app(DomainPurchase::class);
    expect($p->priceFor('shop.co.uk'))->toBe(850)
        ->and($p->priceFor('shop.uk'))->toBe(500)
        ->and($p->priceFor('shop.dev'))->toBe(1400)
        ->and($p->priceFor('shop.com'))->toBeNull();

    Livewire::actingAs(dmSuper())->test(PlatformDomainsPage::class)->call('resetPrices');
    expect(app(DomainPurchase::class)->priceFor('shop.com'))->not->toBeNull();
});

test('bad price rows are refused', function () {
    Livewire::actingAs(dmSuper())->test(PlatformDomainsPage::class)
        ->set('prices', [['tld' => 'co uk', 'price' => '5'], ['tld' => 'com', 'price' => '']])
        ->call('savePrices')->assertHasErrors(['prices.0.tld', 'prices.1.price']);
});

test('a live site can be taken offline', function () {
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id, 'domain' => 'off-'.uniqid().'.com', 'live' => true, 'domain_verified_at' => now()]);

    Livewire::actingAs(dmSuper())->test(PlatformDomainsPage::class)->call('takeOffline', $site->id);
    expect($site->fresh()->live)->toBeFalse();
});
