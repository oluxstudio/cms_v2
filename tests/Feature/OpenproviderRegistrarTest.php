<?php

use App\Services\Domains\OpenproviderRegistrar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function openprovider(array $overrides = []): OpenproviderRegistrar
{
    return new OpenproviderRegistrar($overrides + [
        'url' => 'https://api.sandbox.openprovider.nl/v1',
        'username' => 'reseller@example.com',
        'password' => 'secret-pass',
        'token_ttl' => 3600,
        'timeout' => 5,
    ]);
}

function opLogin(string $token = 'tok-1'): array
{
    return ['code' => 0, 'desc' => '', 'data' => ['token' => $token, 'reseller_id' => 42]];
}

function opResults(array $results): array
{
    return ['code' => 0, 'desc' => '', 'data' => ['results' => $results]];
}

beforeEach(fn () => Cache::forget('openprovider.token'));

test('login succeeds and the token is cached across calls', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains/check' => Http::response(opResults([['domain' => 'mysalon.com', 'status' => 'free']])),
    ]);

    $reg = openprovider();
    $reg->checkAvailability(['mysalon.com']);
    $reg->checkAvailability(['mysalon.com']);

    Http::assertSentCount(3); // ONE login + two checks
    expect(Cache::get('openprovider.token'))->toBe('tok-1');
});

test('login failure throws with the provider message', function () {
    Http::fake(['*/auth/login' => Http::response(['code' => 196, 'desc' => 'Authentication failed'])]);

    expect(fn () => openprovider()->checkAvailability(['mysalon.com']))
        ->toThrow(RuntimeException::class, 'Authentication failed');
});

test('missing credentials throw a clear configuration error', function () {
    Http::fake();

    expect(fn () => openprovider(['username' => null, 'password' => null])->checkAvailability(['mysalon.com']))
        ->toThrow(RuntimeException::class, 'OPENPROVIDER_USER');
    Http::assertNothingSent();
});

test('availability results are mapped: free and taken, reseller price with product fallback', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains/check' => Http::response(opResults([
            ['domain' => 'a.com', 'status' => 'free', 'price' => ['reseller' => ['price' => 7.45, 'currency' => 'USD'], 'product' => ['price' => 9.99, 'currency' => 'USD']]],
            ['domain' => 'b.com', 'status' => 'active', 'price' => ['product' => ['price' => 11.5, 'currency' => 'EUR']]],
            ['domain' => 'c.com', 'status' => 'reserved'],
        ])),
    ]);

    $rows = openprovider()->checkAvailability(['a.com', 'b.com', 'c.com']);

    expect($rows)->toBe([
        ['domain' => 'a.com', 'available' => true, 'status' => 'free', 'price' => 7.45, 'currency' => 'USD'],
        ['domain' => 'b.com', 'available' => false, 'status' => 'active', 'price' => 11.5, 'currency' => 'EUR'],
        ['domain' => 'c.com', 'available' => false, 'status' => 'reserved', 'price' => null, 'currency' => null],
    ]);
});

test('a 401 clears the token, re-logs in and retries exactly once', function () {
    Cache::put('openprovider.token', 'stale-token', 3600);
    Http::fake([
        '*/auth/login' => Http::response(opLogin('tok-fresh')),
        '*/domains/check' => Http::sequence()
            ->push(['code' => 196, 'desc' => 'expired'], 401)
            ->push(opResults([['domain' => 'mysalon.com', 'status' => 'free']])),
    ]);

    $rows = openprovider()->checkAvailability(['mysalon.com']);

    expect($rows[0]['available'])->toBeTrue()
        ->and(Cache::get('openprovider.token'))->toBe('tok-fresh');
    Http::assertSentCount(3); // failed check + one login + retried check
    Http::assertSent(fn ($req) => str_contains($req->url(), 'auth/login')); // exactly one, counted above
});

test('a persistent 401 gives up after one retry', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains/check' => Http::response(['code' => 196, 'desc' => 'still unauthorised'], 401),
    ]);

    expect(fn () => openprovider()->checkAvailability(['mysalon.com']))
        ->toThrow(RuntimeException::class, 'still unauthorised');
    Http::assertSentCount(4); // login + check(401) + re-login + check(401), then throw
});

