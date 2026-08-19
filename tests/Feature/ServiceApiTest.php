<?php

use App\Models\ApiToken;
use App\Models\Page;
use App\Models\Service;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

function bookingSite(): array
{
    $owner = User::factory()->create();
    $site = Site::factory()->create(['user_id' => $owner->id]);

    return [$owner, $site];
}

function bookingKey(User $owner, Site $site, ?array $abilities): string
{
    $raw = Str::random(64);
    ApiToken::create([
        'user_id' => $owner->id, 'site_id' => $site->id, 'name' => 'seed',
        'token' => hash('sha256', $raw), 'token_preview' => substr($raw, 0, 6),
        'abilities' => $abilities, 'expires_at' => now()->addDay(),
    ]);

    return $raw;
}

test('services can be created, updated and deleted through the token API', function () {
    [$owner, $site] = bookingSite();
    $k = bookingKey($owner, $site, ['bookings.manage']);

    $this->withToken($k)->postJson('/api/site/services', [
        'name' => 'Haircut', 'duration_min' => 45, 'price_cents' => 3800, 'description' => 'Precision cut',
    ])->assertCreated()->assertJsonPath('service.slug', 'haircut')->assertJsonPath('service.kind', 'slot');

    // Creating a service switches the bookings feature on.
    expect($site->fresh()->hasFeature('bookings'))->toBeTrue();

    $this->withToken($k)->patchJson('/api/site/services/haircut', ['price_cents' => 4200, 'duration_min' => 60])
        ->assertOk()->assertJsonPath('service.price_cents', 4200);

    $this->withToken($k)->getJson('/api/site/services')
        ->assertOk()->assertJsonPath('services.0.duration_min', 60);

    $this->withToken($k)->deleteJson('/api/site/services/haircut')->assertOk();
    expect($site->services()->count())->toBe(0);
});

test('service writes require the bookings.manage ability and stay tenant-scoped', function () {
    [$owner, $site] = bookingSite();
    $weak = bookingKey($owner, $site, ['components.manage']);
    $this->withToken($weak)->postJson('/api/site/services', ['name' => 'X'])->assertForbidden();

    // A slug belonging to another site is not reachable.
    [$otherOwner, $otherSite] = bookingSite();
    Service::create(['site_id' => $otherSite->id, 'name' => 'Foreign', 'slug' => '', 'kind' => 'slot', 'is_active' => true]);
    $k = bookingKey($owner, $site, ['bookings.manage']);
    $this->withToken($k)->patchJson('/api/site/services/foreign', ['price_cents' => 1])->assertNotFound();
});

test('booking-settings merge into the feature config and reach the public booking config', function () {
    [$owner, $site] = bookingSite();
    $k = bookingKey($owner, $site, ['bookings.manage']);

    $this->withToken($k)->patchJson('/api/site/booking-settings', [
        'days' => 'mon,wed,fri', 'open_time' => '10:00', 'close_time' => '18:00', 'slot_minutes' => 45,
    ])->assertOk()->assertJsonPath('config.days', 'mon,wed,fri');

    $this->withToken($k)->patchJson('/api/site/booking-settings', ['slot_minutes' => 30])
        ->assertOk()
        ->assertJsonPath('config.slot_minutes', 30)
        ->assertJsonPath('config.days', 'mon,wed,fri'); // earlier keys survive the merge

    $this->withToken($k)->postJson('/api/site/services', ['name' => 'Trim', 'duration_min' => 30])->assertCreated();
    $this->getJson("/api/sites/{$site->name}/booking/config")
        ->assertOk()->assertJsonFragment(['name' => 'Trim']);
});

test('the publish endpoint publishes page.json for live pages, gated by publish.manage', function () {
    [$owner, $site] = bookingSite();
    $page = Page::factory()->create(['site_id' => $site->id, 'name' => 'Home', 'url' => '/']);

    $weak = bookingKey($owner, $site, ['bookings.manage']);
    $this->withToken($weak)->postJson('/api/site/connect/publish')->assertForbidden();

    $k = bookingKey($owner, $site, ['publish.manage']);
    $this->withToken($k)->postJson('/api/site/connect/publish')
        ->assertOk()->assertJsonPath('pages', 1)->assertJsonPath('versions./', 1);

    expect($page->fresh()->page_json_version)->toBe(1);
});
