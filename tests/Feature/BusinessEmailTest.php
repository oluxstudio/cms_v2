<?php

use App\Jobs\Email\CreateMailboxJob;
use App\Livewire\SiteMailboxesPage;
use App\Livewire\SubscriptionPage;
use App\Models\DomainOrder;
use App\Models\EmailDomain;
use App\Models\Mailbox;
use App\Models\Site;
use App\Models\User;
use App\Services\Email\BusinessEmail;
use App\Services\Email\EmailDns;
use App\Services\Email\EmailProvider;
use App\Services\Email\FakeEmailProvider;
use App\Services\Openprovider\OpenproviderClient;
use App\Services\PlatformBilling;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['email.driver' => 'fake', 'email.dns_check.max_attempts' => 1, 'email.records.mx' => [['host' => 'mx1.mail.test', 'prio' => 10]]]);
    Cache::forget('fake-email-provider');
    app()->forgetInstance(EmailProvider::class);
});

/** An account on $plan with a site on a verified domain. */
function emailSite(string $plan = 'growth'): array
{
    $owner = User::factory()->create();
    $owner->currentSubscription()->update(['plan' => $plan, 'status' => $plan === 'trial' ? 'trialing' : 'active']);
    $name = 'em-'.substr(uniqid(), -8);
    $site = Site::create(['user_id' => $owner->id, 'name' => $name, 'domain' => $name.'.co.uk', 'owner' => 'x', 'description' => 't', 'domain_verified_at' => now()]);
    $site->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
    RateLimiter::clear('mailbox-create:'.$owner->id);

    return [$owner, $site->fresh()];
}

function emailOn(Site $site, User $owner): EmailDomain
{
    return app(BusinessEmail::class)->enable($site, $owner)->fresh();
}

function fakeProvider(): FakeEmailProvider
{
    return app(EmailProvider::class);
}

// ── Limits ─────────────────────────────────────────────────────────

test('a Growth account can create 5 mailboxes, not a 6th; aliases never count', function () {
    [$owner, $site] = emailSite('growth');
    $d = emailOn($site, $owner);
    $svc = app(BusinessEmail::class);

    foreach (['a', 'b', 'c', 'd', 'e'] as $l) {
        $svc->createMailbox($d, $owner, $l);
    }
    expect($owner->currentSubscription()->mailboxesUsed())->toBe(5);

    expect(fn () => $svc->createMailbox($d, $owner, 'f'))
        ->toThrow(ValidationException::class, 'using all 5 mailboxes');

    $svc->addAlias($d->mailboxes()->where('local_part', 'a')->first(), $owner, 'hello');
    $svc->addAlias($d->mailboxes()->where('local_part', 'a')->first(), $owner, 'bookings');
    expect($owner->currentSubscription()->mailboxesUsed())->toBe(5)
        ->and(Mailbox::where('email_domain_id', $d->id)->count())->toBe(5);
});

test('plan limits: Free/Trial/Starter 0, Growth 5, Pro 10, Enterprise per account; add-ons stack', function () {
    $sub = User::factory()->create()->currentSubscription();
    $limit = fn (string $plan) => tap($sub)->update(['plan' => $plan, 'status' => 'active'])->fresh()->mailboxLimit();

    expect($limit('free'))->toBe(0)->and($limit('trial'))->toBe(0)
        ->and($limit('starter'))->toBe(0)->and($limit('growth'))->toBe(5)->and($limit('pro'))->toBe(10)
        ->and($limit('enterprise'))->toBe(0);

    $sub->update(['plan' => 'enterprise', 'mailbox_limit_override' => 75]);
    expect($sub->fresh()->mailboxLimit())->toBe(75);
    $sub->update(['extra_mailboxes' => 3]);
    expect($sub->fresh()->mailboxLimit())->toBe(78);

    $sub->update(['status' => 'cancelled']);
    expect($sub->fresh()->mailboxLimit())->toBe(0);
});

