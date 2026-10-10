<?php

use App\Livewire\ReviewsPage;
use App\Models\AccountMember;
use App\Models\Contact;
use App\Models\Media;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Modules\Reviews\Mail\NewReviewNotification;
use App\Modules\Reviews\Mail\ReviewRequestMail;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\Models\ReviewRequest;
use App\Support\SiteProperties;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

function reviewsSite(array $config = []): array
{
    $owner = User::factory()->create();
    $name = 'rv-'.substr(uniqid(), -8);
    $site = Site::create([
        'user_id' => $owner->id, 'name' => $name,
        'domain' => $name.'.test', 'owner' => $owner->name, 'description' => 'test',
    ]);
    $site->enableFeature('reviews', $config);

    return [$owner, $site];
}

function reviewsMake(Site $site, array $attrs = []): Review
{
    $r = new Review;
    $r->forceFill($attrs + [
        'site_id' => $site->id, 'name' => 'Ana', 'email' => 'ana@example.com', 'rating' => 5,
        'title' => 'Lovely', 'body' => 'Really great service.', 'status' => 'published',
        'source' => 'on_site', 'published_at' => $attrs['created_at'] ?? now(),
    ])->save();

    return $r;
}

function reviewsPayload(array $over = []): array
{
    return $over + ['name' => 'Ben', 'email' => 'ben@example.com', 'rating' => 5, 'title' => 'Great', 'body' => 'Wonderful visit, thank you!'];
}

// ── On-site submissions ─────────────────────────────────────────────

test('on-site reviews are pending by default and the owner is told', function () {
    Mail::fake();
    [$owner, $site] = reviewsSite();

    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload())
        ->assertStatus(201)->assertJsonPath('status', 'pending');

    $r = Review::where('site_id', $site->id)->sole();
    expect($r->status)->toBe('pending')->and($r->source)->toBe('on_site')
        ->and($r->ip_hash)->not->toBeNull();
    Mail::assertQueued(NewReviewNotification::class, fn ($m) => $m->hasTo($owner->email) && $m->review->is($r));

    // Same visitor, same words → refused as a double submit.
    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload())->assertStatus(422);
    // Validation.
    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['rating' => 7]))->assertStatus(422)->assertJsonValidationErrors('rating');
});

test('reviews at or above the auto-publish threshold go live at once', function () {
    Mail::fake();
    [, $site] = reviewsSite(['auto_publish_min_stars' => '4']);

    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['rating' => 4, 'body' => 'Four stars from me']))
        ->assertStatus(201)->assertJsonPath('status', 'published');
    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['rating' => 3, 'body' => 'Three stars from me']))
        ->assertStatus(201)->assertJsonPath('status', 'pending');
    // Link-stuffed text is held even at 5 stars.
    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['body' => 'Visit http://spam.test and https://spam2.test']))
        ->assertStatus(201)->assertJsonPath('status', 'pending');

    expect(Review::where('site_id', $site->id)->where('status', 'published')->count())->toBe(1);
    Mail::assertQueued(NewReviewNotification::class, 2);

    // "5" only publishes five-star reviews.
    $site->saveFeatureConfig('reviews', ['auto_publish_min_stars' => '5']);
    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['rating' => 4, 'body' => 'Another four']))
        ->assertJsonPath('status', 'pending');
});

test('the honeypot drops bots silently and the endpoint is throttled', function () {
    [, $site] = reviewsSite();

    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['_hp' => 'gotcha']))->assertStatus(201);
    expect(Review::where('site_id', $site->id)->count())->toBe(0);

    // throttle:leads — 10 a minute per IP (the honeypot hit above counts too).
    $codes = [];
    for ($i = 0; $i < 11; $i++) {
        $codes[] = $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload(['body' => "Review number {$i}"]))->status();
    }
    expect($codes)->toContain(429);
});

test('a site without the add-on has no reviews API', function () {
    [, $site] = reviewsSite();
    $site->disableFeature('reviews');
    $this->getJson("/api/sites/{$site->name}/reviews")->assertNotFound();
    $this->postJson("/api/sites/{$site->name}/reviews", reviewsPayload())->assertNotFound();
});

// ── Public list + JSON-LD ───────────────────────────────────────────

