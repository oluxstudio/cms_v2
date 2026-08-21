<?php

use App\Models\ApiToken;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns (minting if needed) the site connect token for a management key', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $mgmt = connectToken($site, ['publish.manage']);

    $first = $this->getJson('/api/site/connect-token', ['Authorization' => 'Bearer '.$mgmt])
        ->assertOk()->json();

    expect($first['site'])->toBe($site->name)
        ->and($first['token'])->toStartWith('olx_live_');

    // Second call returns the SAME token — no mint pile-up.
    $second = $this->getJson('/api/site/connect-token', ['Authorization' => 'Bearer '.$mgmt])->json();
    expect($second['token'])->toBe($first['token']);
    expect(ApiToken::where('site_id', $site->id)->whereNotNull('plain')->count())->toBe(1);

    // And the returned token really authenticates as the connect key.
    $this->getJson('/api/v1/connect/status', ['Authorization' => 'Bearer '.$first['token']])
        ->assertOk();
});

it('refuses to hand the connect token to a connect key or an unauthorized key', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);

    // The public connect key itself lacks publish.manage.
    $connect = connectToken($site);
    $this->getJson('/api/site/connect-token', ['Authorization' => 'Bearer '.$connect])
        ->assertForbidden();

    // A site-scoped key cannot be steered to another site: X-Olux-Site is
    // ignored for scoped keys, so it only ever gets its OWN site's token.
    $other = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $otherKey = connectToken($other, ['publish.manage']);
    $this->getJson('/api/site/connect-token', [
        'Authorization' => 'Bearer '.$otherKey,
        'X-Olux-Site' => $site->name,
    ])->assertOk()->assertJsonPath('site', $other->name);
});

it('never exposes management keys through the endpoint', function () {
    $site = Site::factory()->create(['user_id' => User::factory()->create()->id]);
    $mgmt = connectToken($site, ['publish.manage']);

    $token = $this->getJson('/api/site/connect-token', ['Authorization' => 'Bearer '.$mgmt])->json('token');

    // The returned raw is a retrievable connect key, not any hash-only key.
    $row = ApiToken::where('token', hash('sha256', $token))->first();
    expect($row->plain)->not->toBeNull()
        ->and($row->abilities)->toBe(config('site_connect.abilities', ['connect:ingest', 'content:read']));
});
