<?php

use App\Livewire\MembershipsPage;
use App\Models\AccountMember;
use App\Models\Page;
use App\Models\Post;
use App\Models\Role;
use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Models\User;
use App\Modules\Memberships\Mail\MembershipCancelledMail;
use App\Modules\Memberships\Mail\MembershipMagicLinkMail;
use App\Modules\Memberships\Mail\MembershipPaymentFailedMail;
use App\Modules\Memberships\Mail\MembershipWelcomeMail;
use App\Modules\Memberships\Member;
use App\Modules\Memberships\MembershipAccess;
use App\Modules\Memberships\MembershipEvent;
use App\Modules\Memberships\MembershipFulfilment;
use App\Modules\Memberships\MembershipTier;
use App\Modules\Memberships\MemberToken;
use App\Payments\CheckoutSession;
use App\Payments\Drivers\StripeConnectGateway;
use App\Payments\PaymentManager;
use App\Payments\SitePaymentFulfilment;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/** Records subscription calls instead of talking to Stripe. */
class MembershipFakeGateway extends StripeConnectGateway
{
    public array $checkouts = [];

    public array $cancelled = [];

    public bool $paid = true;

    public function available($site): bool
    {
        return true;
    }

    public function createSubscriptionCheckout($site, string $name, int $amountCents, string $currency, string $interval,
        string $successUrl, string $cancelUrl, array $metadata = [], ?string $customerEmail = null): CheckoutSession
    {
        $this->checkouts[] = compact('name', 'amountCents', 'currency', 'interval', 'successUrl', 'cancelUrl', 'metadata', 'customerEmail');

        return new CheckoutSession('cs_test_'.uniqid(), 'https://checkout.stripe.test/'.uniqid());
    }

    public function subscriptionCheckoutDetails($site, string $sessionId): array
    {
        return ['paid' => $this->paid, 'subscription' => 'sub_ret_1', 'customer' => 'cus_ret_1', 'metadata' => []];
    }

    public function cancelSubscription($site, string $subscriptionId, bool $atPeriodEnd = true): void
    {
        $this->cancelled[] = [$subscriptionId, $atPeriodEnd];
    }
}

function membershipSite(bool $payments = false): array
{
    $owner = User::factory()->create();
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'mbr-'.uniqid(),
        'domain' => 'mbr-'.uniqid().'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->enableFeature('memberships');
    if ($payments) {
        SitePaymentSettings::create([
            'site_id' => $site->id, 'provider' => 'stripe_connect', 'enabled' => true,
            'connect_account_id' => 'acct_'.uniqid(), 'connect_charges_enabled' => true,
        ]);
    }

    return [$owner, $site->fresh()];
}

function membershipTier(Site $site, int $cents = 0, string $interval = 'month', string $name = 'Supporter'): MembershipTier
{
    return MembershipTier::create([
        'site_id' => $site->id, 'name' => $name, 'slug' => MembershipTier::uniqueSlug($site->id, $name),
        'price_cents' => $cents, 'interval' => $interval, 'currency' => 'gbp', 'benefits' => ['Members-only posts'],
    ]);
}

function membershipFakeGateway(): MembershipFakeGateway
{
    app(PaymentManager::class)->fake($fake = new MembershipFakeGateway);

    return $fake;
}

function membershipPaidMember(Site $site, string $status = 'active', ?string $sub = null): Member
{
    $tier = membershipTier($site, 500, 'month', 'Paid '.uniqid());

    return Member::create([
        'site_id' => $site->id, 'tier_id' => $tier->id, 'name' => 'Pat Paid', 'email' => uniqid('p').'@x.test',
        'status' => $status, 'price_cents' => 500, 'interval' => 'month', 'currency' => 'gbp',
        'joined_at' => now()->subMonth(), 'renews_at' => now()->addDay(), 'stripe_subscription_id' => $sub ?? 'sub_'.uniqid(),
    ]);
}

