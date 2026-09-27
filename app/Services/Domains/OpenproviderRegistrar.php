<?php

namespace App\Services\Domains;

use App\Contracts\DomainRegistrar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Openprovider reseller REST API driver (Phase 1: auth + availability).
 *
 * Auth: POST /auth/login → bearer token, cached for token_ttl. Every call
 * goes through send(); a 401 clears the cached token, logs in again and
 * retries exactly once. Passwords and tokens are never logged — errors
 * carry only Openprovider's `desc` message.
 */
class OpenproviderRegistrar implements DomainRegistrar, Registrar
{
    private const TOKEN_CACHE_KEY = 'openprovider.token';

    /** @param array{url?:string,username?:string,password?:string,token_ttl?:int,timeout?:int} $config */
    public function __construct(private array $config) {}

    /** {@inheritDoc} */
    public function checkAvailability(array $domains): array
    {
        $parsed = array_map(self::normalize(...), array_values($domains));
        if ($parsed === []) {
            return [];
        }

        $data = $this->send('post', '/domains/check', [
            'domains' => array_map(fn (array $d) => ['name' => $d['name'], 'extension' => $d['extension']], $parsed),
            'with_price' => true,
        ]);

        return array_values(array_map(function (array $row) {
            // Our COST is the reseller price; fall back to the product price.
            $price = $row['price']['reseller'] ?? $row['price']['product'] ?? null;

            return [
                'domain' => (string) ($row['domain'] ?? ''),
                'available' => ($row['status'] ?? '') === 'free',
                'status' => (string) ($row['status'] ?? 'unknown'),
                'price' => isset($price['price']) ? (float) $price['price'] : null,
                'currency' => isset($price['currency']) ? (string) $price['currency'] : null,
            ];
        }, (array) ($data['results'] ?? [])));
    }

    // ─────────────────────────────────────────────────────────────
    // Registrar (purchase-flow seam): register + DNS
    // ─────────────────────────────────────────────────────────────

    /** {@inheritDoc} */
    public function available(string $label, array $tlds): array
    {
        $rows = $this->checkAvailability(array_map(fn (string $tld) => "{$label}.{$tld}", $tlds));
        $byDomain = collect($rows)->keyBy('domain');

        $out = [];
        foreach ($tlds as $tld) {
            $out["{$label}.{$tld}"] = (bool) ($byDomain["{$label}.{$tld}"]['available'] ?? false);
        }

        return $out;
    }

    /** {@inheritDoc} */
    public function register(string $domain, array $contact, int $years): string
    {
        ['name' => $name, 'extension' => $extension] = self::normalize($domain);
        $handle = $this->customerHandle($contact);

        $payload = [
            'domain' => ['name' => $name, 'extension' => $extension],
            'period' => max(1, $years),
            'unit' => 'y',
            'owner_handle' => $handle,
            'admin_handle' => $handle,
            'tech_handle' => $handle,
            'billing_handle' => $handle,
            // Openprovider's own nameservers — pointAt() then writes our records.
            'ns_group' => 'dns-openprovider',
            'autorenew' => 'off',
        ];

        // Nominet (.uk family) requires a registrant type in the additional data.
        if ($extension === 'uk' || str_ends_with($extension, '.uk')) {
            $payload['additional_data'] = [
                'registrant_type' => filled($contact['company'] ?? '') ? 'LTD' : 'IND',
            ];
        }

        $data = $this->send('post', '/domains', $payload);

        return (string) ($data['id'] ?? '');
    }

    /** {@inheritDoc} */
    public function pointAt(string $domain, string $target): void
    {
        ['name' => $name, 'extension' => $extension] = self::normalize($domain);
        $isIp = (bool) filter_var($target, FILTER_VALIDATE_IP);

        $records = [
            ['name' => '', 'type' => $isIp ? 'A' : 'CNAME', 'value' => $target, 'ttl' => 900],
            ['name' => 'www', 'type' => 'CNAME', 'value' => $isIp ? "{$name}.{$extension}." : $target, 'ttl' => 900],
        ];

        try {
            $this->send('post', '/dns/zones', [
                'domain' => ['name' => $name, 'extension' => $extension],
                'type' => 'master',
                'records' => $records,
            ]);
        } catch (RuntimeException $e) {
            // Zone already exists (registration may auto-create it) → replace records.
            $this->send('put', "/dns/zones/{$name}.{$extension}", [
                'name' => "{$name}.{$extension}",
                'records' => ['replace' => $records],
            ]);
        }
    }

