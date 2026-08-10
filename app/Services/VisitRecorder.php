<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Visit;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Turns an incoming tracking beacon into a persisted Visit: parses the user
 * agent (device/OS/browser/bot), geolocates the IP, derives the traffic source,
 * and stores a privacy-safe daily hash of the IP instead of the raw address.
 */
class VisitRecorder
{
    public function __construct(private GeoLocator $geo) {}

    public function record(Site $site, Request $request, array $payload): ?Visit
    {
        $ip = $request->ip();
        $ua = (string) $request->userAgent();

        // ── Device / client ────────────────────────────────────────
        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_MINOR);
        $dd = new DeviceDetector($ua);
        $dd->parse();
        $isBot = $dd->isBot();
        $client = $isBot ? [] : ($dd->getClient() ?: []);
        $os = $isBot ? [] : ($dd->getOs() ?: []);

        // ── Landing path + referrer + UTM ─────────────────────────
        $path = $this->cleanPath($payload['path'] ?? $payload['url'] ?? null);
        $utm = $this->utmFrom($payload['path'] ?? $payload['url'] ?? '');
        $referrer = $payload['referrer'] ?? null;
        $referrerHost = $referrer ? Str::lower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $internal = $referrerHost && $this->isOwnHost($site, $referrerHost);

        // ── Geo (blank when no mmdb / unknown IP) ─────────────────
        $geo = $this->geo->locate($ip);

        return Visit::create([
            'site_id' => $site->id,
            'visitor_hash' => hash('sha256', $ip.'|'.now()->toDateString().'|'.$site->id.'|'.config('app.key')),
            'session_id' => isset($payload['session_id']) ? Str::limit((string) $payload['session_id'], 64, '') : null,
            'path' => $path,
            'referrer' => $internal ? null : $referrer,
            'referrer_host' => $internal ? null : $referrerHost,
            'source' => $this->deriveSource($utm, $internal ? null : $referrerHost),
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
            'country_code' => $geo['country_code'],
            'country' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
            'device_type' => $isBot ? 'bot' : ($dd->getDeviceName() ?: null),
            'os' => $os['name'] ?? null,
            'os_version' => $os['version'] ?? null,
            'browser' => $client['name'] ?? null,
            'browser_version' => $client['version'] ?? null,
            'device_brand' => $isBot ? null : ($dd->getBrandName() ?: null),
            'language' => $this->language($payload, $request),
            'is_bot' => $isBot,
            'created_at' => now(),
        ]);
    }

    /** Path without query string, capped. */
    private function cleanPath(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $path = parse_url($raw, PHP_URL_PATH) ?: $raw;

        return Str::limit($path, 2048, '');
    }

    /** @return array{source?:string,medium?:string,campaign?:string} */
    private function utmFrom(string $url): array
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return [];
        }
        parse_str($query, $q);

        return array_filter([
            'source' => $q['utm_source'] ?? null,
            'medium' => $q['utm_medium'] ?? null,
            'campaign' => $q['utm_campaign'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function deriveSource(array $utm, ?string $referrerHost): string
    {
        if (! empty($utm['medium']) || ! empty($utm['source'])) {
            $medium = Str::lower($utm['medium'] ?? '');

            return $medium === 'email' ? 'email' : 'campaign';
        }
        if (! $referrerHost) {
            return 'direct';
        }
        foreach ((array) config('analytics.search_hosts') as $h) {
            if (str_contains($referrerHost, $h)) {
                return 'organic';
            }
        }
        foreach ((array) config('analytics.social_hosts') as $h) {
            if (str_contains($referrerHost, $h)) {
                return 'social';
            }
        }

        return 'referral';
    }

    private function isOwnHost(Site $site, string $host): bool
    {
        $own = Str::lower((string) $site->domain);
        $bare = fn ($h) => Str::startsWith($h, 'www.') ? substr($h, 4) : $h;

        return $own !== '' && $bare($host) === $bare($own);
    }

    private function language(array $payload, Request $request): ?string
    {
        $lang = $payload['language'] ?? Str::before((string) $request->header('Accept-Language'), ',');

        return $lang ? Str::limit(trim($lang), 12, '') : null;
    }
}