test('domain input is normalised before hitting the API', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains/check' => Http::response(opResults([['domain' => 'mysalon.co.uk', 'status' => 'free']])),
    ]);

    openprovider()->checkAvailability([' https://www.MySalon.co.uk/contact?x=1 ']);

    Http::assertSent(fn ($req) => str_contains($req->url(), 'domains/check')
        && $req['domains'] === [['name' => 'mysalon', 'extension' => 'co.uk']]
        && $req['with_price'] === true);
});

test('normalize splits at the first dot', function () {
    expect(OpenproviderRegistrar::normalize('MySalon.co.uk'))
        ->toBe(['name' => 'mysalon', 'extension' => 'co.uk']);
});

test('invalid domain input is rejected', function (string $bad) {
    Http::fake();

    expect(fn () => openprovider()->checkAvailability([$bad]))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
})->with(['no-dot', '', '.com', 'trailing.', 'bad_chars!.com', '-lead.com']);

// ─── Phase 2: registration + DNS ─────────────────────────────────

function opContact(): array
{
    return [
        'name' => 'Grace Way', 'email' => 'grace@example.com', 'company' => '',
        'address' => '10 High Street', 'city' => 'Blackburn', 'zip' => 'BB1 1AA',
        'country' => 'GB', 'phone' => '+44 7700 900123',
    ];
}

test('register creates a customer then the domain, returning the provider id', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/customers' => Http::response(['code' => 0, 'data' => ['handle' => 'GW123-GB']]),
        '*/domains' => Http::response(['code' => 0, 'data' => ['id' => 998877]]),
    ]);

    $ref = openprovider()->register('mysalon.com', opContact(), 2);

    expect($ref)->toBe('998877');
    Http::assertSent(fn ($req) => str_contains($req->url(), '/customers')
        && $req['name'] === ['first_name' => 'Grace', 'last_name' => 'Way']
        && $req['address']['street'] === 'High Street'
        && $req['address']['number'] === '10'
        && $req['phone']['country_code'] === '+44');
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/domains')
        && $req['domain'] === ['name' => 'mysalon', 'extension' => 'com']
        && $req['period'] === 2
        && $req['owner_handle'] === 'GW123-GB'
        && ! isset($req['additional_data']));
});

test('uk registrations carry the Nominet registrant type', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/customers' => Http::response(['code' => 0, 'data' => ['handle' => 'GW123-GB']]),
        '*/domains' => Http::response(['code' => 0, 'data' => ['id' => 5]]),
    ]);

    openprovider()->register('mysalon.co.uk', opContact(), 1);

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/domains')
        && ($req['additional_data']['registrant_type'] ?? null) === 'IND');
});

test('the customer handle is cached by email across registrations', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/customers' => Http::response(['code' => 0, 'data' => ['handle' => 'GW123-GB']]),
        '*/domains' => Http::response(['code' => 0, 'data' => ['id' => 1]]),
    ]);
    Cache::forget('openprovider.customer:'.md5('grace@example.com'));

    $reg = openprovider();
    $reg->register('a.com', opContact(), 1);
    $reg->register('b.com', opContact(), 1);

    Http::assertSentCount(4); // login + ONE customer + two domain registrations
});

test('pointAt creates a DNS zone with apex A + www CNAME for an IP target', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/dns/zones' => Http::response(['code' => 0, 'data' => []]),
    ]);

    openprovider()->pointAt('mysalon.com', '203.0.113.10');

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/dns/zones')
        && $req['records'][0] === ['name' => '', 'type' => 'A', 'value' => '203.0.113.10', 'ttl' => 900]
        && $req['records'][1]['type'] === 'CNAME' && $req['records'][1]['name'] === 'www');
});