test('email needs a verified, non-subdomain domain on a plan with mailboxes', function () {
    [$owner, $trialSite] = emailSite('trial');
    expect(app(BusinessEmail::class)->ineligibility($trialSite))->toContain('plan');

    [, $site] = emailSite('growth');
    expect(app(BusinessEmail::class)->ineligibility($site))->toBeNull();

    $site->update(['domain' => 'shop.'.$site->domain]);
    expect(app(BusinessEmail::class)->ineligibility($site->fresh()))->toContain('subdomain');

    $site->update(['domain' => 'x-'.uniqid().'.co.uk', 'domain_verified_at' => null]);
    expect(app(BusinessEmail::class)->ineligibility($site->fresh()))->toContain('verify');

    expect(BusinessEmail::isApex('acme.co.uk'))->toBeTrue()
        ->and(BusinessEmail::isApex('acme.com'))->toBeTrue()
        ->and(BusinessEmail::isApex('mail.acme.com'))->toBeFalse()
        ->and(BusinessEmail::isApex('shop.acme.co.uk'))->toBeFalse();
});

// ── Tenant isolation ───────────────────────────────────────────────

test('a tenant never sees or acts on another tenant\'s domain or mailboxes', function () {
    [$ownerA, $siteA] = emailSite('growth');
    [$ownerB, $siteB] = emailSite('growth');
    $dA = emailOn($siteA, $ownerA);
    [$boxA] = app(BusinessEmail::class)->createMailbox($dA, $ownerA, 'private');

    // B can't open A's page.
    $this->actingAs($ownerB)->get("/{$siteA->name}/mailboxes")->assertForbidden();

    // On B's own page, A's mailbox id is simply not found.
    emailOn($siteB, $ownerB);
    Livewire::actingAs($ownerB)->test(SiteMailboxesPage::class, ['site' => $siteB])
        ->assertDontSee('private@')
        ->call('resetPassword', $boxA->id)
        ->assertNotFound();
    Livewire::actingAs($ownerB)->test(SiteMailboxesPage::class, ['site' => $siteB])
        ->call('askDelete', $boxA->id)
        ->assertNotFound();

    expect(Mailbox::forAccount($ownerB->id)->count())->toBe(0)
        ->and(EmailDomain::forAccount($ownerB->id)->pluck('domain')->all())->not->toContain($siteA->domain);

    // The same domain can't be claimed by another account.
    $siteB->update(['domain' => $siteA->domain]);
    expect(fn () => app(BusinessEmail::class)->enable($siteB->fresh(), $ownerB))->toThrow(ValidationException::class, 'another account');
});

// ── SPF ────────────────────────────────────────────────────────────

test('SPF merging keeps every existing include and ends up with exactly one record', function () {
    $inc = ['include:_spf.mail.test'];

    expect(EmailDns::mergeSpf([], $inc))->toBe('v=spf1 include:_spf.mail.test ~all')
        ->and(EmailDns::mergeSpf(['v=spf1 include:_spf.google.com -all'], $inc))->toBe('v=spf1 include:_spf.google.com include:_spf.mail.test -all')
        ->and(EmailDns::mergeSpf(['v=spf1 include:_spf.mail.test ~all'], $inc))->toBe('v=spf1 include:_spf.mail.test ~all')        // no duplicate
        ->and(EmailDns::mergeSpf(['v=spf1 a mx include:a.test ~all', 'v=spf1 include:b.test -all'], $inc))                      // two records → one
        ->toBe('v=spf1 a mx include:a.test include:b.test include:_spf.mail.test -all')
        ->and(EmailDns::mergeSpf(['"v=spf1 ip4:1.2.3.4 ?all"'], [...$inc, 'include:spf.brevo.com']))
        ->toBe('v=spf1 ip4:1.2.3.4 include:_spf.mail.test include:spf.brevo.com ?all')
        ->and(EmailDns::mergeSpf(['v=spf1 redirect=_spf.old.test'], $inc))->toBe('v=spf1 include:_spf.old.test include:_spf.mail.test ~all')
        ->and(substr_count(EmailDns::mergeSpf(['v=spf1 -all', 'v=spf1 ~all'], $inc), 'v=spf1'))->toBe(1);
});

