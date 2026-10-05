<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Reusable values: every Site Property (and every custom variable from the
 * Variables tab) has a token — {{phone}}, {{logo}}, {{hours}}, {{opening_offer}}
 * — that can be typed into ANY content (a block field, a collection entry, a
 * post). Wherever the site's content is delivered (content API, collections
 * API, static page.json) the token is replaced with the current value, so one
 * change on the Properties page updates every place that uses it. Editors
 * keep showing the raw token. Unknown tokens are left untouched.
 */
class SiteTokens
{
    /** token => [label, property key | callable] for the built-ins (order = display order). */
    private const BUILT_IN = [
        'site_name' => 'Site name', 'short_name' => 'Short name', 'tagline' => 'Tagline',
        'logo' => 'Logo (image URL)', 'logo_light' => 'Light logo (image URL)', 'favicon' => 'Favicon (image URL)', 'share_image' => 'Share image (URL)',
        'email' => 'Email', 'phone' => 'Main phone', 'whatsapp' => 'WhatsApp',
        'address' => 'Full address', 'address_street' => 'Street', 'address_town' => 'Town', 'address_postcode' => 'Postcode', 'address_country' => 'Country',
        'hours' => 'Opening hours (summary)',
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
        'legal_name' => 'Legal name', 'year' => 'Current year',
    ];

    private const DAYS = ['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'];

    /** @var array<string, array<string,string>> per-request: site id → token map */
    private static array $memo = [];

    /**
     * Every token with its label and current value (built-ins first, then custom variables).
     *
     * @return list<array{token: string, label: string, value: string, custom: bool}>
     */
    public static function all(Site $site): array
    {
        ['values' => $v, 'rows' => $rows, 'variables' => $vars] = SiteProperties::get($site);
        $out = [];
        foreach (self::BUILT_IN as $token => $label) {
            $out[] = ['token' => $token, 'label' => $label, 'value' => self::builtIn($site, $token, $v, $rows), 'custom' => false];
        }
        foreach ($vars as $var) {
            $key = self::key((string) ($var['key'] ?? ''));
            if ($key === '' || isset(self::BUILT_IN[$key])) {
                continue;
            }
            $value = (string) ($var['value'] ?? '');
            if (($var['type'] ?? 'text') === 'image') {
                $value = SiteProperties::imageUrl($site, $value);
            }
            $out[] = ['token' => $key, 'label' => (string) $var['key'], 'value' => $value, 'custom' => true];
        }

        return $out;
    }

    /** token => value, cached per request (bust with forget()). @return array<string,string> */
    public static function map(Site $site): array
    {
        return self::$memo[$site->id] ??= collect(self::all($site))->mapWithKeys(fn ($t) => [$t['token'] => $t['value']])->all();
    }

    public static function forget(?string $siteId = null): void
    {
        if ($siteId === null) {
            self::$memo = [];
        } else {
            unset(self::$memo[$siteId]);
        }
    }

    /** A variable name → its token key: "Opening Offer" → opening_offer. */
    public static function key(string $name): string
    {
        return Str::snake(Str::ascii(trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $name))));
    }

    /** Replace {{tokens}} in every string inside $data (arrays walked recursively). */
    public static function apply(Site $site, mixed $data): mixed
    {
        if (is_string($data)) {
            if (! str_contains($data, '{{')) {
                return $data;
            }
            $map = self::map($site);

            return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', fn ($m) => array_key_exists($k = strtolower($m[1]), $map) ? $map[$k] : $m[0], $data);
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::apply($site, $v);
            }
        }

        return $data;
    }

    /** "Mon–Fri 09:00–17:00 · Sat 10:00–14:00 · Sun Closed" from the Hours tab ('' when nothing is filled in). */
    public static function hoursSummary(array $values): string
    {
        $groups = [];
        foreach (self::DAYS as $day => $short) {
            $h = trim((string) ($values["hours_{$day}"] ?? ''));
            $h = $h === '' ? '' : str_replace('-', '–', $h);
            $last = end($groups);
            if ($last && $last['h'] === $h) {
                $groups[key($groups)]['to'] = $short;
            } else {
                $groups[] = ['from' => $short, 'to' => $short, 'h' => $h];
            }
        }

        return collect($groups)->filter(fn ($g) => $g['h'] !== '')
            ->map(fn ($g) => ($g['from'] === $g['to'] ? $g['from'] : $g['from'].'–'.$g['to']).' '.$g['h'])
            ->implode(' · ');
    }

    private static function builtIn(Site $site, string $token, array $v, array $rows): string
    {
        return match ($token) {
            'phone' => (string) ($rows['phones'][0]['value'] ?? ''),
            'address' => collect(['address_street', 'address_town', 'address_county', 'address_postcode', 'address_country'])
                ->map(fn ($k) => trim((string) ($v[$k] ?? '')))->filter()->implode(', '),
            'hours' => self::hoursSummary($v),
            'logo', 'logo_light', 'share_image' => SiteProperties::imageUrl($site, (string) ($v[$token] ?? '')),
            'favicon' => (string) ((json_decode((string) $site->getAttr(config('site-properties.icons_attr')), true) ?: [])['32']
                ?? SiteProperties::imageUrl($site, (string) ($v['square_icon'] ?? ''))),
            'year' => (string) now()->year,
            default => (string) ($v[$token] ?? ''),
        };
    }
}
