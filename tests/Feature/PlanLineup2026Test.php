<?php

use App\Exceptions\PlanLimitReached;
use App\Livewire\BookingsPage;
use App\Livewire\DomainSearch;
use App\Livewire\GoLivePage;
use App\Livewire\InvoicesPage;
use App\Livewire\MediaPicker;
use App\Livewire\SubscriptionPage;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\MembershipPlan;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainPurchase;
use App\Services\LiveShell;
use App\Services\MediaStore;
use App\Support\PlanCatalog;
use App\Support\TemplatePaths;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

afterEach(fn () => PlanCatalog::refresh());

/** An account on $plan (active) with one site. */
function lineupSite(string $plan, array $site = []): array
{
    $owner = User::factory()->create();
    $owner->currentSubscription()->update(['plan' => $plan, 'status' => 'active']);
    $s = Site::create($site + ['user_id' => $owner->id, 'name' => 'pl-'.uniqid(), 'domain' => '', 'owner' => 'x', 'description' => 't', 'template' => 'blank']);
    $s->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);

    return [$owner->fresh(), $s];
}

test('the 2026 line-up: prices, annual prices and limits', function () {
    $t = config('plans.tiers');
    expect([$t['free']['price_cents'], $t['starter']['price_cents'], $t['growth']['price_cents'], $t['pro']['price_cents'], $t['enterprise']['price_cents']])
        ->toBe([0, 1900, 4500, 7900, 14900])
        ->and($t['starter']['annual_price_cents'])->toBe(19000)
        ->and($t['enterprise']['price_prefix'])->toBe('From')
        ->and($t['growth']['highlight'])->toBeTrue()
        ->and([$t['starter']['limits']['mailboxes'], $t['growth']['limits']['mailboxes'], $t['pro']['limits']['mailboxes']])->toBe([0, 5, 10])
        ->and([$t['free']['limits']['storage_mb'], $t['starter']['limits']['storage_mb'], $t['growth']['limits']['storage_mb'], $t['pro']['limits']['storage_mb']])->toBe([1024, 5120, 20480, 51200])
        ->and($t['free']['limits']['custom_domain'])->toBeFalse()
        ->and(config('plans.compare.Bookings.growth'))->toBe('Up to 3 staff calendars, deposits')
        // October 2026 table: Growth 3 sites, Pro up to 10 staff, storage per mailbox.
        ->and([$t['trial']['limits']['sites'], $t['starter']['limits']['sites'], $t['growth']['limits']['sites'], $t['pro']['limits']['sites'], $t['enterprise']['limits']['sites']])->toBe([1, 1, 3, 10, null])
        ->and($t['trial']['limits']['storage_mb'])->toBe(1024)
        ->and([$t['growth']['limits']['mailbox_storage_gb'], $t['pro']['limits']['mailbox_storage_gb']])->toBe([10, 25])
        ->and($t['pro']['limits']['staff_calendars'])->toBe(10)
        ->and($t['pro']['annual_price_cents'])->toBe(79000);

    [$owner] = lineupSite('starter');
    expect($owner->currentSubscription()->paymentFeePct())->toBe(1.0);
    $owner->currentSubscription()->update(['plan' => 'growth']);
    expect($owner->fresh()->currentSubscription()->paymentFeePct())->toBe(0.5);
    $owner->currentSubscription()->update(['plan' => 'pro']);
    expect($owner->fresh()->currentSubscription()->paymentFeePct())->toBe(0.0);
});

test('the line-up migration rewrites admin-edited built-in plans but leaves custom plans alone', function () {
    MembershipPlan::updateOrCreate(['key' => 'starter'], ['data' => ['name' => 'Starter Renamed', 'price_cents' => 2500, 'order' => 7, 'limits' => ['mailboxes' => 5, 'sites' => 1]]]);
    MembershipPlan::updateOrCreate(['key' => 'custom_x'], ['data' => ['name' => 'Custom X', 'price_cents' => 3000, 'highlight' => true, 'limits' => ['sites' => 2]]]);
    PlanCatalog::refresh();
    expect(config('plans.tiers.starter.price_cents'))->toBe(2500);

    (require database_path('migrations/2026_10_04_000005_apply_2026_plan_lineup.php'))->up();

    expect(config('plans.tiers.starter.price_cents'))->toBe(1900)
        ->and(config('plans.tiers.starter.name'))->toBe('Starter Renamed')     // admin's own choices kept
        ->and(config('plans.tiers.starter.order'))->toBe(7)
        ->and(config('plans.tiers.starter.limits.mailboxes'))->toBe(0)
        ->and(config('plans.tiers.starter.limits.invoices_month'))->toBe(10)
        ->and(config('plans.tiers.custom_x.price_cents'))->toBe(3000)
        ->and(config('plans.tiers.custom_x.highlight'))->toBeFalse();          // one recommended plan: Growth
});