test('Brevo is only in SPF when the tenant sends platform email from their own authenticated domain', function () {
    config(['email.records.spf_include' => 'include:_spf.mail.test']);
    [$owner, $site] = emailSite('growth');
    $d = emailOn($site, $owner);
    $dns = app(EmailDns::class);

    expect($dns->spfIncludes($d))->toBe(['include:_spf.mail.test']);
    $site->setAttr('email.send_from_own_domain', '1');
    expect($dns->spfIncludes($d->fresh()))->toBe(['include:_spf.mail.test']);   // not authenticated in Brevo yet
    $site->setAttr('email.brevo_authenticated', '1');
    expect($dns->spfIncludes($d->fresh()))->toBe(['include:_spf.mail.test', 'include:spf.brevo.com']);
});

// ── Names ──────────────────────────────────────────────────────────

test('aliases and mailboxes can\'t share an address; reserved and malformed names are refused', function () {
    [$owner, $site] = emailSite('growth');
    $d = emailOn($site, $owner);
    $svc = app(BusinessEmail::class);
    [$box] = $svc->createMailbox($d, $owner, 'info');
    $svc->addAlias($box, $owner, 'hello');

    expect(fn () => $svc->createMailbox($d, $owner, 'hello'))->toThrow(ValidationException::class, 'already in use')
        ->and(fn () => $svc->addAlias($box, $owner, 'info'))->toThrow(ValidationException::class, 'already in use')
        ->and(fn () => $svc->createMailbox($d, $owner, 'info'))->toThrow(ValidationException::class, 'already in use')
        ->and(fn () => $svc->createMailbox($d, $owner, 'postmaster'))->toThrow(ValidationException::class, 'reserved')
        ->and(fn () => $svc->createMailbox($d, $owner, 'abuse'))->toThrow(ValidationException::class, 'reserved')
        ->and(fn () => $svc->createMailbox($d, $owner, 'Bad Name'))->toThrow(ValidationException::class)
        ->and(fn () => $svc->createMailbox($d, $owner, '.dot'))->toThrow(ValidationException::class)
        ->and(fn () => $svc->createMailbox($d, $owner, 'a..b'))->toThrow(ValidationException::class)
        ->and(fn () => $svc->createMailbox($d, $owner, str_repeat('a', 65)))->toThrow(ValidationException::class);

    expect($svc->createMailbox($d, $owner, 'first.last-2_x')[0]->local_part)->toBe('first.last-2_x');
});

// ── Downgrade ──────────────────────────────────────────────────────

test('downgrading below the mailbox count is blocked until mailboxes are deleted', function () {
    [$owner, $site] = emailSite('pro');
    $d = emailOn($site, $owner);
    $svc = app(BusinessEmail::class);
    foreach (range(1, 6) as $i) {
        $svc->createMailbox($d, $owner, "box{$i}");
    }

    Livewire::actingAs($owner)->test(SubscriptionPage::class)
        ->call('choose', 'growth')
        ->assertSet('blocker', fn ($b) => str_contains((string) $b, 'You have 6 business email mailboxes but Growth includes 5'));
    expect($owner->currentSubscription()->fresh()->plan)->toBe('pro');

    $m = $d->mailboxes()->where('local_part', 'box6')->first();
    $svc->deleteMailbox($m, $owner, $m->address());
    expect(app(PlatformBilling::class)->downgradeBlocker($owner->fresh(), 'growth'))->toBeNull();
    Livewire::actingAs($owner)->test(SubscriptionPage::class)->call('choose', 'growth')->assertSet('blocker', null);
});

// ── Provider flow + idempotency ────────────────────────────────────

