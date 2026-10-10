<?php

use App\Livewire\EarningsPage;
use App\Livewire\NetworkPage;
use App\Livewire\ReferToPartner;
use App\Livewire\SiteContactsPage;
use App\Livewire\SiteFormsPage;
use App\Models\AccountMember;
use App\Models\Contact;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Modules\Network\Contracts\ReferralBillingService;
use App\Modules\Network\Contracts\ReferralService;
use App\Modules\Network\Models\NetworkProfile;
use App\Modules\Network\Models\Referral;
use App\Modules\Network\Models\ReferralPayout;
use App\Modules\Network\NetworkException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/**
 * A recording fake of the engine — the UI is tested against the contract only.
 * $plan toggles planAllows(); every mutating call is logged in $calls.
 */
function netUiFakeService(bool $plan = true): object
{
    $fake = new class implements ReferralService
    {
        public bool $plan = true;

        public array $calls = [];

        public ?string $throw = null;

        private function rec(string $m, ...$args)
        {
            $this->calls[] = [$m, $args];
            if ($this->throw) {
                throw new NetworkException($this->throw);
            }
        }

        public function planAllows(Site $site): bool
        {
            return $this->plan;
        }

        public function profileFor(Site $site): ?NetworkProfile
        {
            return NetworkProfile::where('site_id', $site->id)->first();
        }

        public function saveProfile(Site $site, array $data): NetworkProfile
        {
            $this->rec('saveProfile', $data);

            return NetworkProfile::updateOrCreate(['site_id' => $site->id], $data);
        }

        public function join(Site $site, User $user): NetworkProfile
        {
            $this->rec('join');

            return tap($this->profileFor($site))->update(['terms_accepted_at' => now(), 'terms_version' => config('network.terms_version')]);
        }

        public function leave(Site $site, User $user): void
        {
            $this->rec('leave');
        }

        public function findPartners(Site $site, array $filters = []): Collection
        {
            return NetworkProfile::with('site')->where('accepting', true)->where('site_id', '!=', $site->id)->get();
        }

        public function refer(Site $from, Site $to, array $customer, User $user, bool $formTick = false): Referral
        {
            $this->rec('refer', $to->id, $customer, $formTick);

            return netUiReferral($from, $to, ['customer_name' => $customer['name'], 'customer_email' => $customer['email']]);
        }

        public function recordConsent(string $token, bool $yes, ?string $ip = null): Referral
        {
            throw new LogicException('unused');
        }

        public function findByConsentToken(string $token): ?Referral
        {
            return null;
        }

        public function cancel(Referral $referral, User $user): Referral
        {
            $this->rec('cancel', $referral->id);

            return $referral;
        }

        public function accept(Referral $referral, User $user): Referral
        {
            $this->rec('accept', $referral->id);

            return $referral;
        }

        public function decline(Referral $referral, User $user, ?string $reason = null): Referral
        {
            $this->rec('decline', $referral->id, $reason);

            return $referral;
        }

        public function markConverted(Referral $referral, string $via, ?User $user = null): Referral
        {
            $this->rec('markConverted', $referral->id, $via);

            return $referral;
        }

        public function detectConversion(Site $toSite, ?string $email, string $via): ?Referral
        {
            return null;
        }

        public function dispute(Referral $referral, User $user, string $reason): Referral
        {
            $this->rec('dispute', $referral->id, $reason);

            return $referral;
        }

        public function resolveDispute(Referral $referral, User $admin, string $outcome, ?string $note = null): Referral
        {
            return $referral;
        }

        public function expireStale(): int
        {
            return 0;
        }

        public function log(Referral $referral, string $type, ?User $user = null, array $data = []): void {}
    };
    $fake->plan = $plan;
    app()->instance(ReferralService::class, $fake);

    return $fake;
}

function netUiFakeBilling(array $summary = []): object
{
    $fake = new class implements ReferralBillingService
    {
        public array $summary = [];

        public array $methods = [];

        public function bill(Referral $referral): Referral
        {
            return $referral;
        }

        public function billDue(): int
        {
            return 0;
        }

        public function collectForStripeInvoice(string $stripeInvoiceId, array $invoiceItemIds): int
        {
            return 0;
        }

        public function markCollected(Referral $referral): Referral
        {
            return $referral;
        }

        public function payout(ReferralPayout $payout): ReferralPayout
        {
            return $payout;
        }

        public function retryPayouts(): int
        {
            return 0;
        }

        public function payoutMethod(Site $site): string
        {
            return end($this->methods) ?: 'connect_transfer';
        }

        public function setPayoutMethod(Site $site, string $method): void
        {
            $this->methods[] = $method;
        }

        public function earningsSummary(Site $site): array
        {
            return $this->summary + ['method' => $this->payoutMethod($site)];
        }
    };
    $fake->summary = $summary + ['pending_cents' => 1455, 'ready_cents' => 0, 'paid_cents' => 2910, 'credited_cents' => 0,
        'lifetime_cents' => 4365, 'currency' => 'gbp', 'connect_ready' => false];
    app()->instance(ReferralBillingService::class, $fake);

    return $fake;
}