test('mailboxes an account already had are grandfathered, and an upgrade never caps lower', function () {
    [$owner] = lineupSite('starter');
    $sub = $owner->currentSubscription();
    expect($sub->mailboxLimit())->toBe(0);

    $sub->update(['grandfathered_mailboxes' => 5]);
    expect($sub->fresh()->mailboxLimit())->toBe(5)
        ->and($sub->fresh()->mailboxLimitOn('growth'))->toBe(5)
        ->and($sub->fresh()->mailboxLimitOn('pro'))->toBe(10);
});

test('Free: the 21st online booking of the month is refused politely and the owner is alerted once', function () {
    [$owner, $site] = lineupSite('free');
    $site->enableFeature('bookings');
    $site->services()->create(['name' => 'Trim', 'slug' => 'trim', 'kind' => 'slot', 'duration_min' => 30,
        'price_cents' => 0, 'currency' => 'gbp', 'is_active' => true]);
    foreach (range(1, 20) as $i) {
        Booking::create(['site_id' => $site->id, 'customer_name' => "C{$i}", 'customer_email' => "c{$i}@x.test", 'status' => 'confirmed',
            'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addMinutes(30)]);
    }

    $start = now()->next('Tuesday')->setTime(11, 0)->format('Y-m-d H:i:s');
    $this->postJson("/api/sites/{$site->name}/booking", ['service' => 'trim', 'name' => 'Late', 'email' => 'l@x.test', 'start' => $start])
        ->assertStatus(422)->assertJsonPath('message', 'Online booking is full this month — please contact us to book.');
    $this->postJson("/api/sites/{$site->name}/booking", ['service' => 'trim', 'name' => 'Later', 'email' => 'l2@x.test', 'start' => $start])
        ->assertStatus(422);

    expect(Alert::where('site_id', $site->id)->where('type', 'plan_limit')->count())->toBe(1)
        ->and($owner->currentSubscription()->bookingsThisMonth())->toBe(20);
});

test('staff calendars are capped by plan (Starter 1); existing ones stay', function () {
    [$owner, $site] = lineupSite('starter');
    $site->enableFeature('bookings');

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('newSiteResource')->set('srName', 'Bella')->set('srCapacity', 1)->call('saveSiteResource')
        ->assertNotDispatched('upgrade-required');
    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->call('newSiteResource')->set('srName', 'Cleo')->set('srCapacity', 1)->call('saveSiteResource')
        ->assertDispatched('upgrade-required');
    expect($site->resources()->pluck('name')->all())->toBe(['Bella']);

    $owner->currentSubscription()->update(['plan' => 'growth']);
    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site->fresh()])
        ->call('newSiteResource')->set('srName', 'Cleo')->set('srCapacity', 1)->call('saveSiteResource')
        ->assertNotDispatched('upgrade-required');
    expect($site->resources()->count())->toBe(2);
});

test('Starter: 10 invoices a month, no recurring invoices; Growth unlimited', function () {
    Mail::fake();
    [$owner, $site] = lineupSite('starter');
    $site->enableFeature('invoices');
    foreach (range(1, 10) as $i) {
        Invoice::create(['site_id' => $site->id, 'number' => 'T-'.$i.'-'.uniqid(), 'customer_name' => 'X', 'customer_email' => 'x@x.test',
            'items' => [], 'subtotal_cents' => 0, 'tax_bp' => 0, 'tax_cents' => 0, 'total_cents' => 0, 'currency' => 'gbp', 'status' => 'draft']);
    }
    $fill = fn ($lw) => $lw->set('customerName', 'Ivy')->set('customerEmail', 'ivy@x.test')
        ->set('items', [['description' => 'Work', 'qty' => 1, 'price' => '50']]);

    $fill(Livewire::actingAs($owner)->test(InvoicesPage::class, ['site' => $site]))->call('saveInvoice')
        ->assertDispatched('upgrade-required');
    expect(Invoice::where('site_id', $site->id)->count())->toBe(10);

    $owner->currentSubscription()->update(['plan' => 'growth']);
    $fill(Livewire::actingAs($owner)->test(InvoicesPage::class, ['site' => $site]))->set('recurInterval', 'monthly')->call('saveInvoice')
        ->assertDispatched('upgrade-required');                                    // recurring is Pro+
    $fill(Livewire::actingAs($owner)->test(InvoicesPage::class, ['site' => $site]))->call('saveInvoice')
        ->assertNotDispatched('upgrade-required');
    expect(Invoice::where('site_id', $site->id)->count())->toBe(11);
});

