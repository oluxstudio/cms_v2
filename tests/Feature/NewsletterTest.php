<?php

use App\Livewire\NewsletterPage;
use App\Models\AccountMember;
use App\Models\Role;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Newsletter\Jobs\DispatchScheduledCampaigns;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Mail\NewsletterConfirmation;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterSend;
use App\Modules\Newsletter\Services\CampaignRenderer;
use App\Modules\Newsletter\Services\CampaignSender;
use App\Modules\Newsletter\Services\SubscriberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

/** @return array{0: User, 1: Site} an owner + a site with the Newsletter add-on installed. */
function newsletterSite(array $config = [], string $plan = 'growth'): array
{
    $owner = User::factory()->create();
    $owner->currentSubscription()->update(['plan' => $plan, 'status' => 'active']);
    $site = Site::create([
        'user_id' => $owner->id, 'name' => 'nl-'.uniqid(), 'domain' => 'nl-'.uniqid().'.test',
        'owner' => $owner->name, 'description' => 'newsletter test',
    ]);
    $site->enableFeature('newsletter', $config);

    return [$owner, $site->fresh()];
}

function newsletterSub(Site $site, string $email, array $extra = []): Subscription
{
    return Subscription::create($extra + ['site_id' => $site->id, 'email' => $email, 'status' => Subscription::SUBSCRIBED]);
}

function newsletterCampaign(Site $site, array $extra = []): NewsletterCampaign
{
    return NewsletterCampaign::create($extra + [
        'site_id' => $site->id, 'subject' => 'October news', 'status' => 'draft',
        'body' => '<p>Hello there</p><p><a href="https://example.com/offer">See the offer</a></p>',
    ]);
}

test('double opt-in: signup is pending, gets a confirmation email, and the link confirms it', function () {
    Mail::fake();
    [, $site] = newsletterSite(['double_opt_in' => true]);

    $this->postJson("/api/sites/{$site->name}/subscribe", ['email' => 'Ada@Example.com', 'name' => 'Ada'])
        ->assertCreated()->assertJson(['status' => 'pending']);

    $sub = Subscription::where('site_id', $site->id)->where('email', 'ada@example.com')->first();
    expect($sub->status)->toBe('pending')->and($sub->token)->not->toBeEmpty();
    Mail::assertQueued(NewsletterConfirmation::class, fn ($m) => $m->hasTo('ada@example.com'));

    // Signing up again while pending re-sends, rather than 409.
    $this->postJson("/api/sites/{$site->name}/subscribe", ['email' => 'ada@example.com'])->assertOk()->assertJson(['status' => 'pending']);

    $this->get(route('newsletter.confirm', $sub->token))->assertOk()->assertSee('You&#039;re subscribed', false);
    expect($sub->fresh()->status)->toBe('subscribed')->and($sub->fresh()->confirmed_at)->not->toBeNull();

    $this->postJson("/api/sites/{$site->name}/subscribe", ['email' => 'ada@example.com'])->assertStatus(409);
});

test('without the add-on (or with double opt-in off) the signup API keeps its old contract', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $plain = Site::create(['user_id' => $owner->id, 'name' => 'nl-plain-'.uniqid(), 'domain' => 'p-'.uniqid().'.test', 'owner' => 'x', 'description' => 'x']);

    $this->postJson("/api/sites/{$plain->name}/subscribe", ['email' => 'bo@example.com'])
        ->assertCreated()->assertJson(['message' => 'Thank you for subscribing!', 'status' => 'subscribed']);
    $this->postJson("/api/sites/{$plain->name}/subscribe", ['email' => 'bo@example.com'])->assertStatus(409);
    $this->postJson("/api/sites/{$plain->name}/unsubscribe", ['email' => 'bo@example.com'])->assertOk();
    expect(Subscription::where('site_id', $plain->id)->first()->status)->toBe('unsubscribed');
    $this->postJson("/api/sites/{$plain->name}/subscribe", ['email' => 'bo@example.com'])->assertOk()->assertJson(['status' => 'subscribed']);
    Mail::assertNothingQueued();

    [, $site] = newsletterSite(['double_opt_in' => false]);
    $this->postJson("/api/sites/{$site->name}/subscribe", ['email' => 'cy@example.com', 'tags' => 'vip, news'])->assertCreated();
    expect(Subscription::where('site_id', $site->id)->first()->tags)->toBe(['vip', 'news']);

    $this->getJson("/api/sites/{$site->name}/newsletter")->assertOk()
        ->assertJson(['enabled' => true, 'double_opt_in' => false]);
});

