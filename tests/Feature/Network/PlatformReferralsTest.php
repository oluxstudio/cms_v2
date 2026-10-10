<?php

use App\Http\Middleware\EnsureSuperAdmin;
use App\Livewire\PlatformReferralsPage;
use App\Models\Site;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralEvent;
use App\Modules\Network\Models\ReferralPayout;
use App\Modules\Network\NetworkException;
use App\Services\TwoFactor;
use Livewire\Livewire;

function netSuperAdmin(): User
{
    $user = User::factory()->create(['is_super' => true]);
    app(TwoFactor::class)->issueSecret($user);
    $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_enabled' => true])->save();

    return $user->refresh();
}

function netSuperSite(string $name = 'Biz'): Site
{
    $slug = 'netsuper-'.uniqid();

    return Site::create(['user_id' => User::factory()->create()->id, 'name' => $name.' '.$slug, 'domain' => $slug.'.test', 'owner' => 'x', 'description' => 't']);
}

function netSuperReferral(Site $from, Site $to, array $attrs = []): Referral
{
    return Referral::create(array_merge([
        'reference' => 'R'.strtoupper(substr(uniqid(), -8)),
        'from_site_id' => $from->id, 'to_site_id' => $to->id,
        'customer_name' => 'Cust '.uniqid(), 'customer_email' => uniqid().'@example.test',
        'status' => 'shared', 'fee_cents' => 2000, 'currency' => 'gbp', 'olux_cut_pct' => 3,
    ], $attrs));
}

test('non-super users are forbidden', function () {
    $this->actingAs(User::factory()->create())->get('/admin/referrals')->assertForbidden();
    Livewire::actingAs(User::factory()->create())->test(PlatformReferralsPage::class)->assertForbidden();
});

test('the page renders network stats for super admins', function () {
    $a = netSuperSite('Sparks');
    $b = netSuperSite('Plumbs');
    NetworkProfile::create(['site_id' => $a->id, 'business_type' => 'electrician', 'area' => 'Leeds', 'fee_cents' => 2000, 'terms_accepted_at' => now(), 'terms_version' => config('network.terms_version')]);
    NetworkProfile::create(['site_id' => $b->id, 'business_type' => 'plumber', 'area' => 'York', 'fee_cents' => 1500, 'terms_accepted_at' => now(), 'terms_version' => config('network.terms_version')]);
    $collected = netSuperReferral($a, $b, ['status' => 'collected']);
    netSuperReferral($a, $b, ['status' => 'disputed', 'disputed_at' => now(), 'dispute_reason' => 'Never a customer of ours', 'converted_via' => 'invoice:abc']);
    ReferralPayout::create(['referral_id' => $collected->id, 'site_id' => $a->id, 'gross_cents' => 2000, 'olux_cents' => 60, 'net_cents' => 1940, 'status' => 'failed', 'failure' => 'No connect account']);

    $admin = netSuperAdmin();
    $this->actingAs($admin)->withSession([EnsureSuperAdmin::SESSION_KEY => now()])->get('/admin/referrals')
        ->assertOk()->assertSee('Referrals')->assertSee('Never a customer of ours')->assertSee('invoice:abc');

    Livewire::actingAs($admin)->test(PlatformReferralsPage::class)
        ->assertViewHas('stats', fn ($s) => $s['members'] >= 2 && $s['disputes'] >= 1 && $s['failed'] >= 1 && $s['revenue'] >= 60)
        ->call('setTab', 'payouts')->assertSee('No connect account')
        ->call('setTab', 'members')->assertSee('Leeds')
        ->call('setTab', 'referrals')->set('q', $collected->reference)->assertSee($collected->customer_name);
});

test('uphold and reject call the referral service with the note', function () {
    $r = netSuperReferral(netSuperSite(), netSuperSite(), ['status' => 'disputed', 'disputed_at' => now(), 'dispute_reason' => 'nope']);
    ReferralEvent::create(['referral_id' => $r->id, 'type' => 'disputed', 'data' => ['reason' => 'nope']]);
    $admin = netSuperAdmin();

    $calls = [];
    $mock = Mockery::mock(ReferralService::class);
    $mock->shouldReceive('resolveDispute')->twice()->andReturnUsing(function ($ref, $user, $outcome, $note) use (&$calls, $admin) {
        expect($user->id)->toBe($admin->id);
        $calls[] = [$ref->id, $outcome, $note];

        return $ref;
    });
    app()->instance(ReferralService::class, $mock);

    Livewire::actingAs($admin)->test(PlatformReferralsPage::class)
        ->set("notes.{$r->id}", 'Customer paid the invoice')
        ->call('resolve', $r->id, 'upheld')
        ->call('resolve', $r->id, 'rejected')
        ->call('resolve', $r->id, 'bogus');

    expect($calls)->toBe([[$r->id, 'upheld', 'Customer paid the invoice'], [$r->id, 'rejected', null]]);
});

test('a NetworkException from the service is shown, not thrown', function () {
    $r = netSuperReferral(netSuperSite(), netSuperSite(), ['status' => 'disputed', 'disputed_at' => now()]);
    $mock = Mockery::mock(ReferralService::class);
    $mock->shouldReceive('resolveDispute')->once()->andThrow(new NetworkException('Already resolved.'));
    app()->instance(ReferralService::class, $mock);

    Livewire::actingAs(netSuperAdmin())->test(PlatformReferralsPage::class)
        ->call('resolve', $r->id, 'upheld')
        ->assertDispatched('toast', message: 'Already resolved.');
});

test('retry calls payout and retry all calls retryPayouts', function () {
    $a = netSuperSite();
    $r = netSuperReferral($a, netSuperSite(), ['status' => 'collected']);
    $po = ReferralPayout::create(['referral_id' => $r->id, 'site_id' => $a->id, 'gross_cents' => 2000, 'olux_cents' => 60, 'net_cents' => 1940, 'status' => 'failed', 'failure' => 'boom']);

    $mock = Mockery::mock(ReferralBillingService::class);
    $mock->shouldReceive('payout')->once()->withArgs(fn ($p) => $p->id === $po->id)->andReturnUsing(function ($p) {
        $p->forceFill(['status' => 'transferred', 'paid_at' => now()])->save();

        return $p;
    });
    $mock->shouldReceive('retryPayouts')->once()->andReturn(3);
    app()->instance(ReferralBillingService::class, $mock);

    Livewire::actingAs(netSuperAdmin())->test(PlatformReferralsPage::class)
        ->set('tab', 'payouts')
        ->call('retry', $po->id)->assertDispatched('toast', title: 'Paid out')
        ->call('retryAll')->assertDispatched('toast', message: '3 payouts paid.');

    expect($po->fresh()->status)->toBe('transferred');
});