function netUiSite(?User $owner = null): array
{
    $owner ??= User::factory()->create();
    $name = 'nu-'.substr(uniqid(), -8);
    $site = Site::create(['user_id' => $owner->id, 'name' => $name, 'domain' => $name.'.test', 'owner' => $owner->name, 'description' => 'test']);

    return [$owner, $site];
}

function netUiProfile(Site $site, array $attrs = []): NetworkProfile
{
    return NetworkProfile::create($attrs + [
        'site_id' => $site->id, 'business_type' => 'Electrician', 'services' => ['Rewiring', 'EV chargers'],
        'area' => 'Leeds', 'postcode' => 'LS1 4AP', 'radius_km' => 25, 'pitch' => 'Fast, tidy, certified.',
        'fee_cents' => 1500, 'currency' => 'gbp', 'accepting' => true,
        'terms_accepted_at' => now(), 'terms_version' => config('network.terms_version'),
    ]);
}

function netUiReferral(Site $from, Site $to, array $attrs = []): Referral
{
    return Referral::create($attrs + [
        'reference' => 'R'.strtoupper(substr(uniqid(), -7)), 'from_site_id' => $from->id, 'to_site_id' => $to->id,
        'customer_name' => 'Cara Customer', 'customer_email' => 'cara@example.com', 'status' => 'shared',
        'fee_cents' => 1500, 'currency' => 'gbp', 'olux_cut_pct' => 3, 'shared_at' => now(),
    ]);
}

function netUiMember(User $owner, array $perms): User
{
    $role = Role::create(['account_id' => $owner->id, 'name' => 'Net '.uniqid(), 'slug' => 'net-'.uniqid(), 'permissions' => $perms]);
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $role->id]);

    return $member;
}

test('the owner sees both pages with their rails', function () {
    netUiFakeService();
    netUiFakeBilling();
    [$owner, $site] = netUiSite();
    netUiProfile($site);

    $this->actingAs($owner)->get("/{$site->name}/network")->assertOk()
        ->assertSee(['Referral network', 'Earned', 'Referrals sent', 'Referrals received', 'Conversion rate'])
        ->assertSee(['Profile', 'Find partners', 'Sent', 'Received', 'Your network profile', 'Save profile'])
        ->assertSee(['Membership', 'How it works', 'Earnings →', 'Contacts →'])
        ->assertDontSee('data-network-upgrade', false);

    $this->actingAs($owner)->get("/{$site->name}/earnings")->assertOk()
        ->assertSee(['Lifetime earnings', '£43.65', 'Pending', '£14.55', 'Paid to bank', 'Olux credit', 'How you get paid', 'Network →'])
        ->assertSee('Finish Stripe setup');
});

test('the tab query string opens that tab', function () {
    netUiFakeService();
    [$owner, $site] = netUiSite();
    [, $other] = netUiSite();
    netUiReferral($site, $other, ['customer_name' => 'Sent Sam', 'status' => 'pending_consent']);

    $this->actingAs($owner)->get("/{$site->name}/network?tab=sent")->assertOk()->assertSee('Sent Sam')->assertSee('Awaiting customer');
});

test('pages are permission gated', function () {
    netUiFakeService();
    netUiFakeBilling();
    [$owner, $site] = netUiSite();

    $viewer = netUiMember($owner, ['network.view']);
    $this->actingAs($viewer)->get("/{$site->name}/network")->assertOk()->assertDontSee('Join the network</button>', false);
    $this->actingAs($viewer)->get("/{$site->name}/earnings")->assertForbidden();
    Livewire::actingAs($viewer)->test(NetworkPage::class, ['site' => $site])->call('saveProfile')->assertForbidden();
    Livewire::actingAs($viewer)->test(EarningsPage::class, ['site' => $site])->call('setMethod', 'olux_credit')->assertForbidden();

    $nobody = netUiMember($owner, ['pages.view']);
    $this->actingAs($nobody)->get("/{$site->name}/network")->assertForbidden();
});

test('trial accounts see the upgrade panel and browse partners read-only', function () {
    netUiFakeService(plan: false);
    [$owner, $site] = netUiSite();
    [, $partner] = netUiSite();
    netUiProfile($partner, ['business_type' => 'Plumber']);

    $this->actingAs($owner)->get("/{$site->name}/network")->assertOk()
        ->assertSee('data-network-upgrade', false)->assertSee('Upgrade to join the referral network')
        ->assertSee(route('account.subscription'))->assertDontSee('Join the network</button>', false);

    Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])->call('setTab', 'partners')
        ->assertSee('Plumber')->assertDontSee('Refer a customer</button>', false);
});

