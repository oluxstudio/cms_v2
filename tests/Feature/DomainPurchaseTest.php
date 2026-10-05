<?php

use App\Livewire\DomainSearch;
use App\Models\Alert;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainPurchase;
use App\Services\Domains\Registrar;
use App\Services\PlatformBilling;
use Livewire\Livewire;
use Stripe\StripeClient;
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

// ─── On-page payment (Stripe Payment Element) ────────────────────

/** A PlatformBilling whose Stripe client is an in-memory fake; returns the call log. */
function fakeStripeBilling(string $intentStatus = 'succeeded', string $subStatus = 'active'): ArrayObject
{
    $log = new ArrayObject;
    $o = fn (array $a) => json_decode(json_encode($a));
    $svc = fn (array $methods) => new class($methods)
    {
        public function __construct(private array $m) {}

        public function __call($name, $args)
        {
            return ($this->m[$name])(...$args);
        }
    };
    $client = (object) [
        'customers' => $svc(['create' => function ($p) use ($log, $o) {
            $log[] = ['customers.create', $p];

            return $o(['id' => 'cus_fake']);
        }]),
        'products' => $svc([
            'retrieve' => fn ($id) => $o(['id' => $id]),
            'create' => fn ($p) => $o(['id' => $p['id']]),
        ]),
        'paymentIntents' => $svc([
            'create' => function ($p) use ($log, $o) {
                $log[] = ['paymentIntents.create', $p];

                return $o(['id' => 'pi_fake', 'client_secret' => 'pi_fake_secret_x', 'status' => 'requires_payment_method', 'amount' => $p['amount']]);
            },
            'retrieve' => fn ($id) => $o(['id' => $id, 'client_secret' => 'pi_fake_secret_x', 'status' => $intentStatus, 'amount' => 1200, 'customer' => 'cus_fake']),
        ]),
        'subscriptions' => $svc([
            'create' => function ($p) use ($log, $o) {
                $log[] = ['subscriptions.create', $p];

                return $o(['id' => 'sub_fake', 'latest_invoice' => ['confirmation_secret' => ['client_secret' => 'pi_sub_secret_x']]]);
            },
            'retrieve' => fn ($id) => $o(['id' => $id, 'status' => $subStatus, 'customer' => 'cus_fake',
                'latest_invoice' => ['status' => $subStatus === 'active' ? 'paid' : 'open', 'confirmation_secret' => ['client_secret' => 'pi_sub_secret_x']]]),
        ]),
    ];
    $stripe = new class($client) extends StripeClient
    {
        public function __construct(private object $fake)
        {
            parent::__construct(['api_key' => 'sk_test_fake']);
        }

        public function __get($name)
        {
            return $this->fake->{$name};
        }
    };
    app()->instance(PlatformBilling::class, new class($stripe) extends PlatformBilling
    {
        public function __construct(private StripeClient $fake) {}

        public function configured(): bool
        {
            return true;
        }

        public function client(): StripeClient
        {
            return $this->fake;
        }
    });
    app()->forgetInstance(DomainPurchase::class);

    return $log;
}

test('choosing a domain opens the on-page payment step; nothing is registered until Stripe says it is paid', function () {
    config(['domains.driver' => 'fake']);
    [$owner, $site] = domainSite();
    $owner->currentSubscription()->update(['plan' => 'starter', 'status' => 'active']);   // no plan to buy, no free domain
    $log = fakeStripeBilling('requires_payment_method');

    $lw = Livewire::actingAs($owner)->test(DomainSearch::class, ['site' => $site])
        ->set('query', $label = 'pay-'.uniqid())->call('search')
        ->call('select', $d = $label.'.co.uk')
        ->assertDispatched('go-live-stage', stage: 'chosen', domain: $d)
        ->call('continueToCheckout')
        ->assertDispatched('go-live-stage', stage: 'pay', domain: $d)
        ->assertSet('clientSecret', 'pi_fake_secret_x')
        ->assertSet('payTotalCents', 1200)
        ->assertSee('Pay £12.00')
        ->assertSee('Due today');

    $order = DomainOrder::where('site_id', $site->id)->sole();
    expect($order->status)->toBe('checkout')
        ->and($order->stripe_payment_intent_id)->toBe('pi_fake')
        ->and(collect($log)->firstWhere(0, 'paymentIntents.create')[1]['metadata']['order_id'])->toBe($order->id);

    // Not paid yet → an honest message, no registration.
    $lw->call('paymentConfirmed')->assertSet('payError', fn ($m) => str_contains($m, "didn't go through"));
    expect($order->fresh()->status)->toBe('checkout')->and($site->fresh()->live)->toBeFalse();

    // Going back and paying again reuses the same order.
    $lw->call('backToSearch')->assertSet('payOrderId', null)->call('continueToCheckout');
    expect(DomainOrder::where('site_id', $site->id)->count())->toBe(1);

    // Paid → registered, connected and live.
    fakeStripeBilling('succeeded');
    $lw->call('paymentConfirmed')->assertRedirect(route('site.publish', $site->name));
    expect($order->fresh()->status)->toBe('registered')
        ->and($site->fresh()->domain)->toBe($d)
        ->and($site->fresh()->live)->toBeTrue();
});