test('unsubscribe: GET shows a confirm button, POST unsubscribes, one-click POST works without CSRF', function () {
    [, $site] = newsletterSite();
    $a = newsletterSub($site, 'a@example.com');
    $b = newsletterSub($site, 'b@example.com');

    $this->get(route('newsletter.unsubscribe', $a->token))->assertOk()->assertSee('Unsubscribe?')->assertSee('<form method="POST"', false);
    expect($a->fresh()->status)->toBe('subscribed'); // GET alone never unsubscribes

    $this->post(route('newsletter.unsubscribe.post', $a->token))->assertOk()->assertSee('unsubscribed');
    expect($a->fresh()->status)->toBe('unsubscribed')->and($a->fresh()->unsubscribed_at)->not->toBeNull();

    // RFC 8058: a mail provider POSTs "List-Unsubscribe=One-Click" (no CSRF token, no session).
    $campaign = newsletterCampaign($site, ['status' => 'sent']);
    $send = NewsletterSend::create(['campaign_id' => $campaign->id, 'site_id' => $site->id, 'subscription_id' => $b->id, 'email' => $b->email, 'status' => 'sent']);
    $this->call('POST', route('newsletter.unsubscribe.post', ['token' => $b->token, 'c' => $send->token]), ['List-Unsubscribe' => 'One-Click'])
        ->assertOk()->assertSee('Unsubscribed');
    expect($b->fresh()->status)->toBe('unsubscribed')
        ->and($send->fresh()->unsubscribed_at)->not->toBeNull()
        ->and($campaign->fresh()->unsubscribes_count)->toBe(1);

    $this->get(route('newsletter.unsubscribe', 'nope'))->assertNotFound();
});

test('CSV import adds, tags and skips; export returns the filtered list', function () {
    [$owner, $site] = newsletterSite();
    newsletterSub($site, 'gone@example.com', ['status' => 'unsubscribed']);
    $csv = "email,name,tags\nnew@example.com,New Person,vip;news\nGONE@example.com,Gone,vip\nnot-an-email,X,\n=evil@example.com,Formula,\n";

    Livewire::actingAs($owner)->test(NewsletterPage::class, ['site' => $site])
        ->set('importFile', UploadedFile::fake()->createWithContent('list.csv', $csv))
        ->set('importTags', 'imported')
        ->call('importCsv')
        ->assertSet('importResult', ['added' => 2, 'updated' => 1, 'skipped' => 1]);

    $new = Subscription::where('site_id', $site->id)->where('email', 'new@example.com')->first();
    expect($new->status)->toBe('subscribed')->and($new->tags)->toBe(['vip', 'news', 'imported']);
    $gone = Subscription::where('site_id', $site->id)->where('email', 'gone@example.com')->first();
    expect($gone->status)->toBe('unsubscribed')->and($gone->tags)->toBe(['vip', 'imported']); // never re-subscribed

    $svc = app(SubscriberService::class);
    expect($svc->csv($svc->query($site, 'evil')))->toContain("'=evil@example.com"); // formula-neutralised
    $out = $svc->csv($svc->query($site, '', 'subscribed', 'vip'));
    expect($out)->toContain('email,name,status,tags')->toContain('new@example.com,"New Person",subscribed,vip;news;imported')
        ->not->toContain('gone@example.com');

    Livewire::actingAs($owner)->test(NewsletterPage::class, ['site' => $site])
        ->call('exportCsv')->assertFileDownloaded($site->name.'-subscribers-'.now()->format('Y-m-d').'.csv');
});

test('sending a campaign mails each subscribed member of the audience individually with their own unsubscribe link', function () {
    Mail::fake();
    [$owner, $site] = newsletterSite();
    $vip1 = newsletterSub($site, 'vip1@example.com', ['tags' => ['vip']]);
    $vip2 = newsletterSub($site, 'vip2@example.com', ['tags' => ['vip', 'news']]);
    newsletterSub($site, 'plain@example.com');
    newsletterSub($site, 'pending@example.com', ['status' => 'pending', 'tags' => ['vip']]);
    newsletterSub($site, 'left@example.com', ['status' => 'unsubscribed', 'tags' => ['vip']]);
    $campaign = newsletterCampaign($site, ['audience_tag' => 'vip']);

    Livewire::actingAs($owner)->test(NewsletterPage::class, ['site' => $site])
        ->call('editCampaign', $campaign->id)
        ->call('sendNow')
        ->assertHasNoErrors()->assertSet('limitError', null);

    $campaign->refresh();
    expect($campaign->status)->toBe('sent')->and($campaign->recipients_count)->toBe(2)->and($campaign->sent_count)->toBe(2);

    Mail::assertSent(NewsletterCampaignMail::class, 2);
    foreach ([$vip1, $vip2] as $sub) {
        $send = NewsletterSend::where('campaign_id', $campaign->id)->where('subscription_id', $sub->id)->first();
        expect($send->status)->toBe('sent');
        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $m) use ($sub, $send) {
            $html = $m->render();

            return $m->hasTo($sub->email) && count($m->to) === 1 && empty($m->bcc)
                && str_contains($m->unsubscribeUrl, $sub->token)
                && str_contains($html, e($m->unsubscribeUrl))
                && str_contains($html, route('newsletter.open', $send->token))
                && ! str_contains($html, 'href="https://example.com/offer"');
        });
    }
    Mail::assertNotSent(NewsletterCampaignMail::class, fn ($m) => $m->hasTo('plain@example.com') || $m->hasTo('pending@example.com') || $m->hasTo('left@example.com'));

    // One-click headers on every real message.
    $mail = Mail::sent(NewsletterCampaignMail::class)->first();
    expect($mail->headers()->text['List-Unsubscribe'])->toBe('<'.$mail->unsubscribeUrl.'>')
        ->and($mail->headers()->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
});