test('the public list serves only published reviews with a correct aggregate', function () {
    [, $site] = reviewsSite();
    reviewsMake($site, ['rating' => 5, 'featured' => true, 'created_at' => now()->subDays(3)]);
    reviewsMake($site, ['rating' => 4, 'name' => 'Cleo', 'created_at' => now()->subDay()]);
    reviewsMake($site, ['rating' => 3, 'name' => 'Dev', 'created_at' => now()->subDays(2)]);
    reviewsMake($site, ['rating' => 1, 'name' => 'Pending Pat', 'status' => 'pending']);
    reviewsMake($site, ['rating' => 1, 'name' => 'Hidden Hal', 'status' => 'hidden']);

    $res = $this->getJson("/api/sites/{$site->name}/reviews")->assertOk()
        ->assertJsonPath('aggregate.count', 3)
        ->assertJsonPath('aggregate.average', 4)
        ->assertJsonPath('aggregate.distribution.5', 1)
        ->assertJsonPath('aggregate.distribution.1', 0)
        ->assertJsonPath('reviews.0.name', 'Cleo')       // newest first
        ->assertJsonPath('meta.total', 3)
        ->assertJsonMissing(['name' => 'Pending Pat'])
        ->assertJsonMissing(['name' => 'Hidden Hal']);
    expect($res->json('reviews.0'))->not->toHaveKey('email');
    expect($res->getContent())->not->toContain('ana@example.com');

    $this->getJson("/api/sites/{$site->name}/reviews?sort=highest")->assertJsonPath('reviews.0.rating', 5);
    $this->getJson("/api/sites/{$site->name}/reviews?featured=1")->assertJsonPath('meta.total', 1)->assertJsonPath('reviews.0.featured', true);
    $this->getJson("/api/sites/{$site->name}/reviews?per_page=2&page=2")->assertJsonPath('meta.last_page', 2)->assertJsonCount(1, 'reviews');
});

test('the JSON-LD is valid schema.org built from Site Properties, published only, and escaped', function () {
    [, $site] = reviewsSite();
    SiteProperties::save($site, ['values' => [
        'site_name' => 'Grace Way '.uniqid(), 'business_type' => 'HairSalon',
        'address_street' => '1 High St', 'address_town' => 'Blackburn', 'address_postcode' => 'BB1 1AA',
    ]]);
    reviewsMake($site, ['rating' => 5, 'title' => 'Evil </script><script>alert(1)</script>', 'body' => 'Bad "quotes" & <b>tags</b>']);
    reviewsMake($site, ['rating' => 4, 'name' => 'Cleo']);
    reviewsMake($site, ['rating' => 1, 'name' => 'Pending Pat', 'status' => 'pending']);

    $res = $this->getJson("/api/sites/{$site->name}/reviews")->assertOk();
    $ld = $res->json('json_ld');
    expect($ld['@context'])->toBe('https://schema.org')
        ->and($ld['@type'])->toBe('HairSalon')
        ->and($ld['name'])->toStartWith('Grace Way')
        ->and($ld['address']['@type'])->toBe('PostalAddress')
        ->and($ld['address']['addressLocality'])->toBe('Blackburn')
        ->and($ld['aggregateRating'])->toMatchArray(['@type' => 'AggregateRating', 'ratingValue' => 4.5, 'reviewCount' => 2, 'bestRating' => 5])
        ->and($ld['review'])->toHaveCount(2)
        ->and($ld['review'][0]['@type'])->toBe('Review')
        ->and($ld['review'][0]['reviewRating']['@type'])->toBe('Rating')
        ->and(collect($ld['review'])->pluck('author.name')->all())->not->toContain('Pending Pat');

    // The ready <script> can't be broken out of, and still decodes to the same object.
    $script = $res->json('json_ld_script');
    expect($script)->toStartWith('<script type="application/ld+json">')->toEndWith('</script>')
        ->and(substr_count($script, '</script>'))->toBe(1)
        ->and($script)->not->toContain('<script>alert');
    $inner = substr($script, strlen('<script type="application/ld+json">'), -strlen('</script>'));
    expect(json_decode($inner, true, 512, JSON_THROW_ON_ERROR))->toEqual($ld);
});

// ── Request flow ────────────────────────────────────────────────────

