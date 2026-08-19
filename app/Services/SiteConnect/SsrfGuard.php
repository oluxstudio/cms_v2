<?php

namespace App\Services\SiteConnect;

/**
 * SSRF guard for the server-side crawler. A crawl URL is only fetched when it
 * (a) is http(s), (b) shares the host of the site's verified domain, and (c)
 * does not resolve to a private/reserved IP range. Prevents the crawler from
 * being pointed at internal services via a poisoned link.
 */
class SsrfGuard
{
    /**
     * @param  string  $url  candidate URL to fetch
     * @param  array<int,string>  $allowedHosts  hosts the site is allowed to crawl
     */
    public function allows(string $url, array $allowedHosts): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '') {
            return false;
        }

        // Host must be in the allow-list (exact or subdomain of an allowed host).
        $hostAllowed = false;
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                $hostAllowed = true;
                break;
            }
        }
        if (! $hostAllowed) {
            return false;
        }

        // Local dev: the client site runs on localhost, so private ranges must
        // be fetchable. Production stays strict unless explicitly overridden.
        if (config('site_connect.allow_private_hosts', config('app.env') === 'local')) {
            return true;
        }

        // Resolve + reject private/reserved ranges (defeats DNS-rebinding to
        // internal IPs). A host that resolves to NOTHING is also rejected —
        // "unresolvable" must not read as "safe".
        $ips = $this->resolve($host);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Guzzle options that pin the fetch to the IP vetted by allows(), closing
     * the check-then-fetch DNS-rebinding window (the guard and the HTTP client
     * would otherwise resolve DNS independently). Empty when nothing to pin
     * (e.g. the host is a literal IP).
     *
     * @return array<string,mixed>
     */
    public function pinnedOptions(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }
        $ips = $this->resolve($host);
        $ipv4 = array_values(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
        if ($ipv4 === []) {
            return [];
        }
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'https') === 'https' ? 443 : 80);

        return ['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ipv4[0]}"]]];
    }

    /** @return array<int,string> (protected so tests can stub DNS) */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null,
            $records
        )));
    }
}