test('open pixel and signed click redirect are tracked once per recipient', function () {
    [, $site] = newsletterSite();
    $sub = newsletterSub($site, 't@example.com');
    $campaign = newsletterCampaign($site, ['status' => 'sent', 'sent_count' => 1]);
    $send = NewsletterSend::create(['campaign_id' => $campaign->id, 'site_id' => $site->id, 'subscription_id' => $sub->id, 'email' => $sub->email, 'status' => 'sent']);

    $this->get(route('newsletter.open', $send->token))->assertOk()->assertHeader('Content-Type', 'image/gif');
    $this->get(route('newsletter.open', $send->token))->assertOk();
    expect($send->fresh()->open_count)->toBe(2)->and($campaign->fresh()->opens_count)->toBe(1);

    $html = app(CampaignRenderer::class)->personalise('<a href="https://example.com/offer?x=1&amp;y=2">Go</a>', $send->token);
    preg_match('/href="([^"]+)"/', $html, $m);
    $tracked = html_entity_decode($m[1]);
    $this->get($tracked)->assertRedirect('https://example.com/offer?x=1&y=2');
    $this->get($tracked)->assertRedirect();
    expect($send->fresh()->click_count)->toBe(2)->and($campaign->fresh()->clicks_count)->toBe(1);

    // Tampered target → signature fails, no redirect.
    $this->get(str_replace('example.com', 'evil.test', $tracked))->assertForbidden();
});

test('the plan send limit blocks a campaign that would go over, with an upgrade message', function () {
    Mail::fake();
    config(['plans.tiers.starter.limits.newsletter_sends_month' => 3]);
    [$owner, $site] = newsletterSite([], 'starter');
    foreach (range(1, 4) as $i) {
        newsletterSub($site, "l{$i}@example.com");
    }
    $campaign = newsletterCampaign($site);

    Livewire::actingAs($owner)->test(NewsletterPage::class, ['site' => $site])
        ->assertViewHas('quota', fn ($q) => $q['limit'] === 3 && $q['left'] === 3)
        ->call('editCampaign', $campaign->id)
        ->call('sendNow')
        ->assertSet('limitError', fn ($e) => str_contains($e, 'Upgrade your plan'))
        ->assertSee(route('account.subscription'));

    expect($campaign->fresh()->status)->toBe('draft');
    Mail::assertNothingSent();

    // The scheduler applies the same limit and parks the campaign as a draft with the reason.
    $campaign->update(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
    (new DispatchScheduledCampaigns)->handle(app(CampaignSender::class));
    expect($campaign->fresh()->status)->toBe('draft')->and($campaign->fresh()->error)->toContain('Upgrade');

    // Within the limit, a due scheduled campaign goes out.
    config(['plans.tiers.starter.limits.newsletter_sends_month' => 10]);
    $campaign->refresh()->update(['status' => 'scheduled', 'scheduled_at' => now()->subMinute(), 'error' => null]);
    (new DispatchScheduledCampaigns)->handle(app(CampaignSender::class));
    expect($campaign->fresh()->error)->toBeNull()->and($campaign->fresh()->status)->toBe('sent');
    Mail::assertSent(NewsletterCampaignMail::class, 4);
});

test('changes need newsletter.manage — a view-only member can look but not act', function () {
    Mail::fake();
    [$owner, $site] = newsletterSite();
    $sub = newsletterSub($site, 'keep@example.com');
    $campaign = newsletterCampaign($site);

    $role = Role::create(['account_id' => $owner->id, 'name' => 'NL viewer', 'slug' => 'nl-viewer-'.uniqid(), 'permissions' => ['newsletter.view']]);
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $role->id, 'site_id' => $site->id]);

    $this->actingAs($member)->get("/{$site->name}/newsletter")->assertOk()->assertDontSee('New subscriber');

    $page = fn () => Livewire::actingAs($member)->test(NewsletterPage::class, ['site' => $site]);
    $page()->assertViewHas('canManage', false);
    $page()->call('deleteSubscriber', $sub->id)->assertForbidden();
    $page()->call('unsubscribeSubscriber', $sub->id)->assertForbidden();
    $page()->call('editCampaign', $campaign->id)->assertForbidden();
    $page()->call('deleteCampaign', $campaign->id)->assertForbidden();
    $page()->set('addEmail', 'x@example.com')->call('addSubscriber')->assertForbidden();
    expect($sub->fresh()->status)->toBe('subscribed')->and(NewsletterCampaign::find($campaign->id))->not->toBeNull();

    // No newsletter.view at all → the page itself is forbidden.
    $noRole = Role::create(['account_id' => $owner->id, 'name' => 'None', 'slug' => 'none-'.uniqid(), 'permissions' => ['pages.view']]);
    $other = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $other->id, 'role_id' => $noRole->id, 'site_id' => $site->id]);
    $this->actingAs($other)->get("/{$site->name}/newsletter")->assertForbidden();

    // The owner can.
    Livewire::actingAs($owner)->test(NewsletterPage::class, ['site' => $site])->call('deleteSubscriber', $sub->id)->assertOk();
    expect(Subscription::find($sub->id))->toBeNull();
});

