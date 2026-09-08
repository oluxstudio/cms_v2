<?php

use App\Livewire\SitePaymentsPage;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use Livewire\Livewire;

function paymentsPageSite(): array
{
    $owner = User::factory()->create();
    $site = Site::create(['user_id' => $owner->id, 'name' => 'pp-'.uniqid(), 'domain' => 'pp-'.uniqid().'.test', 'owner' => 'x', 'description' => 't']);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner, $site];
}

test('the payments page renders for the owner, is linked from the nav, and is forbidden to strangers', function () {
    [$owner, $site] = paymentsPageSite();

    $this->actingAs($owner)->get("/{$site->name}/payments")->assertOk()
        ->assertSee('Accept payments')->assertSee('Your Stripe account')->assertSee('Currency');
    $this->actingAs($owner)->get("/{$site->name}/dashboard")->assertSee(url("{$site->name}/payments"));
    $this->actingAs(User::factory()->create())->get("/{$site->name}/payments")->assertForbidden();
});

test('saving with the switch on but nothing configured refuses; with keys it enables', function () {
    [$owner, $site] = paymentsPageSite();

    $c = Livewire::actingAs($owner)->test(SitePaymentsPage::class, ['site' => $site])
        ->set('acceptPayments', true)->call('save');
    expect($c->get('errorMessage'))->toContain('before switching payments on')
        ->and($site->fresh()->paymentsEnabled())->toBeFalse();

    $c->set('pubKey', 'pk_test_x')->set('secretKey', 'sk_test_x')
        ->set('acceptPayments', true)->call('save');
    $ps = SitePaymentSettings::where('site_id', $site->id)->first();
    expect($ps->enabled)->toBeTrue()->and($ps->provider)->toBe('stripe')
        ->and($site->fresh()->paymentsEnabled())->toBeTrue();
});

test('the marketplace no longer hosts the payments drawer and links to the page instead', function () {
    [$owner, $site] = paymentsPageSite();
    $this->actingAs($owner)->get("/{$site->name}/marketplace")
        ->assertSee(url("{$site->name}/payments"))
        ->assertDontSee('Webhook signing secret');
});
