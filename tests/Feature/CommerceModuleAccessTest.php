<?php

use App\Livewire\MarketplacePage;
use App\Models\AccountSubscription;
use App\Models\Site;
use App\Models\User;
use Livewire\Livewire;

test('every commerce module is basic-tier so the free/starter plans can enable them', function () {
    foreach (['store', 'invoices', 'donations', 'bookings', 'estimator'] as $key) {
        expect(config("modules.tiers.$key"))->toBe('basic')
            ->and(config("features.$key.tier"))->toBe('basic');
    }
});

test('a non-premium plan can enable all commerce features from the marketplace', function () {
    $user = User::factory()->create();
    // Starter = cheapest plan WITHOUT premium module access.
    AccountSubscription::create(['user_id' => $user->id, 'plan' => 'starter', 'status' => 'active', 'started_at' => now()]);
    $site = Site::factory()->create(['user_id' => $user->id]);
    expect($user->currentSubscription()->allowsPremium())->toBeFalse();

    $lw = Livewire::actingAs($user)->test(MarketplacePage::class, ['site' => $site]);
    foreach (['store', 'invoices', 'bookings', 'estimator'] as $key) {
        $lw->call('toggle', $key)->assertNotDispatched('upgrade-required');
        expect($site->fresh()->hasFeature($key))->toBeTrue();
    }
});
