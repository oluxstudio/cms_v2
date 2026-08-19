<?php

use App\Services\SiteConnect\SsrfGuard;

function guardResolving(array $ips): SsrfGuard
{
    return new class($ips) extends SsrfGuard
    {
        public function __construct(private array $ips) {}

        protected function resolve(string $host): array
        {
            return $this->ips;
        }
    };
}

test('a host that resolves to nothing is rejected', function () {
    expect(guardResolving([])->allows('https://ghost.example/', ['ghost.example']))->toBeFalse();
});

test('private and reserved ranges are rejected, public passes', function () {
    expect(guardResolving(['10.0.0.5'])->allows('https://a.example/', ['a.example']))->toBeFalse()
        ->and(guardResolving(['127.0.0.1'])->allows('https://a.example/', ['a.example']))->toBeFalse()
        ->and(guardResolving(['192.168.1.9'])->allows('https://a.example/', ['a.example']))->toBeFalse()
        ->and(guardResolving(['93.184.216.34'])->allows('https://a.example/', ['a.example']))->toBeTrue();
});

test('hosts outside the allow-list are rejected regardless of DNS', function () {
    expect(guardResolving(['93.184.216.34'])->allows('https://evil.example/', ['good.example']))->toBeFalse();
});

test('non-http schemes are rejected', function () {
    expect(guardResolving(['93.184.216.34'])->allows('file:///etc/passwd', ['etc']))->toBeFalse()
        ->and(guardResolving(['93.184.216.34'])->allows('gopher://a.example/', ['a.example']))->toBeFalse();
});

test('pinnedOptions locks the fetch to the vetted IP', function () {
    $opts = guardResolving(['93.184.216.34'])->pinnedOptions('https://a.example/img.png');
    expect($opts['curl'][CURLOPT_RESOLVE])->toBe(['a.example:443:93.184.216.34']);

    // Literal-IP hosts and unresolvable hosts pin nothing.
    expect(guardResolving([])->pinnedOptions('https://a.example/'))->toBe([])
        ->and(guardResolving(['1.2.3.4'])->pinnedOptions('https://93.184.216.34/'))->toBe([]);
});

test('the private-host allowance opens local ranges without loosening the host allow-list', function () {
    config(['site_connect.allow_private_hosts' => true]);
    expect(guardResolving(['127.0.0.1'])->allows('http://localhost:3003/x', ['localhost']))->toBeTrue()
        ->and(guardResolving(['127.0.0.1'])->allows('http://evil.example/x', ['localhost']))->toBeFalse();
    config(['site_connect.allow_private_hosts' => false]);
    expect(guardResolving(['127.0.0.1'])->allows('http://localhost:3003/x', ['localhost']))->toBeFalse();
});