    /** {@inheritDoc} */
    public function renew(string $domain, int $years): void
    {
        ['name' => $name, 'extension' => $extension] = self::normalize($domain);

        $this->send('post', '/domains/'.$this->domainId($domain).'/renew', [
            'domain' => ['name' => $name, 'extension' => $extension],
            'period' => max(1, $years),
        ]);
    }

    /** Let Openprovider auto-renew (bills OUR reseller wallet) — off by default. */
    public function setAutorenew(string $domain, bool $on): void
    {
        $this->send('put', '/domains/'.$this->domainId($domain), [
            'autorenew' => $on ? 'on' : 'off',
        ]);
    }

    /**
     * Transfer a domain IN from another registrar. Returns the provider id;
     * the transfer itself completes asynchronously at the registry.
     *
     * @param  array<string,string>  $contact  same shape as register()
     */
    public function transferIn(string $domain, string $authCode, array $contact): string
    {
        ['name' => $name, 'extension' => $extension] = self::normalize($domain);
        $handle = $this->customerHandle($contact);

        $data = $this->send('post', '/domains/transfer', [
            'domain' => ['name' => $name, 'extension' => $extension],
            'auth_code' => $authCode,
            'owner_handle' => $handle,
            'admin_handle' => $handle,
            'tech_handle' => $handle,
            'billing_handle' => $handle,
            'ns_group' => 'dns-openprovider',
            'autorenew' => 'off',
        ]);

        return (string) ($data['id'] ?? '');
    }

    /** Openprovider's numeric id for a domain we manage (cached for a day). */
    private function domainId(string $domain): int
    {
        return (int) Cache::remember('openprovider.domain-id:'.$domain, now()->addDay(), function () use ($domain) {
            $data = $this->send('get', '/domains', ['full_name' => $domain, 'limit' => 1]);
            $id = (int) ($data['results'][0]['id'] ?? 0);
            if ($id === 0) {
                throw new RuntimeException("Openprovider: domain {$domain} was not found in our account.");
            }

            return $id;
        });
    }

    /**
     * Create (or reuse, cached by email) an Openprovider customer for the
     * registrant contact and return its handle.
     *
     * @param  array<string,string>  $contact  name,email,company,address,city,zip,country,phone
     */
    private function customerHandle(array $contact): string
    {
        $email = strtolower(trim((string) ($contact['email'] ?? '')));

        return Cache::remember('openprovider.customer:'.md5($email), now()->addDays(30), function () use ($contact, $email) {
            $parts = preg_split('/\s+/', trim((string) ($contact['name'] ?? '')), 2);
            [$first, $last] = [$parts[0] ?: 'Site', $parts[1] ?? 'Owner'];

            // Address line → street + number (Openprovider wants them separate).
            $address = trim((string) ($contact['address'] ?? ''));
            $number = '1';
            if (preg_match('/^(\d+[a-z]?)\s+(.*)$/i', $address, $m)) {
                [$number, $address] = [$m[1], $m[2]];
            } elseif (preg_match('/^(.*?)\s+(\d+[a-z]?)$/i', $address, $m)) {
                [$address, $number] = [$m[1], $m[2]];
            }

            $data = $this->send('post', '/customers', array_filter([
                'company_name' => (string) ($contact['company'] ?? ''),
                'name' => ['first_name' => $first, 'last_name' => $last],
                'email' => $email,
                'phone' => self::phoneParts((string) ($contact['phone'] ?? ''), (string) ($contact['country'] ?? 'GB')),
                'address' => [
                    'street' => $address ?: 'Unknown',
                    'number' => $number,
                    'zipcode' => (string) ($contact['zip'] ?? ''),
                    'city' => (string) ($contact['city'] ?? ''),
                    'country' => strtoupper((string) ($contact['country'] ?? 'GB')),
                ],
            ], fn ($v) => $v !== ''));

            $handle = (string) ($data['handle'] ?? '');
            if ($handle === '') {
                throw new RuntimeException('Openprovider: customer created without a handle.');
            }

            return $handle;
        });
    }