test('joining validates terms and calls the service', function () {
    $svc = netUiFakeService();
    [$owner, $site] = netUiSite();

    Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])
        ->set('businessType', 'Electrician')->set('area', 'Leeds')->set('feePounds', '20.50')
        ->call('join')->assertHasErrors('termsAgreed');
    expect($svc->calls)->toBe([]);

    Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])
        ->set('businessType', 'Electrician')->set('area', 'Leeds')->set('feePounds', '20.50')
        ->set('serviceInput', 'Rewiring, EV chargers')->call('addService')
        ->set('termsAgreed', true)->call('join')->assertHasNoErrors()
        ->assertSee('Welcome to the Olux referral network.');

    expect(array_column($svc->calls, 0))->toBe(['saveProfile', 'join'])
        ->and($svc->calls[0][1][0])->toMatchArray(['business_type' => 'Electrician', 'fee_cents' => 2050, 'services' => ['Rewiring', 'EV chargers']]);

    // Fee bounds are enforced.
    Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])->set('feePounds', '0.10')->call('saveProfile')->assertHasErrors('feePounds');
});

test('service errors are shown, not thrown', function () {
    $svc = netUiFakeService();
    [$owner, $site] = netUiSite();
    netUiProfile($site);
    $svc->throw = 'Not allowed right now.';

    Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])->call('saveProfile')->assertSee('Not allowed right now.');
});

test('received actions render by status', function () {
    $svc = netUiFakeService();
    [$owner, $site] = netUiSite();
    [, $from] = netUiSite();
    $shared = netUiReferral($from, $site, ['customer_name' => 'Shared Sue', 'status' => 'shared']);
    $accepted = netUiReferral($from, $site, ['customer_name' => 'Accepted Al', 'status' => 'accepted', 'accepted_at' => now()]);
    $won = netUiReferral($from, $site, ['customer_name' => 'Won Wes', 'status' => 'converted', 'converted_at' => now()->subDay()]);
    netUiReferral($from, $site, ['customer_name' => 'Old Olga', 'status' => 'converted', 'converted_at' => now()->subDays(30)]);
    netUiReferral($from, $site, ['customer_name' => 'Hidden Hal', 'status' => 'pending_consent', 'shared_at' => null]);

    $page = Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])->call('setTab', 'received');
    $html = $page->html();
    expect($html)->toContain('Shared Sue')->toContain('Accepted Al')->toContain('Won Wes')->toContain('Old Olga')
        ->not->toContain('Hidden Hal') // never shown before the customer consents
        ->toContain("accept('{$shared->id}')")->not->toContain("accept('{$accepted->id}')")
        ->toContain("markWon('{$accepted->id}')")->not->toContain("markWon('{$won->id}')")
        ->toContain("startReason('{$won->id}', 'dispute')")
        ->toContain('Dispute by '.now()->subDay()->addDays(config('network.dispute_days'))->format('j M Y'))
        ->toContain('Dispute window closed');

    $page->call('accept', $shared->id)
        ->call('startReason', $won->id, 'dispute')->set('reason', 'short')->call('submitReason')->assertHasErrors('reason')
        ->set('reason', 'Customer was already ours last year.')->call('submitReason')->assertHasNoErrors()
        ->call('startReason', $shared->id, 'decline')->set('reason', 'Fully booked')->call('submitReason')
        ->call('markWon', $accepted->id);

    expect(array_column($svc->calls, 0))->toBe(['accept', 'dispute', 'decline', 'markConverted'])
        ->and($svc->calls[3][1])->toBe([$accepted->id, 'manual']);
});

test('sent referrals show net earnings and can be cancelled before sharing', function () {
    $svc = netUiFakeService();
    [$owner, $site] = netUiSite();
    [, $to] = netUiSite();
    $r = netUiReferral($site, $to, ['customer_name' => 'Pending Pat', 'status' => 'pending_consent', 'fee_cents' => 2000]);

    Livewire::actingAs($owner)->test(NetworkPage::class, ['site' => $site])->call('setTab', 'sent')
        ->assertSee(['Pending Pat', '£19.40', 'Cancel referral'])
        ->call('cancel', $r->id);
    expect($svc->calls[0])->toBe(['cancel', [$r->id]]);
});