test('pointAt on an existing zone swaps only the web records — email records survive', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/dns/zones/mysalon.com/records*' => Http::response(['code' => 0, 'data' => ['results' => [
            ['name' => 'mysalon.com', 'type' => 'A', 'value' => '198.51.100.1', 'ttl' => 900],
            ['name' => 'www.mysalon.com', 'type' => 'CNAME', 'value' => 'old.host', 'ttl' => 900],
            ['name' => 'mysalon.com', 'type' => 'MX', 'value' => 'mx1.mail.test', 'prio' => 10, 'ttl' => 900],
            ['name' => 'mysalon.com', 'type' => 'TXT', 'value' => 'v=spf1 include:_spf.mail.test ~all', 'ttl' => 900],
        ]]]),
        '*/dns/zones/mysalon.com' => Http::response(['code' => 0, 'data' => []]),
        '*/dns/zones' => Http::response(['code' => 348, 'desc' => 'Zone already exists'], 400),
    ]);

    openprovider()->pointAt('mysalon.com', 'edge.olux.host');

    Http::assertSent(function ($req) {
        if ($req->method() !== 'PUT' || ! str_ends_with($req->url(), '/dns/zones/mysalon.com')) {
            return false;
        }
        $removed = collect($req['records']['remove'] ?? []);

        return ! isset($req['records']['replace'])
            && $removed->pluck('type')->sort()->values()->all() === ['A', 'CNAME']
            && ! $removed->contains(fn ($r) => in_array($r['type'], ['MX', 'TXT'], true))
            && collect($req['records']['add'])->pluck('name')->all() === ['', 'www'];
    });
});

test('available() maps the purchase-seam bool shape', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains/check' => Http::response(opResults([
            ['domain' => 'mysalon.co.uk', 'status' => 'free'],
            ['domain' => 'mysalon.com', 'status' => 'active'],
        ])),
    ]);

    expect(openprovider()->available('mysalon', ['co.uk', 'com']))
        ->toBe(['mysalon.co.uk' => true, 'mysalon.com' => false]);
});

test('phone numbers are split into Openprovider parts', function () {
    expect(OpenproviderRegistrar::phoneParts('+44 7700 900123'))
        ->toBe(['country_code' => '+44', 'area_code' => '7700', 'subscriber_number' => '900123'])
        ->and(OpenproviderRegistrar::phoneParts('07700900123'))
        ->toBe(['country_code' => '+44', 'area_code' => '7700', 'subscriber_number' => '900123'])
        ->and(OpenproviderRegistrar::phoneParts(''))
        ->toBe(['country_code' => '+44', 'area_code' => '7000', 'subscriber_number' => '000000']);
});

// ─── Phase 3: renew / autorenew / transfer ───────────────────────

test('renew resolves the domain id then posts the renewal', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains?full_name=mysalon.com*' => Http::response(['code' => 0, 'data' => ['results' => [['id' => 777]]]]),
        '*/domains/777/renew' => Http::response(['code' => 0, 'data' => []]),
    ]);
    Cache::forget('openprovider.domain-id:mysalon.com');

    openprovider()->renew('mysalon.com', 1);

    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/domains/777/renew')
        && $req['period'] === 1
        && $req['domain'] === ['name' => 'mysalon', 'extension' => 'com']);
});

test('renew throws when the domain is not in our account', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains?full_name=ghost.com*' => Http::response(['code' => 0, 'data' => ['results' => []]]),
    ]);
    Cache::forget('openprovider.domain-id:ghost.com');

    expect(fn () => openprovider()->renew('ghost.com', 1))
        ->toThrow(RuntimeException::class, 'not found in our account');
});

test('setAutorenew updates the domain', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/domains?full_name=mysalon.com*' => Http::response(['code' => 0, 'data' => ['results' => [['id' => 777]]]]),
        '*/domains/777' => Http::response(['code' => 0, 'data' => []]),
    ]);
    Cache::forget('openprovider.domain-id:mysalon.com');

    openprovider()->setAutorenew('mysalon.com', true);

    Http::assertSent(fn ($req) => $req->method() === 'PUT'
        && str_ends_with($req->url(), '/domains/777') && $req['autorenew'] === 'on');
});

test('transferIn creates the customer and starts the transfer', function () {
    Http::fake([
        '*/auth/login' => Http::response(opLogin()),
        '*/customers' => Http::response(['code' => 0, 'data' => ['handle' => 'GW123-GB']]),
        '*/domains/transfer' => Http::response(['code' => 0, 'data' => ['id' => 4242]]),
    ]);
    Cache::forget('openprovider.customer:'.md5('grace@example.com'));

    $ref = openprovider()->transferIn('mysalon.com', 'AUTH-CODE-1', opContact());

    expect($ref)->toBe('4242');
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/domains/transfer')
        && $req['auth_code'] === 'AUTH-CODE-1' && $req['owner_handle'] === 'GW123-GB');
});