test('the admin page renders its rails, tabs and campaign editor', function () {
    Mail::fake();
    [$owner, $site] = newsletterSite();
    newsletterSub($site, 's1@example.com', ['tags' => ['vip']]);
    newsletterSub($site, 's2@example.com', ['status' => 'pending']);
    newsletterSub($site, 's3@example.com', ['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
    newsletterCampaign($site, ['subject' => 'A sent one', 'status' => 'sent', 'sent_at' => now(), 'sent_count' => 10, 'opens_count' => 4, 'failed_count' => 1]);
    newsletterCampaign($site, ['subject' => 'A draft one']);

    $this->actingAs($owner)->get("/{$site->name}/newsletter")->assertOk()
        ->assertSee('Subscribers')->assertSee('Growth this month')->assertSee('Campaigns sent')->assertSee('Average open rate')
        ->assertSee('Unsubscribes')->assertSee('Sends left this month')
        ->assertSee('List health')->assertSee('Needs attention')->assertSee('1 pending confirmation')
        ->assertSee('Recent campaigns')->assertSee('A sent one')->assertSee('Related')
        ->assertSee(route('site.contacts', $site->name))->assertSee(route('site.properties', $site->name));

    $page = Livewire::actingAs($owner)->test(NewsletterPage::class, ['site' => $site])
        ->assertViewHas('stats', fn ($s) => $s['subscribed'] === 1 && $s['pending'] === 1 && $s['unsubscribed'] === 1
            && $s['sentCampaigns'] === 1 && $s['openRate'] === 40 && $s['drafts'] === 1 && $s['failed'] === 1 && $s['unsubMonth'] === 1)
        ->assertSee('s1@example.com');
    $page->call('setStatus', 'pending');
    expect($page->viewData('subscriberList')->pluck('email')->all())->toBe(['s2@example.com']);
    $page->call('setStatus', 'all')->call('setTag', 'vip');
    expect($page->viewData('subscriberList')->pluck('email')->all())->toBe(['s1@example.com']);

    $page->call('setTab', 'campaigns')->assertSee('A draft one')
        ->call('newCampaign')->assertSee('New campaign')->assertSee('Send a test to')
        ->set('subject', 'Fresh news')->set('body', '<p>Hi</p>')
        ->call('sendTest')->assertHasNoErrors();
    Mail::assertSent(NewsletterCampaignMail::class, fn ($m) => $m->isTest && $m->unsubscribeUrl === null && $m->hasTo($owner->email));
    expect(NewsletterCampaign::where('site_id', $site->id)->where('subject', 'Fresh news')->value('status'))->toBe('draft');

    $page->set('scheduleAt', now()->addDay()->format('Y-m-d\TH:i'))->call('schedule')->assertHasNoErrors();
    expect(NewsletterCampaign::where('site_id', $site->id)->where('subject', 'Fresh news')->value('status'))->toBe('scheduled');

    // The feature gate: without the add-on the page 404s.
    $site->disableFeature('newsletter');
    $this->actingAs($owner)->get("/{$site->name}/newsletter")->assertNotFound();
});
