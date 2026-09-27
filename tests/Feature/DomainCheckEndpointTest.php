<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::forget('openprovider.token');
    config(['openprovider' => [
        'url' => 'https://api.sandbox.openprovider.nl/v1',
        'username' => 'reseller@example.com',
        'password' => 'secret-pass',
        'token_ttl' => 3600,
        'timeout' => 5,
    ]]);
});

function fakeOpenprovider(array $results): void
{
    Http::fake([
        '*/auth/login' => Http::response(['code' => 0, 'data' => ['token' => 'tok']]),
        '*/domains/check' => Http::response(['code' => 0, 'data' => ['results' => $results]]),
    ]);
}

test('the domain check endpoint requires auth', function () {
    Http::fake();

    $this->getJson('/api/domains/check?domain=mysalon')->assertUnauthorized();
    Http::assertNothingSent();
});

test('input is validated', function () {
    Http::fake();
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/domains/check')->assertStatus(422);
    $this->getJson('/api/domains/check?domain=a')->assertStatus(422);
    $this->getJson('/api/domains/check?domain=bad%21chars')->assertStatus(422);
    Http::assertNothingSent();
});

test('a bare label fans out to the default extensions and hides cost prices', function () {
    fakeOpenprovider([
        ['domain' => 'mysalon.co.uk', 'status' => 'free', 'price' => ['reseller' => ['price' => 4.2, 'currency' => 'USD']]],
        ['domain' => 'mysalon.uk', 'status' => 'free'],
        ['domain' => 'mysalon.com', 'status' => 'active'],
    ]);
    $this->actingAs(User::factory()->create());

    $res = $this->getJson('/api/domains/check?domain=MySalon')->assertOk();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'domains/check')
        && $req['domains'] === [
            ['name' => 'mysalon', 'extension' => 'co.uk'],
            ['name' => 'mysalon', 'extension' => 'uk'],
            ['name' => 'mysalon', 'extension' => 'com'],
        ]);

    $res->assertExactJson(['results' => [
        ['domain' => 'mysalon.co.uk', 'available' => true, 'status' => 'free', 'price_cents' => config('domains.tlds.co\.uk.price_cents') ?? config('domains.tlds')['co.uk']['price_cents']],
        ['domain' => 'mysalon.uk', 'available' => true, 'status' => 'free', 'price_cents' => config('domains.tlds')['uk']['price_cents']],
        ['domain' => 'mysalon.com', 'available' => false, 'status' => 'active', 'price_cents' => config('domains.tlds')['com']['price_cents']],
    ]]);
    // retail price only — the reseller COST (4.20 USD in the fake) must never leak
    expect(json_encode($res->json()))->not->toContain('4.2')->not->toContain('USD');
});

test('a full domain is checked as-is', function () {
    fakeOpenprovider([['domain' => 'mysalon.co.uk', 'status' => 'free']]);
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/domains/check?domain=mysalon.co.uk')->assertOk()
        ->assertJsonPath('results.0.domain', 'mysalon.co.uk')
        ->assertJsonPath('results.0.available', true);

    Http::assertSent(fn ($req) => str_contains($req->url(), 'domains/check')
        && $req['domains'] === [['name' => 'mysalon', 'extension' => 'co.uk']]);
});