test('switching on adds the domain at the provider; a mailbox is created once, even when its job is retried', function () {
    [$owner, $site] = emailSite('growth');
    $d = emailOn($site, $owner);
    $p = fakeProvider();
    expect(collect($p->calls)->pluck(0))->toContain('addDomain', 'dkimRecord')
        ->and($d->provider_reference)->toBe($site->domain)
        ->and($d->dns_report['dkim']['value'])->toContain('DKIM1');

    // Queue faked: run the job's attempts by hand, like a worker retrying.
    Queue::fake();
    [$box] = app(BusinessEmail::class)->createMailbox($d, $owner, 'team');
    Queue::assertPushed(CreateMailboxJob::class, fn ($job) => $job->mailboxId === $box->id);

    // First attempt: the licence is bought, then creating the mailbox fails.
    $p->failNext['createMailbox'] = 1;
    expect(fn () => (new CreateMailboxJob($box->id, 'Secret-Pass-123'))->handle($p))->toThrow(RuntimeException::class);
    expect($box->fresh()->provider_state)->toBe('licence_ordered')->and($box->fresh()->status)->toBe('pending');

    // Retry (twice): no second licence, one mailbox.
    (new CreateMailboxJob($box->id, 'Secret-Pass-123'))->handle($p);
    (new CreateMailboxJob($box->id, 'Secret-Pass-123'))->handle($p);
    expect(collect($p->calls)->where(0, 'orderLicence')->count())->toBe(1)
        ->and(collect($p->calls)->where(0, 'createMailbox')->count())->toBe(2)   // failed once + succeeded once
        ->and($box->fresh()->status)->toBe('active')
        ->and($box->fresh()->provider_reference)->not->toBeNull();
});

test('the password is shown once and never stored; provider logs redact secrets', function () {
    [$owner, $site] = emailSite('growth');
    $d = emailOn($site, $owner);

    $lw = Livewire::actingAs($owner)->test(SiteMailboxesPage::class, ['site' => $site])
        ->set('localPart', 'office')->set('displayName', 'Office')
        ->call('createMailbox')
        ->assertHasNoErrors();
    $shown = $lw->get('shownPassword');
    expect($shown['address'])->toBe('office@'.$site->domain)->and(strlen($shown['password']))->toBe(20);

    $row = $d->mailboxes()->where('local_part', 'office')->first()->toArray();
    expect(json_encode($row))->not->toContain($shown['password']);

    $lw->call('dismissPassword')->assertSet('shownPassword', null);

    expect(OpenproviderClient::redact(['password' => 'x', 'nested' => ['password_confirmation' => 'y', 'token' => 'z'], 'mailbox' => 'office']))
        ->toBe(['password' => '[redacted]', 'nested' => ['password_confirmation' => '[redacted]', 'token' => '[redacted]'], 'mailbox' => 'office']);
});

test('switching on over another provider\'s MX needs explicit confirmation', function () {
    [$owner, $site] = emailSite('growth');
    $dns = Mockery::mock(EmailDns::class, [app(EmailProvider::class)])->makePartial();
    $dns->shouldReceive('hasForeignMx')->andReturn(true);
    app()->instance(EmailDns::class, $dns);

    expect(fn () => app(BusinessEmail::class)->enable($site, $owner))->toThrow(ValidationException::class, 'Confirm the switch');
    $d = app(BusinessEmail::class)->enable($site, $owner, confirmMxChange: true);
    expect($d->mx_change_confirmed_at)->not->toBeNull();
});

// ── Cancellation ───────────────────────────────────────────────────