test('turning deposits on needs Growth or above', function () {
    [$owner, $site] = lineupSite('starter');
    $site->enableFeature('bookings');

    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->set('wizStep', 2)->set('name', 'Colour')->set('price', '60')->set('depositMode', 'pct')->set('depositValue', '30')
        ->call('wizNext')
        ->assertDispatched('upgrade-required')
        ->assertSet('wizStep', 2);

    $owner->currentSubscription()->update(['plan' => 'growth']);
    Livewire::actingAs($owner)->test(BookingsPage::class, ['site' => $site])
        ->set('wizStep', 2)->set('name', 'Colour')->set('price', '60')->set('depositMode', 'pct')->set('depositValue', '30')
        ->call('wizNext')
        ->assertNotDispatched('upgrade-required')
        ->assertSet('wizStep', 3);
});

test('Free stays on its olux address: connecting a domain asks to upgrade, buying one requires a paid plan', function () {
    [$owner, $site] = lineupSite('free');

    Livewire::actingAs($owner)->test(GoLivePage::class, ['site' => $site])
        ->call('chooseConnect')->assertDispatched('upgrade-required')->assertSet('flow', null)
        ->set('domain', 'mine.co.uk')->call('saveDomain')->assertDispatched('upgrade-required');
    expect($site->fresh()->domain)->toBe('');

    Livewire::actingAs($owner)->test(DomainSearch::class, ['site' => $site])
        ->assertSet('plan', 'starter')
        ->assertDontSee('Free ·');
});

test('the plan\'s free domain: Growth gets one .co.uk free, Pro any domain; once per account; renewals charge', function () {
    config(['domains.driver' => 'fake', 'services.stripe_platform.secret' => null]);
    $svc = app(DomainPurchase::class);

    [$growth, $gs] = lineupSite('growth');
    expect($svc->priceForAccount($growth, 'salon.co.uk'))->toBe(0)
        ->and($svc->priceForAccount($growth, 'salon.com'))->toBe($svc->priceFor('salon.com'));

    $svc->preparePayment($growth, $gs, $d = 'free-'.uniqid().'.co.uk', null);
    expect(DomainOrder::where('domain', $d)->value('price_cents'))->toBe(0)
        ->and(DomainOrder::where('domain', $d)->value('status'))->toBe('registered')
        ->and($svc->priceForAccount($growth, 'another.co.uk'))->toBe($svc->priceFor('another.co.uk'));   // used

    [$pro] = lineupSite('pro');
    expect($svc->priceForAccount($pro, 'studio.com'))->toBe(0);

    // A trial account buying Growth alongside gets the .co.uk free too.
    $trial = User::factory()->create();
    expect($svc->priceForAccount($trial, 'trial.co.uk', 'growth'))->toBe(0)
        ->and($svc->priceForAccount($trial, 'trial.co.uk', 'starter'))->toBe($svc->priceFor('trial.co.uk'));

    $prep = $svc->prepareRenewalPayment($growth, DomainOrder::where('domain', $d)->first());
    expect($prep['order']->price_cents)->toBe($svc->priceFor($d));
});

test('the storage pool is enforced on every upload path, not just the Assets page', function () {
    Storage::fake('public');
    [$owner, $site] = lineupSite('free');
    Media::create(['site_id' => $site->id, 'name' => 'big', 'file_type' => 'image', 'url' => '/x', 'size' => '1 GB', 'bytes' => 1024 * 1024 * 1024]);

    expect(fn () => app(MediaStore::class)->store($site, UploadedFile::fake()->image('a.jpg')))->toThrow(PlanLimitReached::class);

    Livewire::actingAs($owner)->test(MediaPicker::class, ['siteId' => $site->id])
        ->set('uploads', [UploadedFile::fake()->image('b.jpg')])
        ->assertDispatched('upgrade-required');
    expect(Media::where('site_id', $site->id)->count())->toBe(1);
});

test('Free live sites carry the Made with Olux badge; paid plans don\'t', function () {
    $key = 'pltest-'.uniqid();
    File::ensureDirectoryExists(TemplatePaths::shellDir($key));
    File::put(TemplatePaths::shellDir($key).'/index.html', '<html><head></head><body><div id="__nuxt"></div></body></html>');
    try {
        [, $free] = lineupSite('free', ['template' => $key]);
        [, $paid] = lineupSite('starter', ['template' => $key]);

        expect((string) app(LiveShell::class)->respond($free)->getContent())->toContain('Made with Olux')
            ->and((string) app(LiveShell::class)->respond($paid)->getContent())->not->toContain('Made with Olux');
    } finally {
        File::deleteDirectory(TemplatePaths::shellDir($key));
    }
});

test('the subscription page shows the new prices, annual prices and the comparison table', function () {
    [$owner] = lineupSite('starter');

    Livewire::actingAs($owner)->test(SubscriptionPage::class)
        ->assertSee('£45.00')
        ->assertSee('or £450/year')
        ->assertSee('Compare every spec')
        ->assertSee('Storage per mailbox')
        ->assertSee('Olux fee on online payments')
        ->assertSee('Up to 3 staff calendars, deposits')
        ->assertSee('From');
});
