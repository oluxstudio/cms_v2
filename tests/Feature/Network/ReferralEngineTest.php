<?php

use App\Models\AccountSubscription;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Mail\ReferralConsentMail;
use App\Modules\Network\Mail\ReferralReceivedMail;
use App\Modules\Network\Mail\ReferralUpdateMail;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralEvent;
use App\Modules\Network\NetworkException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;

uses(DatabaseTransactions::class);

beforeEach(fn () => Mail::fake());

function netEngine(): ReferralService
{
    return app(ReferralService::class);
}

/** @return array{0: User, 1: Site} an owner on $plan with one site. */
function netEngineSite(string $plan = 'growth', string $status = 'active'): array
{
    $owner = User::factory()->create();
    AccountSubscription::create([
        'user_id' => $owner->id, 'plan' => $plan, 'status' => $status, 'started_at' => now(),
        'trial_ends_at' => $plan === 'trial' ? now()->addDays(10) : null,
    ]);
    $name = 'ne-'.substr(uniqid(), -8);
    $site = Site::create([
        'user_id' => $owner->id, 'name' => $name,
        'domain' => $name.'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);

    return [$owner, $site];
}

/** Two joined members: [fromOwner, fromSite, toOwner, toSite]. */
function netEnginePair(int $fee = 2000): array
{
    [$a, $from] = netEngineSite();
    [$b, $to] = netEngineSite('pro');
    netEngine()->join($from, $a);
    netEngine()->join($to, $b);
    netEngine()->saveProfile($to, ['business_type' => 'electrician', 'fee_cents' => $fee, 'pitch' => 'Sparks fixed fast', 'area' => 'Leeds']);

    return [$a, $from, $b, $to];
}

function netEngineCustomer(array $over = []): array
{
    return $over + ['name' => 'Cara Customer', 'email' => 'cara-'.substr(uniqid(), -6).'@example.com', 'phone' => '07700 900123', 'note' => 'Needs a rewire'];
}

function netEngineToken(Referral $referral): string
{
    $token = null;
    Mail::assertQueued(ReferralConsentMail::class, function (ReferralConsentMail $m) use ($referral, &$token) {
        if ($m->referral->id === $referral->id) {
            $token = $m->token;

            return true;
        }

        return false;
    });

    return $token;
}

test('trial accounts cannot join; paid plans can', function () {
    [$u, $site] = netEngineSite('trial', 'trialing');
    expect(netEngine()->planAllows($site))->toBeFalse();
    expect(fn () => netEngine()->join($site, $u))->toThrow(NetworkException::class);

    [$u2, $paid] = netEngineSite('starter');
    expect(netEngine()->planAllows($paid))->toBeTrue();
    $profile = netEngine()->join($paid, $u2);
    expect($profile->isMember())->toBeTrue()
        ->and($profile->terms_version)->toBe(config('network.terms_version'));
});

test('saveProfile validates the fee bounds and cleans services', function () {
    [, $site] = netEngineSite();
    expect(fn () => netEngine()->saveProfile($site, ['fee_cents' => 5]))->toThrow(NetworkException::class);
    $p = netEngine()->saveProfile($site, ['fee_cents' => 2500, 'services' => ['Rewiring', ' ', 'EV chargers'], 'radius_km' => '30']);
    expect($p->fee_cents)->toBe(2500)->and($p->services)->toBe(['Rewiring', 'EV chargers'])->and($p->radius_km)->toBe(30);
});

test('findPartners lists other accepting members and filters by q', function () {
    [, $from, , $to] = netEnginePair();
    $found = netEngine()->findPartners($from, ['q' => 'electric']);
    expect($found->pluck('site_id'))->toContain($to->id)->not->toContain($from->id);
    expect(netEngine()->findPartners($from, ['q' => 'zzz-nothing-'.uniqid()]))->toHaveCount(0);
});

test('email-link referral: consent yes shares it and creates the receiver contact', function () {
    [$a, $from, , $to] = netEnginePair(2000);
    $customer = netEngineCustomer();
    $ref = netEngine()->refer($from, $to, $customer, $a);

    expect($ref->status)->toBe('pending_consent')
        ->and($ref->reference)->toMatch('/^REF-[A-Z0-9]{8}$/')
        ->and($ref->fee_cents)->toBe(2000)
        ->and($ref->olux_cut_pct)->toBe((float) config('network.olux_cut_pct'))
        ->and($ref->consent_method)->toBe('email_link')
        ->and($ref->consent_text)->not->toBeEmpty();

    $token = netEngineToken($ref);
    expect(netEngine()->findByConsentToken($token)?->id)->toBe($ref->id);

    $this->get(route('network.consent.show', $token))->assertOk()->assertSee('Yes, introduce me');
    $this->post(route('network.consent.store', $token), ['answer' => 'yes'])->assertRedirect(route('network.consent.show', $token));

    $ref->refresh();
    expect($ref->status)->toBe('shared')->and($ref->shared_at)->not->toBeNull()
        ->and($ref->consent_ip)->not->toBeNull()
        ->and($ref->expires_at->isAfter(now()->addDays(59)))->toBeTrue()
        ->and($ref->consent_token_hash)->not->toBeNull();

    $contact = Contact::find($ref->to_contact_id);
    expect($contact->site_id)->toBe($to->id)
        ->and($contact->email)->toBe($customer['email'])
        ->and($contact->data['source'])->toBe('referral')
        ->and($contact->data['referral_reference'])->toBe($ref->reference);

    Mail::assertQueued(ReferralReceivedMail::class);
    Mail::assertQueued(ReferralUpdateMail::class, fn ($m) => $m->event === 'shared');
    expect(ReferralEvent::where('referral_id', $ref->id)->pluck('type')->all())
        ->toContain('created', 'consent_requested', 'consented', 'shared');

    // The link can't be reused, and now shows the thank-you state.
    expect(fn () => netEngine()->recordConsent($token, true))->toThrow(NetworkException::class);
    $this->get(route('network.consent.show', $token))->assertOk()->assertSee('Thank you');
});

test('consent no refuses the referral without sharing', function () {
    [$a, $from, , $to] = netEnginePair();
    $ref = netEngine()->refer($from, $to, netEngineCustomer(), $a);
    $ref = netEngine()->recordConsent(netEngineToken($ref), false, '1.2.3.4');

    expect($ref->status)->toBe('consent_refused')->and($ref->to_contact_id)->toBeNull()->and($ref->consent_ip)->toBe('1.2.3.4');
    Mail::assertNotQueued(ReferralReceivedMail::class);
    Mail::assertQueued(ReferralUpdateMail::class, fn ($m) => $m->event === 'consent_refused');
});

test('an expired consent link cannot be answered', function () {
    [$a, $from, , $to] = netEnginePair();
    $ref = netEngine()->refer($from, $to, netEngineCustomer(), $a);
    $token = netEngineToken($ref);
    $ref->forceFill(['expires_at' => now()->subMinute()])->save();
    expect(fn () => netEngine()->recordConsent($token, true))->toThrow(NetworkException::class);
    $this->get(route('network.consent.show', $token))->assertOk()->assertSee('no longer active');
});

test('form tick shares at once without a consent email', function () {
    [$a, $from, , $to] = netEnginePair();
    $ref = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    expect($ref->status)->toBe('shared')->and($ref->consent_method)->toBe('form_tick')
        ->and($ref->consented_at)->not->toBeNull()->and($ref->to_contact_id)->not->toBeNull();
    Mail::assertNotQueued(ReferralConsentMail::class);
    Mail::assertQueued(ReferralReceivedMail::class);
});

test('refer refuses duplicates, self-referrals, non-members and trial referrers', function () {
    [$a, $from, , $to] = netEnginePair();
    $customer = netEngineCustomer();
    netEngine()->refer($from, $to, $customer, $a);
    expect(fn () => netEngine()->refer($from, $to, ['email' => strtoupper($customer['email'])] + $customer, $a))
        ->toThrow(NetworkException::class);
    expect(fn () => netEngine()->refer($from, $from, netEngineCustomer(), $a))->toThrow(NetworkException::class);

    [$c, $outsider] = netEngineSite();
    expect(fn () => netEngine()->refer($outsider, $to, netEngineCustomer(), $c))->toThrow(NetworkException::class);

    // Joined, then dropped to trial → can no longer send.
    [$d, $lapsed] = netEngineSite();
    netEngine()->join($lapsed, $d);
    $d->subscription->update(['plan' => 'trial', 'status' => 'trialing']);
    expect(fn () => netEngine()->refer($lapsed->fresh(), $to, netEngineCustomer(), $d))->toThrow(NetworkException::class);

    // Receiver not accepting.
    netEngine()->leave($to, $a);
    expect(fn () => netEngine()->refer($from, $to, netEngineCustomer(), $a))->toThrow(NetworkException::class);
});

test('accept, decline and cancel follow the status guards', function () {
    [$a, $from, $b, $to] = netEnginePair();

    $pending = netEngine()->refer($from, $to, netEngineCustomer(), $a);
    expect(fn () => netEngine()->accept($pending, $b))->toThrow(NetworkException::class);
    expect(netEngine()->cancel($pending, $a)->status)->toBe('cancelled');

    $shared = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    expect(netEngine()->accept($shared, $b)->status)->toBe('accepted');
    Mail::assertQueued(ReferralUpdateMail::class, fn ($m) => $m->event === 'accepted');
    expect(fn () => netEngine()->cancel($shared, $a))->toThrow(NetworkException::class);
    expect(netEngine()->decline($shared, $b, 'Fully booked')->status)->toBe('declined');
    expect($shared->decline_reason)->toBe('Fully booked');
    expect(fn () => netEngine()->decline($shared, $b))->toThrow(NetworkException::class);
});

test('a paid invoice on the receiving site converts the open referral', function () {
    [$a, $from, , $to] = netEnginePair();
    $customer = netEngineCustomer();
    $ref = netEngine()->refer($from, $to, $customer, $a, true);

    $invoice = Invoice::create([
        'site_id' => $to->id, 'number' => 'INV-'.substr(uniqid(), -5), 'customer_name' => 'Cara',
        'customer_email' => strtoupper($customer['email']), 'items' => [['description' => 'Rewire', 'qty' => 1, 'unit_cents' => 50000]],
        'subtotal_cents' => 50000, 'tax_bp' => 0, 'tax_cents' => 0, 'total_cents' => 50000, 'currency' => 'gbp', 'status' => 'sent',
    ]);
    expect($ref->fresh()->status)->toBe('shared');

    $invoice->markPaid();

    $ref->refresh();
    expect($ref->status)->toBe('converted')->and($ref->converted_via)->toBe('invoice:'.$invoice->id)->and($ref->converted_at)->not->toBeNull();
    Mail::assertQueued(ReferralUpdateMail::class, fn ($m) => $m->event === 'converted');
});

test('manual conversion works while open and is refused once the window has passed', function () {
    [$a, $from, $b, $to] = netEnginePair();
    $ref = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    expect(netEngine()->markConverted($ref, 'manual', $b)->status)->toBe('converted');

    $late = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    $late->forceFill(['expires_at' => now()->subDay()])->save();
    expect(fn () => netEngine()->markConverted($late, 'manual', $b))->toThrow(NetworkException::class);
    // Automatic detection outside the window is a quiet no-op.
    expect(netEngine()->detectConversion($to, $late->customer_email, 'order:x'))->toBeNull()
        ->and(netEngine()->detectConversion($to, null, 'order:x'))->toBeNull()
        ->and($late->fresh()->status)->toBe('shared');
});

test('disputes: within the window only, and super admins resolve both ways', function () {
    [$a, $from, $b, $to] = netEnginePair();
    $super = User::factory()->create();
    $super->forceFill(['is_super' => true])->save();

    $one = netEngine()->markConverted(netEngine()->refer($from, $to, netEngineCustomer(), $a, true), 'manual', $b);
    netEngine()->dispute($one, $b, 'They never booked');
    expect($one->status)->toBe('disputed');
    expect(fn () => netEngine()->resolveDispute($one, $b, 'upheld'))->toThrow(NetworkException::class);
    netEngine()->resolveDispute($one, $super, 'upheld', 'Agreed');
    expect($one->status)->toBe('void')->and($one->dispute_outcome)->toBe('upheld')->and($one->dispute_resolved_at)->not->toBeNull();

    $two = netEngine()->markConverted(netEngine()->refer($from, $to, netEngineCustomer(), $a, true), 'manual', $b);
    netEngine()->dispute($two, $b, 'Not ours');
    netEngine()->resolveDispute($two, $super, 'rejected');
    expect($two->status)->toBe('converted')->and($two->dispute_outcome)->toBe('rejected');

    $old = netEngine()->markConverted(netEngine()->refer($from, $to, netEngineCustomer(), $a, true), 'manual', $b);
    $old->forceFill(['converted_at' => now()->subDays((int) config('network.dispute_days') + 1)])->save();
    expect(fn () => netEngine()->dispute($old, $b, 'Too late'))->toThrow(NetworkException::class);
    expect(ReferralEvent::where('referral_id', $one->id)->pluck('type')->all())->toContain('disputed', 'dispute_upheld');
});

test('expireStale expires unanswered consents and lapsed open referrals', function () {
    [$a, $from, , $to] = netEnginePair();
    $pending = netEngine()->refer($from, $to, netEngineCustomer(), $a);
    $open = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    $fresh = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    $pending->forceFill(['expires_at' => now()->subMinute()])->save();
    $open->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(netEngine()->expireStale())->toBeGreaterThanOrEqual(2);
    expect($pending->fresh()->status)->toBe('expired')
        ->and($open->fresh()->status)->toBe('expired')
        ->and($fresh->fresh()->status)->toBe('shared');

    $this->artisan('network:expire')->assertSuccessful();
});

test('the network mails render', function () {
    [$a, $from, , $to] = netEnginePair();
    $ref = netEngine()->refer($from, $to, netEngineCustomer(), $a, true);
    expect((new ReferralConsentMail($ref, str_repeat('a', 48)))->render())->toContain('/referral/'.str_repeat('a', 48));
    expect((new ReferralReceivedMail($ref))->render())->toContain($ref->reference);
    foreach (ReferralUpdateMail::EVENTS as $event) {
        expect((new ReferralUpdateMail($ref, $event))->render())->toContain($ref->reference);
    }
});