test('a request is sent, opened, completed once, and shows the thank-you state', function () {
    Mail::fake();
    [$owner, $site] = reviewsSite(['auto_publish_min_stars' => '5']);
    $contact = Contact::create(['site_id' => $site->id, 'name' => 'Zoe Q', 'email' => 'zoe@example.com']);

    Livewire::actingAs($owner)->test(ReviewsPage::class, ['site' => $site])
        ->set('tab', 'requests')->set('rqEmail', 'zoe@example.com')->call('sendOne')->assertHasNoErrors();

    $req = ReviewRequest::where('site_id', $site->id)->sole();
    expect($req->contact_id)->toBe($contact->id)->and($req->name)->toBe('Zoe Q')
        ->and($req->sent_at)->not->toBeNull()->and(strlen($req->token))->toBe(48);
    Mail::assertQueued(ReviewRequestMail::class, fn ($m) => $m->hasTo('zoe@example.com') && ! $m->reminder);
    expect((new ReviewRequestMail($site, $req))->render())->toContain($req->url())
        ->toContain('Thanks for choosing us');

    $this->get("/review/{$req->token}")->assertOk()->assertSee('How did we do?');
    expect($req->fresh()->opened_at)->not->toBeNull();

    $this->post("/review/{$req->token}", ['name' => 'Zoe', 'rating' => 5, 'title' => 'Superb', 'body' => 'Best haircut ever.'])
        ->assertRedirect("/review/{$req->token}");
    $review = Review::where('site_id', $site->id)->sole();
    expect($review->source)->toBe('request')->and($review->request_id)->toBe($req->id)
        ->and($review->status)->toBe('published')->and($review->email)->toBe('zoe@example.com')
        ->and($req->fresh()->completed_at)->not->toBeNull();

    // One review per token.
    $this->post("/review/{$req->token}", ['name' => 'Zoe', 'rating' => 1, 'body' => 'Second go'])->assertRedirect();
    expect(Review::where('site_id', $site->id)->count())->toBe(1);
    $this->get("/review/{$req->token}")->assertOk()->assertSee('Thank you')->assertDontSee('Send review');

    // Unknown tokens 404.
    $this->get('/review/'.str_repeat('x', 48))->assertNotFound();
});

test('request photos land in the site asset library as @media refs', function () {
    Mail::fake();
    Storage::fake('public');
    [, $site] = reviewsSite();
    $req = ReviewRequest::create(['site_id' => $site->id, 'email' => 'p@example.com', 'sent_at' => now()]);

    $this->post("/review/{$req->token}", [
        'name' => 'Pia', 'rating' => 4, 'body' => 'With a photo',
        'photo' => UploadedFile::fake()->image('cut.jpg', 400, 300),
    ])->assertRedirect();

    $review = Review::where('site_id', $site->id)->sole();
    expect($review->photo)->toStartWith('@media/')->and($review->status)->toBe('pending');
    expect(Media::where('site_id', $site->id)->count())->toBe(1);
});

test('bulk requests go to picked contacts and pasted emails, and can be re-sent', function () {
    Mail::fake();
    [$owner, $site] = reviewsSite();
    $a = Contact::create(['site_id' => $site->id, 'name' => 'Amy', 'email' => 'amy@example.com']);

    $page = Livewire::actingAs($owner)->test(ReviewsPage::class, ['site' => $site])
        ->set('tab', 'requests')->assertSee('Amy')
        ->set('pickedContacts', [$a->id])->set('rqPaste', "bo@example.com, not-an-email\ncy@example.com")
        ->call('sendBulk')->assertHasNoErrors();
    expect(ReviewRequest::where('site_id', $site->id)->pluck('email')->sort()->values()->all())
        ->toBe(['amy@example.com', 'bo@example.com', 'cy@example.com']);
    Mail::assertQueued(ReviewRequestMail::class, 3);

    $page->call('resend', ReviewRequest::where('email', 'bo@example.com')->value('id'));
    Mail::assertQueued(ReviewRequestMail::class, 4);
});

test('the reminder command sends one reminder after five days', function () {
    Mail::fake();
    [, $site] = reviewsSite();
    $due = ReviewRequest::create(['site_id' => $site->id, 'email' => 'due@example.com', 'sent_at' => now()->subDays(6)]);
    $fresh = ReviewRequest::create(['site_id' => $site->id, 'email' => 'fresh@example.com', 'sent_at' => now()->subDays(2)]);
    $done = ReviewRequest::create(['site_id' => $site->id, 'email' => 'done@example.com', 'sent_at' => now()->subDays(8), 'completed_at' => now()->subDays(7)]);

    $this->artisan('reviews:remind')->assertSuccessful();
    Mail::assertQueued(ReviewRequestMail::class, fn ($m) => $m->hasTo('due@example.com') && $m->reminder);
    Mail::assertNotQueued(ReviewRequestMail::class, fn ($m) => $m->hasTo('fresh@example.com') || $m->hasTo('done@example.com'));
    expect($due->fresh()->reminder_sent_at)->not->toBeNull()->and($fresh->fresh()->reminder_sent_at)->toBeNull();

    // Only ever one reminder.
    $this->artisan('reviews:remind')->assertSuccessful();
    Mail::assertQueued(ReviewRequestMail::class, 1);
});

// ── Admin ───────────────────────────────────────────────────────────