test('domain + hosting plan is one payment: a subscription whose first invoice carries the domain', function () {
    config(['domains.driver' => 'fake']);
    [$owner, $site] = domainSite();
    $log = fakeStripeBilling(subStatus: 'active');

    Livewire::actingAs($owner)->test(DomainSearch::class, ['site' => $site])
        ->set('query', $label = 'plan-'.uniqid())->call('search')
        ->set('plan', 'starter')
        ->call('buy', $d = $label.'.co.uk')
        ->assertSet('clientSecret', 'pi_sub_secret_x')
        ->assertSee('hosting plan')
        ->call('paymentConfirmed')
        ->assertRedirect(route('site.publish', $site->name));

    $create = collect($log)->firstWhere(0, 'subscriptions.create')[1];
    expect($create['payment_behavior'])->toBe('default_incomplete')
        ->and($create['add_invoice_items'][0]['price_data']['unit_amount'])->toBe(1200)
        ->and($create['customer'])->toBe('cus_fake');

    $sub = $owner->fresh()->currentSubscription();
    expect($sub->plan)->toBe('starter')->and($sub->status)->toBe('active')
        ->and($sub->stripe_subscription_id)->toBe('sub_fake')
        ->and(DomainOrder::where('domain', $d)->value('status'))->toBe('registered');
});

test('the webhook fulfils a paid domain order, and the bank-redirect return confirms it too', function () {
    config(['domains.driver' => 'fake']);
    [$owner, $site] = domainSite();
    fakeStripeBilling('succeeded');

    $order = DomainOrder::create(['user_id' => $owner->id, 'site_id' => $site->id, 'domain' => $d = 'hook-'.uniqid().'.co.uk',
        'type' => 'register', 'years' => 1, 'price_cents' => 1200, 'status' => 'checkout', 'stripe_payment_intent_id' => 'pi_hook']);
    app(DomainPurchase::class)->fulfilFromPaymentIntent(json_decode(json_encode(['id' => 'pi_other', 'metadata' => ['kind' => 'domain', 'order_id' => $order->id]])));
    expect($order->fresh()->status)->toBe('checkout');   // id mismatch → ignored
    app(DomainPurchase::class)->fulfilFromPaymentIntent(json_decode(json_encode(['id' => 'pi_hook', 'customer' => 'cus_fake', 'metadata' => ['kind' => 'domain', 'order_id' => $order->id]])));
    expect($order->fresh()->status)->toBe('registered');

    $o2 = DomainOrder::create(['user_id' => $owner->id, 'site_id' => $site->id, 'domain' => 'return-'.uniqid().'.co.uk',
        'type' => 'register', 'years' => 1, 'price_cents' => 1200, 'status' => 'checkout', 'stripe_payment_intent_id' => 'pi_ret']);
    $this->actingAs($owner)->get(route('site.domain.success', $site).'?order='.$o2->id.'&payment_intent=pi_ret')
        ->assertRedirect(route('site.publish', $site->name));
    expect($o2->fresh()->status)->toBe('registered');
});

test('renewals are paid on the page too', function () {
    config(['domains.driver' => 'fake']);
    [$owner, $site] = domainSite();
    $registered = DomainOrder::create(['user_id' => $owner->id, 'site_id' => $site->id, 'domain' => $d = 'renewpay-'.uniqid().'.co.uk',
        'type' => 'register', 'years' => 1, 'price_cents' => 1200, 'status' => 'registered', 'expires_at' => now()->addDays(20)]);
    $site->update(['domain' => $d]);
    fakeStripeBilling('succeeded');

    Livewire::actingAs($owner)->test(DomainSearch::class, ['site' => $site])
        ->call('renew', $registered->id)
        ->assertSet('clientSecret', 'pi_fake_secret_x')
        ->assertSee('renewal')
        ->call('paymentConfirmed')
        ->assertRedirect(route('site.publish', $site->name));

    expect($registered->fresh()->expires_at->toDateString())->toBe(now()->addDays(20)->addYear()->toDateString());
});