test('cancellation suspends on our side only; management is locked; the purge runs after the export window', function () {
    [$owner, $site] = emailSite('growth');
    $d = emailOn($site, $owner);
    $svc = app(BusinessEmail::class);
    [$box] = $svc->createMailbox($d, $owner, 'keep');
    $p = fakeProvider();
    $callsBefore = count($p->calls);

    $svc->suspendAccount($owner);
    expect($d->fresh()->status)->toBe('suspended')
        ->and($d->fresh()->delete_after->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($box->fresh()->status)->toBe('suspended')
        ->and(count($p->calls))->toBe($callsBefore);                       // nothing touched at the provider

    expect(fn () => $svc->resetPassword($box->fresh(), $owner))->toThrow(ValidationException::class, 'suspended');

    // Inside the window: resubscribing restores.
    $svc->restoreAccount($owner);
    expect($d->fresh()->status)->toBe('active')->and($box->fresh()->status)->toBe('active');

    // Window passed: the purge deletes at the provider.
    $svc->suspendAccount($owner);
    $d->update(['delete_after' => now()->subDay()]);
    $this->artisan('email:purge-suspended')->assertSuccessful();
    $this->artisan('email:purge-suspended')->assertSuccessful();
    expect(collect($p->calls)->pluck(0))->toContain('deleteMailbox', 'removeDomain')
        ->and(EmailDomain::find($d->id))->toBeNull();
});

test('a domain bought through the app gets its email records written automatically', function () {
    config([
        'domains.driver' => 'openprovider', 'email.driver' => 'openprovider',
        'openprovider' => ['url' => 'https://op.test/v1', 'username' => 'u', 'password' => 'p', 'token_ttl' => 60, 'timeout' => 5],
        'email.records.spf_include' => 'include:_spf.mail.test',
    ]);
    Cache::forget(OpenproviderClient::TOKEN_CACHE_KEY);
    app()->forgetInstance(EmailProvider::class);

    [$owner, $site] = emailSite('growth');
    DomainOrder::create(['user_id' => $owner->id, 'site_id' => $site->id, 'domain' => $site->domain, 'type' => 'register', 'years' => 1, 'price_cents' => 999, 'status' => 'registered']);

    Http::fake([
        '*/auth/login' => Http::response(['code' => 0, 'data' => ['token' => 't']]),
        '*/mailcow/domains' => Http::response(['code' => 0, 'data' => ['status' => 'ok']]),
        '*/mailcow/dkim*' => Http::response(['code' => 0, 'data' => ['txt_record' => ['name' => 'dkim._domainkey.'.$site->domain, 'record_type' => 'TXT', 'value' => 'v=DKIM1; k=rsa; p=REALKEY']]]),
        '*/dns/zones/'.$site->domain.'/records*' => Http::response(['code' => 0, 'data' => ['results' => [
            ['name' => $site->domain, 'type' => 'A', 'value' => '72.61.17.72', 'ttl' => 900],
            ['name' => 'www.'.$site->domain, 'type' => 'CNAME', 'value' => $site->domain, 'ttl' => 900],
            ['name' => $site->domain, 'type' => 'TXT', 'value' => 'v=spf1 include:_spf.google.com ~all', 'ttl' => 900],
        ]]]),
        '*/dns/zones/'.$site->domain => Http::response(['code' => 0, 'data' => ['success' => true]]),
    ]);

    $d = app(BusinessEmail::class)->enable($site, $owner);
    expect($d->fresh()->source)->toBe('openprovider');

    Http::assertSent(function ($req) use ($site) {
        if ($req->method() !== 'PUT' || ! str_ends_with($req->url(), '/dns/zones/'.$site->domain)) {
            return false;
        }
        $r = $req['records'];
        $added = collect($r['add'] ?? []);

        return $added->contains(fn ($x) => $x['type'] === 'MX' && $x['value'] === 'mx1.mail.test' && $x['prio'] === 10)
            && $added->contains(fn ($x) => $x['type'] === 'TXT' && $x['name'] === 'dkim._domainkey' && str_contains($x['value'], 'REALKEY'))
            && $added->contains(fn ($x) => $x['type'] === 'TXT' && $x['name'] === '_dmarc' && str_starts_with($x['value'], 'v=DMARC1; p=none'))
            // the one SPF is updated in place, keeping Google's include
            && ($r['update'][0]['record']['value'] ?? null) === 'v=spf1 include:_spf.google.com include:_spf.mail.test ~all'
            && ! $added->contains(fn ($x) => str_starts_with($x['value'], 'v=spf1'))
            // website records are never touched
            && ! collect($r['remove'] ?? [])->contains(fn ($x) => in_array($x['type'], ['A', 'CNAME'], true));
    });
});