/** Post a correctly signed connected-account event to the platform webhook. */
function membershipWebhook($test, Site $site, string $type, array $object)
{
    config(['services.stripe_platform.connect_webhook_secret' => 'whsec_mbr_test']);
    $payload = json_encode([
        'id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => $type,
        'account' => $site->paymentSettings->connect_account_id, 'data' => ['object' => $object],
    ]);
    $t = time();
    $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_mbr_test');

    return $test->call('POST', '/stripe/sites/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);
}

// ── joining ──────────────────────────────────────────────────────────────

test('a free join makes an active member and emails a welcome with a magic link', function () {
    Mail::fake();
    [, $site] = membershipSite();
    $tier = membershipTier($site);

    $this->getJson("/api/sites/{$site->name}/memberships/tiers")->assertOk()
        ->assertJsonPath('tiers.0.slug', $tier->slug)->assertJsonPath('tiers.0.free', true)->assertJsonPath('tiers.0.available', true);

    $this->postJson("/api/sites/{$site->name}/memberships/join", ['name' => 'Fay Free', 'email' => 'Fay@Example.test', 'tier' => $tier->slug])
        ->assertStatus(201)->assertJsonPath('status', 'active')->assertJsonPath('member.tier.slug', $tier->slug);

    $member = Member::where('site_id', $site->id)->where('email', 'fay@example.test')->first();
    expect($member->status)->toBe('active')->and($member->joined_at)->not->toBeNull()
        ->and(MemberToken::where('member_id', $member->id)->where('kind', 'magic')->count())->toBe(1);
    Mail::assertQueued(MembershipWelcomeMail::class, fn ($m) => $m->hasTo('fay@example.test') && str_contains($m->loginUrl, '/membership/auth/'));
});

test('a paid join returns a subscription checkout carrying membership_id', function () {
    Mail::fake();
    [, $site] = membershipSite(payments: true);
    $tier = membershipTier($site, 1200, 'year', 'Gold');
    $fake = membershipFakeGateway();

    $res = $this->postJson("/api/sites/{$site->name}/memberships/join", ['name' => 'Gil', 'email' => 'gil@x.test', 'tier' => $tier->id])
        ->assertStatus(201)->assertJsonPath('status', 'checkout');
    expect($res->json('checkout_url'))->toStartWith('https://checkout.stripe.test/');

    $member = Member::where('site_id', $site->id)->where('email', 'gil@x.test')->first();
    expect($member->status)->toBe('pending')
        ->and($fake->checkouts[0]['metadata']['membership_id'])->toBe($member->id)
        ->and($fake->checkouts[0]['interval'])->toBe('year')
        ->and($fake->checkouts[0]['amountCents'])->toBe(1200)
        ->and($member->stripe_checkout_id)->toStartWith('cs_test_');
    Mail::assertNothingQueued();

    // The Stripe params themselves: subscription mode, recurring price, metadata on both, platform fee.
    $p = StripeConnectGateway::subscriptionCheckoutParams('Gold', 1200, 'GBP', 'year', 's', 'c', ['membership_id' => 'm1'], 'g@x.test', 1.0);
    expect($p['mode'])->toBe('subscription')
        ->and($p['line_items'][0]['price_data']['recurring'])->toBe(['interval' => 'year'])
        ->and($p['line_items'][0]['price_data']['currency'])->toBe('gbp')
        ->and($p['metadata'])->toBe(['membership_id' => 'm1'])
        ->and($p['subscription_data']['metadata'])->toBe(['membership_id' => 'm1'])
        ->and($p['subscription_data']['application_fee_percent'])->toEqual(1.0);
});

test('paid tiers 422 without connected payments while free tiers still work', function () {
    Mail::fake();
    [, $site] = membershipSite(payments: false);
    $paid = membershipTier($site, 500, 'month', 'Paid');
    $free = membershipTier($site, 0, 'month', 'Free');

    $this->getJson("/api/sites/{$site->name}/memberships/tiers")->assertOk()->assertJsonPath('paid_available', false);
    $this->postJson("/api/sites/{$site->name}/memberships/join", ['name' => 'A', 'email' => 'a@x.test', 'tier' => $paid->slug])
        ->assertStatus(422)->assertJsonPath('code', 'payments_not_connected');
    $this->postJson("/api/sites/{$site->name}/memberships/join", ['name' => 'A', 'email' => 'a@x.test', 'tier' => $free->slug])
        ->assertStatus(201);
});

test('the memberships API 404s when the add-on is off', function () {
    [, $site] = membershipSite();
    $site->disableFeature('memberships');
    $this->getJson("/api/sites/{$site->name}/memberships/tiers")->assertNotFound();
});

// ── fulfilment + recurring webhooks ──────────────────────────────────────

test('fulfilment of a completed subscription checkout activates the member once', function () {
    Mail::fake();
    [, $site] = membershipSite(payments: true);
    $tier = membershipTier($site, 500);
    $member = Member::create(['site_id' => $site->id, 'tier_id' => $tier->id, 'name' => 'Pam', 'email' => 'pam@x.test',
        'status' => 'pending', 'price_cents' => 500, 'interval' => 'month', 'currency' => 'gbp']);

    expect(config('payments.fulfilment.membership_id'))->toBe(MembershipFulfilment::class);
    $event = new WebhookEvent(kind: WebhookEventKind::Completed, sessionId: 'cs_1', metadata: ['membership_id' => $member->id], isPaid: true);
    app(SitePaymentFulfilment::class)->apply($site, $event);
    app(SitePaymentFulfilment::class)->apply($site, $event); // webhook retry

    $member->refresh();
    expect($member->status)->toBe('active')->and($member->renews_at->isFuture())->toBeTrue();
    Mail::assertQueued(MembershipWelcomeMail::class, 1);
});

test('invoice.paid renews, payment_failed marks past due, subscription.deleted cancels', function () {
    Mail::fake();
    [, $site] = membershipSite(payments: true);
    $member = membershipPaidMember($site, 'pending', 'sub_flow_'.uniqid());
    $member->update(['joined_at' => null]);
    $end = now()->addMonth()->startOfMinute()->getTimestamp();

    // First invoice activates (pending → active) with Stripe's period end.
    membershipWebhook($this, $site, 'invoice.paid', [
        'object' => 'invoice', 'id' => 'in_1', 'customer' => 'cus_9', 'subscription' => $member->stripe_subscription_id,
        'amount_paid' => 500, 'currency' => 'gbp', 'lines' => ['data' => [['period' => ['end' => $end]]]],
    ])->assertOk();
    $member->refresh();
    expect($member->status)->toBe('active')->and($member->renews_at->getTimestamp())->toBe($end)
        ->and($member->stripe_customer_id)->toBe('cus_9');

    // Failed renewal → past_due + one email.
    membershipWebhook($this, $site, 'invoice.payment_failed', [
        'object' => 'invoice', 'id' => 'in_2', 'subscription' => $member->stripe_subscription_id, 'amount_due' => 500, 'currency' => 'gbp',
    ])->assertOk();
    expect($member->fresh()->status)->toBe('past_due');
    Mail::assertQueued(MembershipPaymentFailedMail::class, 1);

    // A retry succeeds (new-API shape: subscription under parent.subscription_details) → active, renewed.
    $end2 = now()->addMonths(2)->startOfMinute()->getTimestamp();
    membershipWebhook($this, $site, 'invoice.paid', [
        'object' => 'invoice', 'id' => 'in_3', 'amount_paid' => 500, 'currency' => 'gbp',
        'parent' => ['subscription_details' => ['subscription' => $member->stripe_subscription_id, 'metadata' => ['membership_id' => $member->id]]],
        'lines' => ['data' => [['period' => ['end' => $end2]]]],
    ])->assertOk();
    $member->refresh();
    expect($member->status)->toBe('active')->and($member->renews_at->getTimestamp())->toBe($end2);
    Mail::assertQueued(MembershipWelcomeMail::class, 1); // recovery is not a second welcome

    membershipWebhook($this, $site, 'customer.subscription.deleted', [
        'object' => 'subscription', 'id' => $member->stripe_subscription_id, 'status' => 'canceled', 'metadata' => ['membership_id' => $member->id],
    ])->assertOk();
    expect($member->fresh()->status)->toBe('cancelled')->and($member->fresh()->cancelled_at)->not->toBeNull();
    Mail::assertQueued(MembershipCancelledMail::class, 1);
    expect(MembershipEvent::where('member_id', $member->id)->pluck('type')->all())
        ->toContain('renewed', 'payment_failed', 'cancelled');
});

test('the checkout success page confirms payment with Stripe when no webhook arrived', function () {
    Mail::fake();
    [, $site] = membershipSite(payments: true);
    membershipFakeGateway();
    $tier = membershipTier($site, 500);
    $member = Member::create(['site_id' => $site->id, 'tier_id' => $tier->id, 'name' => 'Sue', 'email' => 'sue@x.test',
        'status' => 'pending', 'price_cents' => 500, 'interval' => 'month', 'currency' => 'gbp', 'stripe_checkout_id' => 'cs_ok_1']);

    $this->get("/preview/{$site->name}/membership/welcome?session_id=cs_ok_1")->assertOk()->assertSee('in, Sue!');
    expect($member->fresh()->status)->toBe('active')->and($member->fresh()->stripe_subscription_id)->toBe('sub_ret_1');
});

// ── sign-in ──────────────────────────────────────────────────────────────

test('magic-link login gives a member token that unlocks /me', function () {
    Mail::fake();
    [, $site] = membershipSite();
    $tier = membershipTier($site);
    $member = Member::create(['site_id' => $site->id, 'tier_id' => $tier->id, 'name' => 'Lee', 'email' => 'lee@x.test', 'status' => 'active', 'joined_at' => now()]);

    // Unknown emails get the same answer and no mail.
    $this->postJson("/api/sites/{$site->name}/memberships/login", ['email' => 'nobody@x.test'])->assertStatus(202);
    Mail::assertNothingQueued();

    $this->postJson("/api/sites/{$site->name}/memberships/login", [
        'email' => 'LEE@x.test', 'return_url' => 'https://'.$site->domain.'/members',
    ])->assertStatus(202);
    $link = null;
    Mail::assertQueued(MembershipMagicLinkMail::class, function ($m) use (&$link) {
        $link = $m->loginUrl;

        return $m->hasTo('lee@x.test');
    });
    $raw = basename(parse_url($link, PHP_URL_PATH));
    expect(MemberToken::where('token_hash', hash('sha256', $raw))->exists())->toBeTrue()
        ->and(MemberToken::where('token_hash', $raw)->exists())->toBeFalse(); // only the hash is stored

    $res = $this->get(parse_url($link, PHP_URL_PATH))->assertRedirect();
    $location = $res->headers->get('Location');
    expect($location)->toStartWith('https://'.$site->domain.'/members#member_token=');
    $token = urldecode(substr($location, strpos($location, '=') + 1));

    $this->getJson("/api/sites/{$site->name}/memberships/me", ['Authorization' => 'Bearer '.$token])
        ->assertOk()->assertJsonPath('member.email', 'lee@x.test')->assertJsonPath('member.active', true);
    $this->getJson("/api/sites/{$site->name}/memberships/me", ['Authorization' => 'Bearer nope-nope-nope-nope-nope'])->assertStatus(401);

    // A foreign return URL is never used (no open redirect); expired links 410.
    $this->postJson("/api/sites/{$site->name}/memberships/login", ['email' => 'lee@x.test', 'return_url' => 'https://evil.test/x'])->assertStatus(202);
    expect(MemberToken::where('member_id', $member->id)->where('kind', 'magic')->orderByDesc('id')->first()->return_url)->toBeNull();
    $this->get("/preview/{$site->name}/membership/auth/mlk_expired_or_made_up_token_123456")->assertStatus(410);

    // Logout revokes the token.
    $this->postJson("/api/sites/{$site->name}/memberships/logout", [], ['Authorization' => 'Bearer '.$token])->assertOk();
    $this->getJson("/api/sites/{$site->name}/memberships/me", ['Authorization' => 'Bearer '.$token])->assertStatus(401);
});

test('a member cancels at period end from the manage page', function () {
    Mail::fake();
    [, $site] = membershipSite(payments: true);
    $fake = membershipFakeGateway();
    $member = membershipPaidMember($site);
    [$token] = MemberToken::issue($member, 'session', now()->addDay());

    $this->get("/preview/{$site->name}/membership?member_token={$token}")->assertRedirect("/preview/{$site->name}/membership");
    $this->get("/preview/{$site->name}/membership")->assertOk()->assertSee('Pat Paid')->assertSee('Cancel membership');
    $this->post("/preview/{$site->name}/membership/cancel")->assertRedirect();

    expect($fake->cancelled)->toBe([[$member->stripe_subscription_id, true]])
        ->and($member->fresh()->cancel_at_period_end)->toBeTrue()
        ->and($member->fresh()->status)->toBe('active');
});

// ── members-only content ─────────────────────────────────────────────────

test('access checks respect the tiers a post / page is gated to', function () {
    [, $site] = membershipSite();
    $bronze = membershipTier($site, 0, 'month', 'Bronze');
    $gold = membershipTier($site, 0, 'month', 'Gold');
    $post = Post::create(['site_id' => $site->id, 'title' => 'Secret post', 'slug' => 'secret-'.uniqid(), 'status' => 'published']);
    $page = Page::create(['site_id' => $site->id, 'name' => 'Members lounge', 'url' => '/lounge', 'keywords' => '']);
    $open = Post::create(['site_id' => $site->id, 'title' => 'Open post', 'slug' => 'open-'.uniqid(), 'status' => 'published']);
    MembershipAccess::create(['site_id' => $site->id, 'content_type' => 'post', 'content_id' => $post->id, 'tier_ids' => [$gold->id]]);
    MembershipAccess::create(['site_id' => $site->id, 'content_type' => 'page', 'content_id' => $page->id, 'tier_ids' => []]);

    $mk = function (MembershipTier $tier, string $status = 'active') use ($site) {
        $m = Member::create(['site_id' => $site->id, 'tier_id' => $tier->id, 'name' => 'M', 'email' => uniqid().'@x.test', 'status' => $status]);

        return MemberToken::issue($m, 'session', now()->addDay())[0];
    };
    $bronzeToken = $mk($bronze);
    $goldToken = $mk($gold);
    $lapsedGold = $mk($gold, 'cancelled');
    $q = fn ($type, $id, $token = null) => $this->getJson("/api/sites/{$site->name}/memberships/access?type={$type}&id={$id}",
        $token ? ['Authorization' => 'Bearer '.$token] : []);

    $q('post', $post->id)->assertOk()->assertJsonPath('gated', true)->assertJsonPath('allowed', false)->assertJsonPath('tiers.0.slug', $gold->slug);
    $q('post', $post->id, $bronzeToken)->assertJsonPath('allowed', false);
    $q('post', $post->id, $goldToken)->assertJsonPath('allowed', true);
    $q('post', $post->id, $lapsedGold)->assertJsonPath('allowed', false);
    $q('page', $page->id, $bronzeToken)->assertJsonPath('gated', true)->assertJsonPath('allowed', true); // any member
    $q('post', $open->id)->assertJsonPath('gated', false)->assertJsonPath('allowed', true);

    // The list form tells a template what to lock (with slugs / urls).
    $list = $this->getJson("/api/sites/{$site->name}/memberships/access", ['Authorization' => 'Bearer '.$bronzeToken])->assertOk();
    $gated = collect($list->json('gated'))->keyBy('type');
    expect($list->json('member'))->toBeTrue()
        ->and($gated['post']['slug'])->toBe($post->slug)->and($gated['post']['allowed'])->toBeFalse()
        ->and($gated['page']['url'])->toBe('/lounge')->and($gated['page']['allowed'])->toBeTrue();
});

// ── admin ────────────────────────────────────────────────────────────────

test('the admin page renders its rails and the owner manages tiers, members and gating', function () {
    Mail::fake();
    [$owner, $site] = membershipSite();
    $free = membershipTier($site, 0, 'month', 'Community');
    membershipTier($site, 900, 'month', 'Patron');   // paid tier, payments not connected
    $paid = membershipPaidMember($site, 'past_due');
    $paid->update(['name' => 'Dee Overdue']);

    $this->actingAs($owner)->get("/{$site->name}/memberships")->assertOk()
        ->assertSee('Active members')->assertSee('New this month')->assertSee('MRR')->assertSee('Churn this month')
        ->assertSee('Past due')->assertSee('Free / paid')
        ->assertSee('Members-only content')->assertSee('Needs attention')->assertSee('Payments not connected')
        ->assertSee('Dee Overdue — payment failed')->assertSee('Related');

    $page = Livewire::actingAs($owner)->test(MembershipsPage::class, ['site' => $site])
        ->assertViewHas('stats', fn ($s) => $s['pastDue'] === 1 && $s['paidTiers'] >= 1)
        ->set('addName', 'Ann Added')->set('addEmail', 'ann@x.test')->set('addTierId', $free->id)->call('addMember')
        ->assertHasNoErrors();
    $ann = Member::where('site_id', $site->id)->where('email', 'ann@x.test')->first();
    expect($ann->status)->toBe('active');
    Mail::assertQueued(MembershipWelcomeMail::class);

    $page->assertViewHas('stats', fn ($s) => $s['active'] === 1 && $s['newMonth'] >= 1 && $s['free'] === 1)
        ->call('setStatus', 'past_due')->assertViewHas('members', fn ($m) => $m->pluck('name')->all() === ['Dee Overdue'])
        ->call('setStatus', 'bogus')->assertSet('statusFilter', 'all');

    // Tiers: create (paid, monthly), reorder.
    $page->call('newTier')->set('tName', 'Gold Circle')->set('tPrice', '12.50')->set('tInterval', 'year')
        ->set('tBenefits', "Early access\n\nQ&A")->call('saveTier')->assertHasNoErrors();
    $gold = MembershipTier::where('site_id', $site->id)->where('name', 'Gold Circle')->first();
    expect($gold->price_cents)->toBe(1250)->and($gold->benefits)->toBe(['Early access', 'Q&A'])->and($gold->slug)->toBe('gold-circle');
    $page->call('moveTier', $gold->id, -1);
    expect(MembershipTier::where('site_id', $site->id)->orderBy('sort')->pluck('name')->search('Gold Circle'))->toBeLessThan(
        MembershipTier::where('site_id', $site->id)->count() - 1);

    // Gate a post to Gold Circle.
    $post = Post::create(['site_id' => $site->id, 'title' => 'VIP', 'slug' => 'vip-'.uniqid(), 'status' => 'published']);
    $page->set('tab', 'content')->set('cType', 'post')->set('cId', $post->id)->set('cTiers', [$gold->id])->call('gateContent')->assertHasNoErrors();
    expect(MembershipAccess::where('site_id', $site->id)->where('content_id', $post->id)->first()->tier_ids)->toBe([$gold->id]);

    // Cancel a free member (immediate) and export.
    $page->call('cancelMember', $ann->id);
    expect($ann->fresh()->status)->toBe('cancelled');
    $page->call('exportCsv')->assertFileDownloaded();
});

test('members without memberships.manage can view but not change anything', function () {
    [$owner, $site] = membershipSite();
    $tier = membershipTier($site);
    $viewer = Role::forAccount($owner)->firstWhere('slug', 'viewer');
    $user = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $user->id, 'role_id' => $viewer->id]);

    expect($site->allows($user, 'memberships.manage'))->toBeFalse();
    Livewire::actingAs($user)->test(MembershipsPage::class, ['site' => $site])
        ->set('addName', 'X')->set('addEmail', 'x@x.test')->set('addTierId', $tier->id)->call('addMember')->assertStatus(403);
    Livewire::actingAs($user)->test(MembershipsPage::class, ['site' => $site])->call('newTier')->assertStatus(403);
    expect(Member::where('site_id', $site->id)->count())->toBe(0);

    // A stranger can't open the page at all; the add-on off → 404.
    $this->actingAs(User::factory()->create())->get("/{$site->name}/memberships")->assertForbidden();
    $site->disableFeature('memberships');
    $this->actingAs($owner)->get("/{$site->name}/memberships")->assertNotFound();
});
