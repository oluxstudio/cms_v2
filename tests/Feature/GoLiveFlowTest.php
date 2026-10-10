<?php

use App\Livewire\GoLivePage;
use App\Models\Alert;
use App\Models\DomainOrder;
use App\Models\Site;
use App\Models\User;
use App\Support\GoLiveChecklist;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function flowSite(array $attrs = []): array
{
    $owner = User::factory()->create();
    // Own domains are a paid-plan feature (the trial gets a free subdomain).
    \App\Models\AccountSubscription::create(['user_id' => $owner->id, 'plan' => 'starter', 'status' => 'active', 'started_at' => now()]);
    $site = Site::create($attrs + [
        'user_id' => $owner->id, 'name' => $n = 'flow-'.uniqid(),
        'domain' => $attrs['domain'] ?? '',
        'owner' => $owner->name, 'description' => 't',
    ]);

    return [$owner, $site];
}

test('the flow derives from tenant state: choose, connect, buy', function () {
    config(['publishing.dns_target' => '203.0.113.10']);

    [$owner, $site] = flowSite();
    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->assertSee('Get your web address');

    [$owner2, $site2] = flowSite(['domain' => 'own-'.uniqid().'.co.uk']);
    Livewire::actingAs($owner2)->test(GoLivePage::class, ['site' => $site2])
        ->assertSee('Connect your domain');

    [$owner3, $site3] = flowSite();
    DomainOrder::create([
        'user_id' => $owner3->id, 'site_id' => $site3->id, 'domain' => 'bought-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500, 'status' => 'registered',
        'expires_at' => now()->addYear(),
    ]);
    Livewire::actingAs($owner3)->test(GoLivePage::class, ['site' => $site3])
        ->assertSee('Registered in your name');
});

test('domain input is normalised and junk is rejected with friendly copy', function () {
    config(['publishing.dns_target' => '203.0.113.10']);
    [$owner, $site] = flowSite();

    // Existence is checked against DNS — pre-seed the cached answer so the
    // test stays hermetic (no network, unique domain every run).
    Cache::put('domain-exists:mysalon-'.($u = uniqid()).'.co.uk', true, 600);
    $c = Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->set('domain', ' https://www.MySalon-'.$u.'.co.uk/contact ')
        ->call('saveDomain');
    expect($site->fresh()->domain)->toBe('mysalon-'.$u.'.co.uk');

    $c->set('domain', 'caac')->call('saveDomain')
        ->assertSet('errorMessage', 'Enter the full address, e.g. janes-salon.co.uk');
    expect($site->fresh()->domain)->toBe('mysalon-'.$u.'.co.uk'); // unchanged
});

test('the backend refuses to go live before the domain is verified', function () {
    config(['publishing.dns_target' => '203.0.113.10']);
    [$owner, $site] = flowSite(['domain' => 'unverified-'.uniqid().'.com']);

    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->call('toggleLive')
        ->assertSet('errorMessage', 'Your domain has to pass the checks before the site can go live.');

    expect($site->fresh()->live)->toBeFalse();

    // Verified → allowed.
    $site->update(['domain_verified_at' => now()]);
    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])->call('toggleLive');
    expect($site->fresh()->live)->toBeTrue();
});

test('a missing DNS target stays out of the client UI and alerts admins', function () {
    config(['publishing.dns_target' => '']);
    [$owner, $site] = flowSite(['domain' => 'own-'.uniqid().'.co.uk']);
    Cache::forget('dns-target-missing-flagged:'.now()->toDateString());
    Alert::where('dedupe_key', 'config:dns-target:'.now()->toDateString())->delete();

    $c = Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->call('runChecks')
        ->assertSee('Domain connection is temporarily unavailable')
        ->assertDontSee('PLATFORM_DNS_TARGET');

    $alert = Alert::where('dedupe_key', 'config:dns-target:'.now()->toDateString())->first();
    expect($alert)->not->toBeNull()
        ->and($alert->audience)->toBe('admins')
        ->and($alert->level)->toBe('error');

    // Deduped on a second run.
    $c->call('runChecks');
    expect(Alert::where('dedupe_key', 'config:dns-target:'.now()->toDateString())->count())->toBe(1);
});

test('the stepper captions reflect real state: saved domain shown, buying while in flight', function () {
    [$owner, $site] = flowSite(['domain' => $d = 'caption-'.uniqid().'.co.uk']);
    $steps = collect(GoLiveChecklist::steps($site))->keyBy('key');
    expect($steps['domain']['state'])->toBe('done')
        ->and($steps['domain']['description'])->toBe($d)
        ->and($steps['domain']['label'])->toBe('Web address');

    [$owner2, $site2] = flowSite();
    DomainOrder::create([
        'user_id' => $owner2->id, 'site_id' => $site2->id, 'domain' => $bd = 'inflight-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500, 'status' => 'paid',
    ]);
    // Buying through us: Site ready → Web address → Payment → Live.
    $steps2 = collect(GoLiveChecklist::steps($site2))->keyBy('key');
    expect($steps2->keys()->all())->toBe(['template', 'domain', 'payment', 'live'])
        ->and($steps2['domain']['state'])->toBe('done')
        ->and($steps2['domain']['description'])->toBe($bd)
        ->and($steps2['payment']['state'])->toBe('working')
        ->and($steps2['payment']['description'])->toContain('registering '.$bd);
});

test('on the Buy path the stepper follows the domain search into the payment step', function () {
    [, $site] = flowSite(['template' => 'blank']);

    $search = collect(GoLiveChecklist::steps($site, 'buy', 'search'))->keyBy('key');
    expect($search->keys()->all())->toBe(['template', 'domain', 'payment', 'live'])
        ->and($search['domain']['state'])->toBe('active');

    $pay = collect(GoLiveChecklist::steps($site, 'buy', 'pay', $d = 'paying-'.uniqid().'.co.uk'))->keyBy('key');
    expect($pay['domain']['state'])->toBe('done')
        ->and($pay['domain']['description'])->toBe($d)
        ->and($pay['payment']['state'])->toBe('active');

    // An unpaid order (checkout, or a legacy pending one) is not "buying" — and never shows "Payment received".
    DomainOrder::create(['user_id' => $site->user_id, 'site_id' => $site->id, 'domain' => 'unpaid-'.uniqid().'.com',
        'type' => 'register', 'years' => 1, 'price_cents' => 1500, 'status' => 'checkout']);
    expect(collect(GoLiveChecklist::steps($site))->pluck('key')->all())->toBe(['template', 'domain', 'dns', 'live']);
    $owner = User::find($site->user_id);
    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->assertDontSee('Payment received')
        ->assertDontSee('registering');
});

test('saving a domain verifies it actually exists', function () {
    config(['publishing.dns_target' => '203.0.113.10']);
    [$owner, $site] = flowSite();

    // A domain that cannot exist → rejected with guidance.
    $ghost = 'definitely-not-registered-'.uniqid().'.com';
    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->set('domain', $ghost)
        ->call('saveDomain')
        ->assertSet('errorMessage', "We couldn't find {$ghost} on the internet — it doesn't look registered. Check the spelling, or buy it brand new from the Buy option.");
    expect($site->fresh()->domain)->toBe('');

    // An existing domain (cached answer) → saves.
    $real = 'exists-'.uniqid().'.com';
    Cache::put('domain-exists:'.$real, true, 600);
    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->set('domain', $real)
        ->call('saveDomain')
        ->assertSet('errorMessage', '');
    expect($site->fresh()->domain)->toBe($real);
});
