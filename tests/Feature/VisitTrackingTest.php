<?php

use App\Livewire\AnalyticsDashboard;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\GeoLocator;
use Illuminate\Support\Str;
use Livewire\Livewire;

const CHROME_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const GOOGLEBOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

function trackSite(): Site
{
    $owner = User::factory()->create();

    return Site::create(['user_id' => $owner->id, 'name' => 'tr-'.uniqid(), 'domain' => 'example.com', 'owner' => $owner->name, 'description' => 't']);
}

/** Force geo to a fixed country so tests don't need the mmdb. */
function fakeGeo(string $code = 'US', string $country = 'United States', string $city = 'New York'): void
{
    $stub = new class($code, $country, $city) extends GeoLocator
    {
        public function __construct(private string $c, private string $n, private string $city) {}

        public function locate(?string $ip): array
        {
            return ['country_code' => $this->c, 'country' => $this->n, 'region' => 'NY', 'city' => $this->city, 'latitude' => 40.7, 'longitude' => -74.0];
        }
    };
    app()->instance(GeoLocator::class, $stub);
}

test('a beacon records a visit with parsed device, os, browser and geo', function () {
    fakeGeo();
    $site = trackSite();

    $this->withHeaders(['User-Agent' => CHROME_UA])
        ->postJson("/api/sites/{$site->name}/track", ['path' => '/about', 'referrer' => 'https://google.com/search?q=x'])
        ->assertNoContent();

    $v = Visit::where('site_id', $site->id)->first();
    expect($v)->not->toBeNull()
        ->and($v->device_type)->toBe('desktop')
        ->and($v->os)->toContain('Windows')
        ->and($v->browser)->toBe('Chrome')
        ->and($v->country_code)->toBe('US')
        ->and($v->city)->toBe('New York')
        ->and($v->source)->toBe('organic')          // google referrer
        ->and($v->is_bot)->toBeFalse();
});

test('the raw IP is never stored, but the daily hash is stable', function () {
    fakeGeo();
    $site = trackSite();

    $post = fn () => $this->withHeaders(['User-Agent' => CHROME_UA])
        ->postJson("/api/sites/{$site->name}/track", ['path' => '/']);

    $post()->assertNoContent();
    $post()->assertNoContent();

    $hashes = Visit::where('site_id', $site->id)->pluck('visitor_hash')->unique();
    expect($hashes)->toHaveCount(1)                     // same IP+day → same hash
        ->and($hashes->first())->not->toContain('127.0.0.1');
});

test('mobile UA is detected as a smartphone', function () {
    fakeGeo();
    $site = trackSite();

    $this->withHeaders(['User-Agent' => IPHONE_UA])
        ->postJson("/api/sites/{$site->name}/track", ['path' => '/'])->assertNoContent();

    expect(Visit::where('site_id', $site->id)->first()->device_type)->toBe('smartphone');
});

test('a bot is flagged', function () {
    fakeGeo();
    $site = trackSite();

    $this->withHeaders(['User-Agent' => GOOGLEBOT_UA])
        ->postJson("/api/sites/{$site->name}/track", ['path' => '/'])->assertNoContent();

    expect(Visit::where('site_id', $site->id)->first()->is_bot)->toBeTrue();
});

test('utm params classify the source as a campaign', function () {
    fakeGeo();
    $site = trackSite();

    $this->withHeaders(['User-Agent' => CHROME_UA])
        ->postJson("/api/sites/{$site->name}/track", ['path' => '/?utm_source=newsletter&utm_medium=email'])->assertNoContent();

    $v = Visit::where('site_id', $site->id)->first();
    expect($v->source)->toBe('email')->and($v->utm_source)->toBe('newsletter');
});

test('direct traffic (no referrer) is classed direct and internal referrers are ignored', function () {
    fakeGeo();
    $site = trackSite(); // domain example.com

    $this->withHeaders(['User-Agent' => CHROME_UA])
        ->postJson("/api/sites/{$site->name}/track", ['path' => '/', 'referrer' => 'https://example.com/prev'])->assertNoContent();

    $v = Visit::where('site_id', $site->id)->first();
    expect($v->source)->toBe('direct')->and($v->referrer_host)->toBeNull();
});

test('the dashboard aggregates real visits and excludes bots', function () {
    $site = trackSite();
    $mk = fn (array $a) => Visit::create(array_merge([
        'site_id' => $site->id, 'visitor_hash' => Str::random(64),
        'is_bot' => false, 'created_at' => now(),
    ], $a));

    $mk(['country_code' => 'US', 'device_type' => 'desktop', 'os' => 'Windows', 'source' => 'organic', 'referrer_host' => 'google.com']);
    $mk(['country_code' => 'US', 'device_type' => 'smartphone', 'os' => 'iOS', 'source' => 'referral', 'referrer_host' => 'news.site']);
    $mk(['country_code' => 'GB', 'device_type' => 'desktop', 'os' => 'Windows', 'source' => 'direct']);
    $mk(['country_code' => 'US', 'device_type' => 'desktop', 'os' => 'Linux', 'source' => 'organic', 'referrer_host' => 'google.com', 'is_bot' => true]); // excluded

    $owner = $site->user;
    Livewire::actingAs($owner)->test(AnalyticsDashboard::class, ['site' => $site])
        ->assertSet('range', '30d')
        ->assertViewHas('totals', fn ($t) => $t['visits'] === 3 && $t['unique_visitors'] === 3 && $t['unique_sources'] === 2)
        ->assertViewHas('charts', fn ($c) => ($c['country']['US'] ?? 0) === 2 && ($c['country']['GB'] ?? 0) === 1)
        ->assertViewHas('referrers', fn ($r) => ($r['google.com'] ?? 0) === 1); // bot's google hit excluded
});

test('changing range dispatches fresh chart data', function () {
    $site = trackSite();
    Visit::create(['site_id' => $site->id, 'visitor_hash' => 'h', 'country_code' => 'US', 'device_type' => 'desktop', 'os' => 'Windows', 'source' => 'direct', 'created_at' => now()]);

    Livewire::actingAs($site->user)->test(AnalyticsDashboard::class, ['site' => $site])
        ->call('setRange', '7d')
        ->assertSet('range', '7d')
        ->assertDispatched('analytics-updated');
});
