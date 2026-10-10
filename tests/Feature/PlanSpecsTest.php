<?php

use App\Livewire\SubscriptionPage;
use App\Models\User;
use App\Support\PlanSpecs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

function planSpecsRow(string $plan, string $section, string $label): array
{
    return PlanSpecs::flat($plan)[$section][$label];
}

it('describes each plan from the limits the app enforces', function () {
    // Free: no own domain, badge shown, 20 bookings, no invoices
    expect(planSpecsRow('free', 'Websites', 'Your own domain')['ok'])->toBeFalse()
        ->and(planSpecsRow('free', 'Websites', 'No “Made with Olux” badge')['ok'])->toBeFalse()
        ->and(planSpecsRow('free', 'Bookings & payments', 'Online bookings')['value'])->toBe(config('plans.tiers.free.limits.bookings_month').' a month')
        ->and(planSpecsRow('free', 'Bookings & payments', 'Invoices')['value'])->toBe('Not included');

    // Starter: own domain, no free domain; Growth: free .co.uk; Pro: any domain, no fee
    expect(planSpecsRow('starter', 'Websites', 'Your own domain')['ok'])->toBeTrue()
        ->and(planSpecsRow('starter', 'Websites', 'Free domain for year 1')['ok'])->toBeFalse()
        ->and(planSpecsRow('growth', 'Websites', 'Free domain for year 1')['value'])->toBe('.co.uk domain')
        ->and(planSpecsRow('pro', 'Websites', 'Free domain for year 1')['value'])->toBe('Any domain')
        ->and(planSpecsRow('pro', 'Bookings & payments', 'Olux fee on online payments')['value'])->toBe('None');
});

it('holds an ended trial and a cancelled plan to the Free plan rules', function () {
    $user = User::factory()->create();
    $sub = $user->currentSubscription();

    $sub->update(['plan' => 'trial', 'status' => 'trialing', 'trial_ends_at' => now()->addDays(3)]);
    expect($sub->fresh()->allowsCustomDomain())->toBe((bool) config('plans.tiers.trial.limits.custom_domain'))
        ->and($sub->fresh()->limit('bookings_month'))->toBeNull();

    $sub->update(['trial_ends_at' => now()->subDay()]);
    $expired = $sub->fresh();
    expect($expired->lapsed())->toBeTrue()
        ->and($expired->allowsCustomDomain())->toBeFalse()
        ->and($expired->limit('bookings_month'))->toBe(config('plans.tiers.free.limits.bookings_month'))
        ->and($expired->limit('invoices_month'))->toBe(0);

    $sub->update(['plan' => 'pro', 'status' => 'cancelled', 'trial_ends_at' => null]);
    expect($sub->fresh()->allowsCustomDomain())->toBeFalse()
        ->and($sub->fresh()->tier()['name'])->toBe(config('plans.tiers.free.name'));

    $sub->update(['status' => 'active']);
    expect($sub->fresh()->allowsCustomDomain())->toBeTrue();
});

it('shows every spec for a plan and a full comparison on the subscription page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(SubscriptionPage::class)
        ->assertSee('Compare every spec')
        ->assertSee('Your own domain')
        ->assertSee('Staff booking calendars')
        ->call('viewPlan', 'free', 'specs')
        ->assertSet('planTab', 'specs')
        ->assertSee('Not included — free Olux address only')
        ->assertSee('Badge shown on your site')
        ->call('viewPlan', 'pro')
        ->assertSet('planTab', 'about')
        ->assertSee('About this plan');
});

it('still applies the Free rules to lapsed accounts when Free is not on sale', function () {
    $user = User::factory()->create();
    $tiers = config('plans.tiers');
    unset($tiers['free']);
    config(['plans.tiers' => $tiers]); // admin removed Free from sale

    $sub = $user->currentSubscription();
    $sub->update(['plan' => 'trial', 'status' => 'trialing', 'trial_ends_at' => now()->subDay()]);

    expect($sub->fresh()->allowsCustomDomain())->toBeFalse()
        ->and($sub->fresh()->limit('bookings_month'))->toBe(20);
});