test('earnings: payouts list and payout method', function () {
    netUiFakeService();
    $billing = netUiFakeBilling();
    [$owner, $site] = netUiSite();
    [, $to] = netUiSite();
    $r = netUiReferral($site, $to, ['customer_name' => 'Paid Pete', 'status' => 'collected']);
    ReferralPayout::create(['referral_id' => $r->id, 'site_id' => $site->id, 'gross_cents' => 1500, 'olux_cents' => 45,
        'net_cents' => 1455, 'currency' => 'gbp', 'status' => 'transferred', 'method' => 'connect_transfer', 'paid_at' => now()]);

    Livewire::actingAs($owner)->test(EarningsPage::class, ['site' => $site])
        ->assertSee([$r->reference, 'Paid Pete', '£15.00', '£0.45', '£14.55', 'Paid'])
        ->call('setMethod', 'olux_credit')->assertSee('Earnings will now be taken off your Olux bill.')
        ->call('setMethod', 'bogus');
    expect($billing->methods)->toBe(['olux_credit']);
});

test('refer to a partner validates and sends through the service', function () {
    $svc = netUiFakeService();
    [$owner, $site] = netUiSite();
    netUiProfile($site);
    [, $partnerSite] = netUiSite();
    netUiProfile($partnerSite, ['business_type' => 'Roofer', 'fee_cents' => 2500]);

    $c = Livewire::actingAs($owner)->test(ReferToPartner::class, ['site' => $site])
        ->dispatch('open-refer-partner', prefill: ['name' => 'Ola', 'email' => 'not-an-email'])
        ->assertSet('open', true)->assertSee('Roofer')
        ->call('submit')->assertHasErrors(['email', 'toSiteId']);
    expect($svc->calls)->toBe([]);

    $c->set('email', 'ola@example.com')->call('choosePartner', $partnerSite->id)
        ->assertSee('£25.00')->assertSee('£24.25') // fee they pay · what you earn
        ->set('consentMethod', 'form')->assertSee('They must have agreed to')
        ->call('submit')->assertHasNoErrors()->assertDispatched('referral-sent')->assertSee('sent');

    expect($svc->calls[0][0])->toBe('refer')
        ->and($svc->calls[0][1][0])->toBe($partnerSite->id)
        ->and($svc->calls[0][1][1])->toMatchArray(['name' => 'Ola', 'email' => 'ola@example.com'])
        ->and($svc->calls[0][1][2])->toBeTrue();

    // Own site / non-accepting partners are refused.
    Livewire::actingAs($owner)->test(ReferToPartner::class, ['site' => $site])
        ->call('openFor', ['name' => 'Ola', 'email' => 'ola@example.com', 'to_site_id' => $site->id])
        ->call('submit')->assertHasErrors('toSiteId');

    // View-only users can't send.
    $viewer = netUiMember($owner, ['network.view']);
    Livewire::actingAs($viewer)->test(ReferToPartner::class, ['site' => $site])
        ->call('openFor', ['name' => 'Ola', 'email' => 'ola@example.com', 'to_site_id' => $partnerSite->id])
        ->call('submit')->assertForbidden();
});

test('contacts drawer and form responses carry the refer button', function () {
    netUiFakeService();
    [$owner, $site] = netUiSite();
    $contact = Contact::create(['site_id' => $site->id, 'name' => 'Dee Drawer', 'email' => 'dee@example.com', 'status' => 'new']);

    Livewire::actingAs($owner)->test(SiteContactsPage::class, ['site' => $site])
        ->call('open', $contact->id)->assertSee('Refer to a partner')->assertSee('open-refer-partner', false);

    $form = Form::create(['site_id' => $site->id, 'name' => 'quote-'.uniqid(), 'title' => 'Quote']);
    $resp = FormResponse::create(['form_id' => $form->id, 'fields' => ['your_name' => 'Fay Form', 'email' => 'fay@example.com', 'referral_consent' => 'yes']]);
    $html = Livewire::actingAs($owner)->test(SiteFormsPage::class, ['site' => $site, 'openResponse' => $resp->id])->html();
    expect($html)->toContain('Refer to a partner')->toContain('fay@example.com')->toContain('form_tick');

    // View-only members don't get the button.
    $viewer = netUiMember($owner, ['contacts.view', 'network.view']);
    Livewire::actingAs($viewer)->test(SiteContactsPage::class, ['site' => $site])
        ->call('open', $contact->id)->assertDontSee('Refer to a partner');
});

test('pages render against the real engine and billing services', function () {
    [$owner, $site] = netUiSite();
    [, $partner] = netUiSite();
    netUiProfile($partner, ['business_type' => 'Roofer']);

    $this->actingAs($owner)->get("/{$site->name}/network")->assertOk()->assertSee('Referral network');
    $this->actingAs($owner)->get("/{$site->name}/network?tab=partners")->assertOk()->assertSee('Roofer');
    $this->actingAs($owner)->get("/{$site->name}/earnings")->assertOk()->assertSee('Lifetime earnings');
    $this->actingAs($owner)->get("/{$site->name}/contacts")->assertOk();
});
