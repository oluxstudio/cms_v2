<?php

use App\Livewire\DomainSearch;
use App\Models\Alert;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainPurchase;
use App\Services\Domains\Registrar;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        ->assertRedirect(route('site.publish', $site->name));

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

        public function renew(string $d, int $y): void {}
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

// ─── Phase 3: renewals ───────────────────────────────────────────

test('renewing without Stripe extends the expiry instantly', function () {
    config(['domains.driver' => 'fake', 'services.stripe_platform.secret' => null]);
    [$owner, $site] = domainSite();

    $registered = DomainOrder::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'domain' => $d = 'renewme-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500,
        'status' => 'registered', 'expires_at' => now()->addDays(20),
    ]);
    $site->update(['domain' => $d]);

    $back = app(DomainPurchase::class)
        ->startRenewal($owner, $registered, 'http://x/back');

    expect($back)->toBe('http://x/back');
    $renewal = DomainOrder::where('domain', $d)->where('type', 'renew')->first();
    expect($renewal->status)->toBe('registered')
        ->and($renewal->expires_at->toDateString())->toBe(now()->addDays(20)->addYear()->toDateString())
        ->and($registered->fresh()->expires_at->toDateString())->toBe(now()->addDays(20)->addYear()->toDateString());
});

test('only registered orders can be renewed', function () {
    config(['domains.driver' => 'fake', 'services.stripe_platform.secret' => null]);
    [$owner, $site] = domainSite();
    $pending = DomainOrder::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'domain' => 'nope-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500, 'status' => 'pending',
    ]);

    app(DomainPurchase::class)->startRenewal($owner, $pending, 'http://x');
})->throws(HttpException::class);

test('the renewal sweep raises deduped expiry alerts', function () {
    [$owner, $site] = domainSite();
    DomainOrder::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'domain' => $d = 'soon-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500,
        'status' => 'registered', 'expires_at' => now()->addDays(5),
    ]);
    $site->update(['domain' => $d]);

    $this->artisan('domains:renewal-sweep')->assertSuccessful();
    $this->artisan('domains:renewal-sweep')->assertSuccessful(); // dedupe: still one alert

    $alerts = Alert::where('site_id', $site->id)->where('type', 'domain')->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->level)->toBe('error')          // ≤7 days → urgent
        ->and($alerts->first()->title)->toContain($d);
});

test('the sweep ignores domains the site no longer uses', function () {
    [$owner, $site] = domainSite();
    DomainOrder::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'domain' => 'old-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500,
        'status' => 'registered', 'expires_at' => now()->addDays(5),
    ]);
    // site->domain stays {name}.test — the expiring order is for a domain not in use

    $this->artisan('domains:renewal-sweep')->assertSuccessful();

    expect(Alert::where('site_id', $site->id)->where('type', 'domain')->count())->toBe(0);
});
