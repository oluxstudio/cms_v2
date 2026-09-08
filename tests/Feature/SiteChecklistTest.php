<?php

use App\Models\Service;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use App\Support\SiteChecklist;

function checklistSite(): Site
{
    $owner = User::factory()->create();

    return Site::create(['user_id' => $owner->id, 'name' => 'chk-'.uniqid(), 'domain' => 'chk-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
}

test('a fresh site has an incomplete checklist and steps flip as real data appears', function () {
    $site = checklistSite();
    $byKey = fn () => collect(SiteChecklist::steps($site->fresh()))->keyBy('key');

    expect(SiteChecklist::complete($site))->toBeFalse()
        ->and($byKey()['branding']['done'])->toBeFalse()
        ->and($byKey()['payments']['done'])->toBeFalse()
        ->and($byKey()['live']['done'])->toBeFalse()
        ->and($byKey()->has('services'))->toBeFalse(); // bookings feature off → step hidden

    $site->setAttr('email.logo', 'https://cdn.example/logo.png');
    expect($byKey()['branding']['done'])->toBeTrue();

    $site->enableFeature('bookings');
    expect($byKey()['services']['done'])->toBeFalse();
    Service::create(['site_id' => $site->id, 'name' => 'Cut', 'slug' => 'cut', 'kind' => 'slot', 'duration_min' => 30, 'price_cents' => 2000, 'currency' => 'gbp', 'is_active' => true, 'sort' => 0]);
    expect($byKey()['services']['done'])->toBeTrue();

    SitePaymentSettings::create(['site_id' => $site->id, 'enabled' => true, 'stripe_publishable' => 'pk_test_x', 'stripe_secret' => 'sk_test_x']);
    expect($byKey()['payments']['done'])->toBeTrue();

    $site->update(['live' => true, 'domain_verified_at' => now()]);
    expect($byKey()['live']['done'])->toBeTrue();

    $site->user->currentSubscription()->update(['plan' => 'pro', 'status' => 'active']);
    expect($byKey()['plan']['done'])->toBeTrue()
        ->and(SiteChecklist::complete($site->fresh()))->toBeTrue()
        ->and(SiteChecklist::progress($site->fresh())['pct'])->toBe(100);
});
