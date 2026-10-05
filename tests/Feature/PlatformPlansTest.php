<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformPlansPage;
use App\Models\AccountSubscription;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\TwoFactor;
use App\Support\PlanCatalog;
use Livewire\Livewire;

function plSuper(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

// Plan rows are global: remove everything a test wrote so other tests see the file plans.
beforeEach(fn () => $this->planRowsBefore = (int) MembershipPlan::max('id'));
afterEach(function () {
    MembershipPlan::where('id', '>', $this->planRowsBefore)->delete();
    PlanCatalog::refresh();
});

test('only a verified super admin reaches the plans page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/plans')->assertForbidden();
    $this->actingAs(plSuper())->withSession([EnsureSuperAdmin::SESSION_KEY => now()])
        ->get('/admin/plans')->assertOk()->assertSee('Membership plans')->assertSee('Starter');
});

test('editing a plan changes it everywhere config is read', function () {
    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
        ->call('edit', 'starter')
        ->set('form.name', 'Starter Plus')
        ->set('form.price', '24.50')
        ->set('form.sites', 2)
        ->set('form.storage_mb', '')
        ->set('form.features', "2 sites\nForms\n\nEmail support")
        ->call('save')->assertHasNoErrors();

    PlanCatalog::refresh();
    $t = config('plans.tiers.starter');
    expect($t['name'])->toBe('Starter Plus')
        ->and($t['price_cents'])->toBe(2450)
        ->and($t['limits']['sites'])->toBe(2)
        ->and($t['limits']['storage_mb'])->toBeNull()
        ->and($t['features'])->toBe(['2 sites', 'Forms', 'Email support']);

    // Billing reads the same config: a new subscription is priced from it.
    $sub = new AccountSubscription(['plan' => 'starter']);
    expect($sub->priceFor('starter'))->toBe(2450);
});

test('a new plan can be created, hidden from pricing, and deleted while unused', function () {
    $key = 'agency_'.substr(uniqid(), -6);
    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
        ->call('create')
        ->set('form.key', $key)->set('form.name', 'Agency')->set('form.price', '99')
        ->call('save')->assertHasNoErrors();

    expect(config("plans.tiers.{$key}.price_cents"))->toBe(9900)
        ->and(PlanCatalog::publicTiers()->has($key))->toBeTrue();

    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)->call('toggleHidden', $key);
    expect(PlanCatalog::publicTiers()->has($key))->toBeFalse()
        ->and(PlanCatalog::publicTiers($key)->has($key))->toBeTrue(); // current subscribers still see it

    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)->call('startDelete', $key)->call('deletePlan');
    expect(config("plans.tiers.{$key}"))->toBeNull();
});

test('validation: duplicate keys and bad colours are refused', function () {
    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
        ->call('create')
        ->set('form.key', 'pro')->set('form.name', 'Pro again')->set('form.price', '10')
        ->call('save')->assertHasErrors('form.key');

    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
        ->call('edit', 'pro')->set('form.color', 'blue')
        ->call('save')->assertHasErrors('form.color');
});

test('trial length is editable', function () {
    Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
        ->set('trialDays', 21)->call('saveTrialDays')->assertHasNoErrors();

    PlanCatalog::refresh();
    expect(config('plans.trial_days'))->toBe(21);
});

test('deleting a plan with accounts on it moves them to a chosen plan first', function () {
    $key = 'gone_'.uniqid();
    PlanCatalog::save($key, ['name' => 'Gone soon', 'price_cents' => 1500, 'limits' => ['sites' => 1]]);
    $user = User::factory()->create();
    $user->currentSubscription()->update(['plan' => $key, 'status' => 'active']);

    $page = Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
        ->call('startDelete', $key)
        ->assertSee('Move its 1 account to')
        ->call('deletePlan')->assertHasErrors('moveTo');          // must say where they go
    expect(config("plans.tiers.{$key}"))->not->toBeNull();

    $page->set('moveTo', 'growth')->call('deletePlan')->assertHasNoErrors()->assertSet('deleting', null);
    expect(config("plans.tiers.{$key}"))->toBeNull()
        ->and($user->fresh()->currentSubscription()->plan)->toBe('growth');
});

test('a default plan can be deleted (gone from every pricing page) and restored; the trial cannot', function () {
    $before = MembershipPlan::where('key', 'starter')->value('data');
    try {
        $page = Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)
            ->call('startDelete', 'starter')->set('moveTo', 'growth')->call('deletePlan')->assertHasNoErrors();
        expect(config('plans.tiers.starter'))->toBeNull()
            ->and(PlanCatalog::publicTiers()->has('starter'))->toBeFalse()
            ->and(PlanCatalog::deletedBuiltIns())->toHaveKey('starter');
        $page->assertSee('Deleted plans');

        Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)->call('restorePlan', 'starter');
        expect(config('plans.tiers.starter'))->not->toBeNull()
            ->and(config('plans.tiers.starter.hidden'))->toBeTrue()          // back hidden — Show when ready
            ->and(PlanCatalog::deletedBuiltIns())->not->toHaveKey('starter');

        Livewire::actingAs(plSuper())->test(PlatformPlansPage::class)->call('startDelete', 'trial')->assertStatus(422);
    } finally {
        $before === null ? MembershipPlan::where('key', 'starter')->delete() : MembershipPlan::where('key', 'starter')->update(['data' => $before]);
        PlanCatalog::refresh();
    }
});

test('every plan action re-checks super admin, not just the page load', function () {
    $component = Livewire::actingAs(plSuper())->test(PlatformPlansPage::class);
    $this->actingAs(User::factory()->create());   // session changes hands mid-way
    $component->call('startDelete', 'pro')->assertForbidden();
});
