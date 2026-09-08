<?php

use App\Livewire\DomainSearch;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainPurchase;
use App\Services\Domains\Registrar;
use Livewire\Livewire;

function domainSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => $n = 'dom-'.uniqid(), 'domain' => $n.'.test', 'owner' => $owner->name, 'description' => 't']);

    return [$owner, $site];
}

test('search lists every offered TLD with price and availability', function () {
    config(['domains.driver' => 'fake']);
    [$owner, $site] = domainSite();
    Site::create(['user_id' => $owner->id, 'name' => 'other-'.uniqid(), 'domain' => 'janes.com', 'owner' => 'x', 'description' => 't']);

    $rows = app(DomainPurchase::class)->search('https://www.Janes.com/x');
    expect($rows)->toHaveCount(count(config('domains.tlds')));
    $byDomain = collect($rows)->keyBy('domain');
    expect($byDomain['janes.co.uk']['available'])->toBeTrue()
        ->and($byDomain['janes.co.uk']['price_cents'])->toBe(1200)
        ->and($byDomain['janes.com']['available'])->toBeFalse()
        ->and($byDomain['janes.com']['taken_here'])->toBeTrue();

    expect(collect(app(DomainPurchase::class)->search('taken-name'))->every(fn ($r) => ! $r['available']))->toBeTrue();
});

test('buying without Stripe configured registers instantly, activates the plan and puts the site live', function () {
    config(['domains.driver' => 'fake', 'services.stripe_platform.secret' => null, 'publishing.dns_target' => '203.0.113.10']);
    [$owner, $site] = domainSite();
    expect($owner->currentSubscription()->plan)->toBe('trial');

    Livewire::actingAs($owner)
        ->test(DomainSearch::class, ['site' => $site])
        ->set('query', $label = 'salon-'.uniqid())
        ->call('search')
        ->assertSee($label.'.co.uk')
        ->set('plan', 'pro')
        ->call('buy', $label.'.co.uk')
        ->assertRedirect(route('site.publish', $site->id));

    $order = DomainOrder::where('site_id', $site->id)->first();
    expect($order->status)->toBe('registered')
        ->and($order->plan)->toBe('pro')
        ->and($order->registrar_ref)->toStartWith('fake-')
        ->and($order->expires_at)->not->toBeNull();

    $site->refresh();
    expect($site->domain)->toBe($label.'.co.uk')
        ->and($site->live)->toBeTrue()
        ->and($site->domain_verified_at)->not->toBeNull();

    $sub = $owner->fresh()->currentSubscription();
    expect($sub->plan)->toBe('pro')->and($sub->status)->toBe('active');

    // The domain is now unavailable to everyone else.
    expect(collect(app(DomainPurchase::class)->search($label))->firstWhere('domain', $label.'.co.uk')['available'])->toBeFalse();
});

test('fulfilment is idempotent and a registrar failure is recorded, not hidden', function () {
    config(['domains.driver' => 'fake', 'services.stripe_platform.secret' => null]);
    [$owner, $site] = domainSite();
    $svc = app(DomainPurchase::class);

    $svc->start($owner, $site, $d1 = 'twice-'.uniqid().'.com', null, '/back');
    $order = DomainOrder::where('domain', $d1)->first();
    $svc->fulfil($order);
    expect(DomainOrder::where('site_id', $site->id)->count())->toBe(1)->and($order->fresh()->status)->toBe('registered');

    $failing = new class implements Registrar
    {
        public function available(string $l, array $t): array
        {
            return array_fill_keys(array_map(fn ($x) => "$l.$x", $t), true);
        }

        public function register(string $d, array $c, int $y): string
        {
            throw new RuntimeException('balance too low');
        }

        public function pointAt(string $d, string $t): void {}
    };
    app()->instance(Registrar::class, $failing);
    app()->forgetInstance(DomainPurchase::class);
    app(DomainPurchase::class)->start($owner, $site, $d2 = 'broken-'.uniqid().'.com', null, '/back');
    $o = DomainOrder::where('domain', $d2)->first();
    expect($o->status)->toBe('failed')->and($o->error)->toContain('balance too low')
        ->and($site->fresh()->domain)->toBe($d1);
});

test('non-owners cannot buy domains for a site', function () {
    [$owner, $site] = domainSite();
    Livewire::actingAs(User::factory()->create())
        ->test(DomainSearch::class, ['site' => $site])
        ->assertForbidden();
});
