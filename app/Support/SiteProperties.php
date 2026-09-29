<?php

namespace App\Support;

use App\Models\Site;

/**
 * Site properties — the business identity every template can read: display
 * name, logo, main email, any number of labelled phone numbers and email
 * addresses, plus admin-named custom variables (key · type · value).
 *
 * Stored as site attributes under `site.*` (lists as JSON), so they also
 * appear in the content API's raw `attributes`; `payload()` is the tidy,
 * decoded shape served as `site.properties`.
 */
class SiteProperties
{
    public const NAME = 'site.display_name';

    public const LOGO = 'site.logo';

    public const EMAIL = 'site.email';

    public const PHONES = 'site.phones';

    public const EMAILS = 'site.emails';

    public const VARIABLES = 'site.variables';

    /** Custom variable value types, as offered in the type dropdown. */
    public const TYPES = ['text' => 'Text', 'image' => 'Image'];

    /** Variable keys: lowercase, start with a letter, letters/digits/underscores. */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const MAX_ROWS = 50;

    /** @return array{name:string,logo:string,email:string,phones:list<array{label:string,value:string}>,emails:list<array{label:string,value:string}>,variables:list<array{key:string,type:string,value:string}>} */
    public static function get(Site $site): array
    {
        $attrs = $site->attrMap();
        $list = fn (string $key) => array_values(array_filter(
            json_decode((string) ($attrs[$key] ?? ''), true) ?: [],
            'is_array',
        ));

        return [
            'name' => (string) ($attrs[self::NAME] ?? ''),
            'logo' => (string) ($attrs[self::LOGO] ?? ''),
            'email' => (string) ($attrs[self::EMAIL] ?? ''),
            'phones' => array_map(fn ($r) => ['label' => (string) ($r['label'] ?? ''), 'value' => (string) ($r['value'] ?? '')], $list(self::PHONES)),
            'emails' => array_map(fn ($r) => ['label' => (string) ($r['label'] ?? ''), 'value' => (string) ($r['value'] ?? '')], $list(self::EMAILS)),
            'variables' => array_map(fn ($r) => [
                'key' => (string) ($r['key'] ?? ''),
                'type' => isset(self::TYPES[$r['type'] ?? '']) ? $r['type'] : 'text',
                'value' => (string) ($r['value'] ?? ''),
            ], $list(self::VARIABLES)),
        ];
    }

    /** Persist a validated set; blank scalars and empty lists are removed. */
    public static function save(Site $site, array $props): void
    {
        $scalar = fn (string $key, string $value) => $value === '' ? $site->forgetAttr($key) : $site->setAttr($key, $value);
        $list = fn (string $key, array $rows) => $rows === [] ? $site->forgetAttr($key) : $site->setAttr($key, json_encode(array_values($rows), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $scalar(self::NAME, trim($props['name'] ?? ''));
        $scalar(self::LOGO, trim($props['logo'] ?? ''));
        $scalar(self::EMAIL, trim($props['email'] ?? ''));
        $list(self::PHONES, $props['phones'] ?? []);
        $list(self::EMAILS, $props['emails'] ?? []);
        $list(self::VARIABLES, $props['variables'] ?? []);
    }

    /** The API shape: variables keyed by name for direct lookup in templates. */
    public static function payload(Site $site): array
    {
        $p = self::get($site);
        $abs = fn (string $url) => $url !== '' && str_starts_with($url, '/') ? url($url) : $url;

        return [
            'name' => $p['name'] !== '' ? $p['name'] : $site->name,
            'logo' => $abs($p['logo']) ?: null,
            'email' => $p['email'] ?: null,
            'phones' => $p['phones'],
            'emails' => $p['emails'],
            'variables' => collect($p['variables'])->mapWithKeys(fn ($v) => [
                $v['key'] => $v['type'] === 'image' ? $abs($v['value']) : $v['value'],
            ])->all(),
            'variable_types' => collect($p['variables'])->pluck('type', 'key')->all(),
        ];
    }
}