test('owners approve, hide, feature, reply to, add and delete reviews', function () {
    [$owner, $site] = reviewsSite();
    $r = reviewsMake($site, ['status' => 'pending', 'rating' => 2, 'published_at' => null]);
    $page = Livewire::actingAs($owner)->test(ReviewsPage::class, ['site' => $site]);

    $page->call('approve', $r->id);
    expect($r->fresh()->status)->toBe('published')->and($r->fresh()->published_at)->not->toBeNull();

    $page->call('toggleFeature', $r->id);
    expect($r->fresh()->featured)->toBeTrue();
    $page->call('hide', $r->id);
    expect($r->fresh()->status)->toBe('hidden')->and($r->fresh()->featured)->toBeFalse();

    $page->call('startReply', $r->id)->set('replyBody', 'Sorry to hear this — <b>please</b> call us.')->call('saveReply');
    expect($r->fresh()->reply_body)->toBe('Sorry to hear this — please call us.')->and($r->fresh()->replied_at)->not->toBeNull();

    $page->call('openAdd')->set('m.name', 'Walk-in Will')->set('m.rating', 4)->set('m.body', 'Told us in person.')
        ->set('m.photo', '@media/will.jpg')->call('saveManual')->assertHasNoErrors();
    $manual = Review::where('site_id', $site->id)->where('source', 'manual')->sole();
    expect($manual->status)->toBe('published')->and($manual->photo)->toBe('@media/will.jpg');

    $page->call('deleteReview', $r->id);
    expect(Review::find($r->id))->toBeNull();
});

test('changes need reviews.manage; viewing needs reviews.view', function () {
    [$owner, $site] = reviewsSite();
    $r = reviewsMake($site, ['status' => 'pending']);

    $readOnly = Role::create(['account_id' => $owner->id, 'name' => 'Review reader', 'slug' => 'rr-'.uniqid(), 'permissions' => ['reviews.view']]);
    $member = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $member->id, 'role_id' => $readOnly->id]);

    $this->actingAs($member)->get("/{$site->name}/reviews")->assertOk()->assertDontSee('Add review');
    foreach (['approve', 'hide', 'toggleFeature', 'startReply', 'deleteReview'] as $action) {
        Livewire::actingAs($member)->test(ReviewsPage::class, ['site' => $site])->call($action, $r->id)->assertForbidden();
    }
    Livewire::actingAs($member)->test(ReviewsPage::class, ['site' => $site])
        ->set('rqEmail', 'x@example.com')->call('sendOne')->assertForbidden();
    expect($r->fresh()->status)->toBe('pending');

    $nobody = Role::create(['account_id' => $owner->id, 'name' => 'Nothing', 'slug' => 'no-'.uniqid(), 'permissions' => ['pages.view']]);
    $other = User::factory()->create();
    AccountMember::create(['account_id' => $owner->id, 'user_id' => $other->id, 'role_id' => $nobody->id]);
    $this->actingAs($other)->get("/{$site->name}/reviews")->assertForbidden();
});

test('the admin page renders its three rails and both tabs', function () {
    [$owner, $site] = reviewsSite();
    reviewsMake($site, ['rating' => 5]);
    reviewsMake($site, ['rating' => 2, 'name' => 'Grumpy Gus', 'reply_body' => null]);
    reviewsMake($site, ['rating' => 4, 'name' => 'Waiting Wendy', 'status' => 'pending']);

    $this->actingAs($owner)->get("/{$site->name}/reviews")->assertOk()
        ->assertSee(['Average rating', '3.5 / 5', 'Total reviews', 'Awaiting approval', 'New this month', 'Response rate', 'Requests sent / completed'])
        ->assertSee(['Reviews', 'Requests', 'Pending', 'Published', 'Hidden', 'Featured', 'Add review'])
        ->assertSee(['Rating summary', 'Needs attention', 'Waiting Wendy', 'Grumpy Gus', 'Show reviews on your site', "/api/sites/{$site->name}/reviews", 'application/ld+json'])
        ->assertSee(['Contacts', 'Edit site', 'Site Properties']);

    Livewire::actingAs($owner)->test(ReviewsPage::class, ['site' => $site])
        ->call('setFilter', 'pending')
        ->assertViewHas('reviews', fn ($list) => $list->pluck('name')->all() === ['Waiting Wendy'])
        ->call('setTab', 'requests')->assertSee('Ask one person')->assertSee('Choose from your contacts');

    // The add-on gates the page.
    $site->disableFeature('reviews');
    $this->actingAs($owner)->get("/{$site->name}/reviews")->assertNotFound();
});