    /**
     * '+44 7700 900123' → ['country_code' => '+44', 'area_code' => '7700', 'subscriber_number' => '900123'].
     *
     * @return array{country_code: string, area_code: string, subscriber_number: string}
     */
    public static function phoneParts(string $phone, string $country = 'GB'): array
    {
        $cc = $country === 'GB' ? '+44' : '+1';
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '+') && preg_match('/^\+(\d{1,3})/', $phone, $m)) {
            $cc = '+'.$m[1];
            $digits = substr($digits, strlen($m[1]));
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1); // national trunk prefix
        }

        $digits = $digits !== '' ? $digits : '7000000000';

        return [
            'country_code' => $cc,
            'area_code' => substr($digits, 0, 4),
            'subscriber_number' => substr($digits, 4) ?: '0',
        ];
    }

    /**
     * 'https://www.MySalon.co.uk/x?y' → ['name' => 'mysalon', 'extension' => 'co.uk'].
     * Split at the FIRST dot so multi-part TLDs (co.uk) stay whole.
     *
     * @return array{name: string, extension: string}
     */
    public static function normalize(string $input): array
    {
        $d = strtolower(trim($input));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#[/?].*$#', '', $d);
        $d = preg_replace('/^www\./', '', $d);

        $dot = strpos($d, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($d) - 1) {
            throw new InvalidArgumentException("Invalid domain: \"{$input}\" — expected name.tld, e.g. mysalon.co.uk");
        }

        [$name, $extension] = [substr($d, 0, $dot), substr($d, $dot + 1)];
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $name) || ! preg_match('/^[a-z0-9.\-]+$/', $extension)) {
            throw new InvalidArgumentException("Invalid domain: \"{$input}\" — expected name.tld, e.g. mysalon.co.uk");
        }

        return ['name' => $name, 'extension' => $extension];
    }

    // ─────────────────────────────────────────────────────────────

    /** Bearer token, cached; logs in when the cache is cold. */
    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, (int) ($this->config['token_ttl'] ?? 43200), fn () => $this->login());
    }

    private function login(): string
    {
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeException('Openprovider credentials are not configured (OPENPROVIDER_USER / OPENPROVIDER_PASS).');
        }

        $res = $this->http()->post($this->url('/auth/login'), [
            'username' => $username,
            'password' => $password,
            'ip' => '0.0.0.0',
        ]);
        $json = $res->json();

        if (! $res->successful() || (int) ($json['code'] ?? -1) !== 0 || blank($json['data']['token'] ?? null)) {
            throw new RuntimeException('Openprovider login failed: '.($json['desc'] ?? 'HTTP '.$res->status()));
        }

        return (string) $json['data']['token'];
    }

    /**
     * One gateway for every authenticated call. A 401 means the cached token
     * expired server-side: drop it, log in again, retry ONCE.
     */
    private function send(string $method, string $path, array $payload, bool $retried = false): array
    {
        $res = $this->http()->withToken($this->token())->{$method}($this->url($path), $payload);

        if ($res->status() === 401 && ! $retried) {
            Cache::forget(self::TOKEN_CACHE_KEY);

            return $this->send($method, $path, $payload, retried: true);
        }

        $json = $res->json();
        if (! $res->successful() || (int) ($json['code'] ?? -1) !== 0) {
            throw new RuntimeException('Openprovider: '.($json['desc'] ?? 'HTTP '.$res->status()));
        }

        return (array) ($json['data'] ?? []);
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) ($this->config['timeout'] ?? 20))->acceptJson()->asJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['url'] ?? ''), '/').$path;
    }
}
