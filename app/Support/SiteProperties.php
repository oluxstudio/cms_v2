<?php

namespace App\Support;

use App\Models\Component;
use App\Models\Media;
use App\Models\Node;
use App\Models\Page;
use App\Models\Site;
use App\Services\ContentVersioner;
use App\Services\SiteConnect\PageJsonPublisher;
use App\Services\SiteIcons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Site properties — the business profile every template can read (brand,
 * business details, hours, contact & social, legal, SEO, locale & status,
 * assistant, custom variables).
 *
 * Stored as the NODES of the site's page-less "Site Properties" component,
 * matched by label (schema: config/site-properties.php), so the Properties
 * page and the Edit page edit the same data. Repeating rows use the
 * "{Prefix} {n} {Field}" label convention; any other node is a custom
 * variable. `payload()` is the tidy shape served as `site.properties`.
 */
class SiteProperties
{
    /** Custom variable value types, as offered in the type dropdown. */
    public const TYPES = ['text' => 'Text', 'image' => 'Image'];

    /** Variable names: start with a letter; letters, digits, spaces, _ or -. */
    public const KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9 _\-]{0,59}$/';

    public const MAX_ROWS = 50;

    /** A day's hours: "Closed", or one or more "HH:MM-HH:MM" ranges separated by commas. */
    public const HOURS_PATTERN = '/^\s*(closed|([01]?\d|2[0-3]):[0-5]\d\s*-\s*([01]?\d|2[0-3]):[0-5]\d(\s*,\s*([01]?\d|2[0-3]):[0-5]\d\s*-\s*([01]?\d|2[0-3]):[0-5]\d)*)\s*$/i';

    /** Site attributes the first version stored properties in (folded into the component once). */
    private const LEGACY = [
        'name' => 'site.display_name', 'logo' => 'site.logo', 'email' => 'site.email',
        'phones' => 'site.phones', 'emails' => 'site.emails', 'variables' => 'site.variables',
    ];

    // ── Schema ─────────────────────────────────────────────────────

    /** @return array<string, array> field key => definition (with resolved options) */
    public static function fields(): array
    {
        return collect(config('site-properties.fields'))->map(function (array $f) {
            if (is_string($f['options'] ?? null)) {
                $f['options'] = match ($f['options']) {
                    'business_types' => config('site-properties.business_types'),
                    'timezones' => collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($z) => [$z => str_replace('_', ' ', $z)])->all(),
                    default => [],
                };
            }

            return $f;
        })->all();
    }

    public static function repeaters(): array
    {
        return config('site-properties.repeaters');
    }

    /** The node type a field / sub-field is stored as. */
    public static function nodeType(string $input): string
    {
        return match ($input) {
            'image' => 'image',
            'url' => 'url',
            'number' => 'number',
            'toggle' => 'boolean',
            default => 'text',
        };
    }

    /** Is this node label one the schema owns (a field or a repeater row)? */
    public static function isSchemaLabel(string $label): bool
    {
        static $labels = null;
        $labels ??= array_flip(array_column(config('site-properties.fields'), 'label'));
        if (isset($labels[$label])) {
            return true;
        }

        return self::repeaterMatch($label) !== null;
    }

    /** "Phone 2 Value" → ['phones', 2, 'value'] for schema repeaters, else null. */
    public static function repeaterMatch(string $label): ?array
    {
        foreach (self::repeaters() as $key => $r) {
            if (preg_match('/^'.preg_quote($r['prefix'], '/').' (\d+) (.+)$/', $label, $m)) {
                foreach ($r['fields'] as $sub => $f) {
                    if ($f['label'] === $m[2]) {
                        return [$key, (int) $m[1], $sub];
                    }
                }
            }
        }

        return null;
    }

    // ── The component ──────────────────────────────────────────────

    /** Is this the site's Site Properties component? */
    public static function isComponent(?Component $c): bool
    {
        return $c !== null && $c->name === config('site-properties.component')
            && in_array(config('site-properties.tag'), (array) $c->tags, true);
    }

    /**
     * Follow-up after the component changed (from either page): one business
     * name everywhere, icons rebuilt when the square icon changed, and the
     * published page data refreshed. Returns the icon result (see SiteIcons).
     */
    public static function afterSave(Site $site, string $oldIcon): ?bool
    {
        if (filled($name = self::value($site, 'site_name'))) {
            $site->setAttr('business_name', trim($name));
        }
        $icon = self::value($site, 'square_icon');
        $made = $icon !== $oldIcon ? app(SiteIcons::class)->generate($site, $icon) : 'unchanged';
        self::republish($site);

        return $made === 'unchanged' ? null : $made;
    }

    public static function find(Site $site): ?Component
    {
        return Component::where('site_id', $site->id)
            ->where('name', config('site-properties.component'))
            ->whereDoesntHave('pages')
            ->first();
    }

    /**
     * The site's Site Properties component, created on first use (seeded from
     * the legacy attributes), with any schema field it lacks added — values
     * already there are never touched.
     */
    public static function component(Site $site): Component
    {
        return DB::transaction(function () use ($site) {
            $component = self::find($site);
            $created = false;
            if (! $component) {
                $component = Component::create([
                    'site_id' => $site->id,
                    'name' => config('site-properties.component'),
                    'source' => 'platform',
                    'author' => 'platform',
                    'description' => 'Your business profile: name, logo, contact details, hours, legal, SEO and more. Also edited on the Properties page.',
                    'tags' => [config('site-properties.tag')],
                ]);
                $created = true;
            }

            $have = $component->nodes()->pluck('label')->flip();
            $order = (int) $component->nodes()->max('order');
            $legacy = $created ? self::legacyValues($site) : [];
            foreach (self::fields() as $key => $f) {
                if (! $have->has($f['label'])) {
                    $component->nodes()->create([
                        'parent' => '0',
                        'label' => $f['label'],
                        'type' => self::nodeType($f['input']),
                        'value' => $legacy['values'][$key] ?? ($f['default'] ?? null),
                        'order' => ++$order,
                        'description' => $f['help'] ?? null,
                    ]);
                }
            }

            if ($created && $legacy) {
                self::writeRows($component, $legacy['rows'], $order);
                self::writeVariables($component, $legacy['variables']);
                foreach (self::LEGACY as $attr) {
                    $site->forgetAttr($attr);
                }
            }

            return $component->load('nodes');
        });
    }

    /** Values from the attribute-based first version, mapped onto the schema. */
    private static function legacyValues(Site $site): array
    {
        $attrs = $site->attrMap();
        if (! array_intersect_key($attrs, array_flip(self::LEGACY))) {
            return [];
        }
        $list = fn (string $k) => array_values(array_filter(json_decode((string) ($attrs[self::LEGACY[$k]] ?? ''), true) ?: [], 'is_array'));

        return [
            'values' => array_filter([
                'site_name' => $attrs[self::LEGACY['name']] ?? null,
                'logo' => $attrs[self::LEGACY['logo']] ?? null,
                'email' => $attrs[self::LEGACY['email']] ?? null,
            ], 'filled'),
            'rows' => [
                'phones' => array_map(fn ($r) => ['label' => (string) ($r['label'] ?? ''), 'value' => (string) ($r['value'] ?? '')], $list('phones')),
                'emails' => array_map(fn ($r) => ['label' => (string) ($r['label'] ?? ''), 'value' => (string) ($r['value'] ?? '')], $list('emails')),
            ],
            'variables' => array_map(fn ($r) => [
                'key' => (string) ($r['key'] ?? ''), 'type' => ($r['type'] ?? '') === 'image' ? 'image' : 'text', 'value' => (string) ($r['value'] ?? ''),
            ], $list('variables')),
        ];
    }

    // ── Read / write ───────────────────────────────────────────────

    /**
     * Everything, from the component: field values, repeater rows and custom
     * variables (any node the schema doesn't own).
     *
     * @return array{values: array<string,string>, rows: array<string, list<array<string,string>>>, variables: list<array{key:string,type:string,value:string}>}
     */
    public static function get(Site $site, ?Component $component = null): array
    {
        $component ??= self::find($site);
        $nodes = $component ? $component->nodes : collect();
        $byLabel = $nodes->keyBy('label');

        $values = [];
        foreach (self::fields() as $key => $f) {
            $values[$key] = (string) ($byLabel->get($f['label'])?->value ?? ($component ? '' : ($f['default'] ?? '')));
        }

        $rows = array_fill_keys(array_keys(self::repeaters()), []);
        foreach ($nodes as $n) {
            if ($m = self::repeaterMatch($n->label)) {
                [$key, $i, $sub] = $m;
                $rows[$key][$i][$sub] = (string) $n->value;
            }
        }
        foreach ($rows as $key => $list) {
            ksort($list);
            $blank = array_fill_keys(array_keys(self::repeaters()[$key]['fields']), '');
            $rows[$key] = array_values(array_map(fn ($r) => array_merge($blank, $r), $list));
        }

        $variables = $nodes->reject(fn ($n) => self::isSchemaLabel($n->label))
            ->map(fn ($n) => ['key' => $n->label, 'type' => $n->type === 'image' ? 'image' : 'text', 'value' => (string) $n->value])
            ->values()->all();

        return compact('values', 'rows', 'variables');
    }

    /** One field's value (e.g. 'logo'), or '' when unset. */
    /**
     * Is $name already another site's Site Name? Case and spacing don't matter.
     * A site that never set one shows its address as its name ("grace-way" →
     * "Grace Way"), so that counts too.
     */
    public static function nameTaken(string $name, Site $except): bool
    {
        $norm = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
        $name = $norm($name);
        if ($name === '') {
            return false;
        }

        $stored = Node::where('label', config('site-properties.fields.site_name.label'))
            ->whereHas('component', fn ($q) => $q->where('site_id', '!=', $except->id)
                ->where('name', config('site-properties.component'))->whereDoesntHave('pages'))
            ->whereNotNull('value')
            ->with('component:id,site_id')
            ->get(['id', 'component_id', 'value']);
        if ($stored->contains(fn ($n) => $norm((string) $n->value) === $name)) {
            return true;
        }

        $slug = Str::slug($name);

        return $slug !== '' && Site::where('name', $slug)->whereKeyNot($except->id)
            ->whereNotIn('id', $stored->filter(fn ($n) => trim((string) $n->value) !== '')->pluck('component.site_id'))
            ->exists();
    }

    /**
     * Would renaming $site to $name clash with another site? Only a change is
     * checked, so a site that already shares a name can still save its other
     * properties.
     */
    public static function renameTaken(Site $site, string $name): bool
    {
        $norm = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));

        return $norm($name) !== $norm(self::value($site, 'site_name')) && self::nameTaken($name, $site);
    }

    public static function value(Site $site, string $key): string
    {
        $label = config("site-properties.fields.{$key}.label");
        if (! $label) {
            return '';
        }

        return (string) Node::whereHas('component', fn ($q) => $q->where('site_id', $site->id)
            ->where('name', config('site-properties.component'))->whereDoesntHave('pages'))
            ->where('label', $label)->value('value');
    }

    /**
     * Persist a validated set: a checkpoint is captured first (it shows in
     * the Edit page's history), then fields, rows and variables are written.
     */
    public static function save(Site $site, array $data, ?string $editor = null): Component
    {
        SiteTokens::forget($site->id); // {{tokens}} reflect the new values
        $component = self::component($site);
        app(ContentVersioner::class)->capture($component, $editor);

        DB::transaction(function () use ($component, $data) {
            $byLabel = $component->nodes->keyBy('label');
            foreach (self::fields() as $key => $f) {
                if (! array_key_exists($key, $data['values'] ?? [])) {
                    continue;
                }
                $value = trim((string) $data['values'][$key]);
                $node = $byLabel->get($f['label']);
                $node
                    ? $node->update(['value' => $value === '' ? null : $value])
                    : $component->nodes()->create(['parent' => '0', 'label' => $f['label'], 'type' => self::nodeType($f['input']), 'value' => $value ?: null, 'order' => 0]);
            }

            if (isset($data['rows'])) {
                $component->nodes()->get()->filter(fn ($n) => self::repeaterMatch($n->label) !== null)->each->delete();
                self::writeRows($component, $data['rows'], (int) $component->nodes()->max('order'));
            }
            if (isset($data['variables'])) {
                self::writeVariables($component, $data['variables']);
            }

        });

        return $component->load('nodes');
    }

    private static function writeRows(Component $component, array $rows, int $order): void
    {
        foreach (self::repeaters() as $key => $r) {
            foreach (array_values($rows[$key] ?? []) as $i => $row) {
                foreach ($r['fields'] as $sub => $f) {
                    $component->nodes()->create([
                        'parent' => '0',
                        'label' => $r['prefix'].' '.($i + 1).' '.$f['label'],
                        'type' => self::nodeType($f['input']),
                        'value' => filled($row[$sub] ?? null) ? trim((string) $row[$sub]) : null,
                        'order' => ++$order,
                    ]);
                }
            }
        }
    }

    /** Custom variables = the component's non-schema nodes; this replaces that set. */
    private static function writeVariables(Component $component, array $variables): void
    {
        $existing = $component->nodes()->get()->reject(fn ($n) => self::isSchemaLabel($n->label))->keyBy('label');
        $keep = [];
        $order = (int) $component->nodes()->max('order');
        foreach ($variables as $v) {
            $label = trim((string) ($v['key'] ?? ''));
            if ($label === '' || self::isSchemaLabel($label)) {
                continue;
            }
            $attrs = ['type' => ($v['type'] ?? '') === 'image' ? 'image' : 'text', 'value' => filled($v['value'] ?? null) ? (string) $v['value'] : null];
            ($node = $existing->get($label))
                ? $node->update($attrs + ['order' => ++$order])
                : $component->nodes()->create(['parent' => '0', 'label' => $label, 'order' => ++$order] + $attrs);
            $keep[] = $label;
        }
        $existing->reject(fn ($n) => in_array($n->label, $keep, true))->each->delete();
    }

    /** Re-publish every page's page.json (after the response) so baked site data picks the change up. */
    public static function republish(Site $site): void
    {
        $ids = $site->livePages()->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        dispatch(function () use ($ids) {
            foreach (Page::whereIn('id', $ids)->get() as $page) {
                try {
                    app(PageJsonPublisher::class)->publish($page);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        })->afterResponse();
    }

    /** An image value as a usable URL: "@media/…" refs (set by the Edit page) resolved; '' if none. */
    public static function imageUrl(Site $site, ?string $value): string
    {
        return filled($value) ? Media::resolveRef($site->id, trim($value)) : '';
    }

    // ── Hours ──────────────────────────────────────────────────────

    /** "09:00-12:00, 13:00-17:00" → [['09:00','12:00'],['13:00','17:00']]; "Closed"/'' → []. */
    public static function parseHours(string $text): array
    {
        if (! preg_match(self::HOURS_PATTERN, $text) || stripos($text, 'closed') !== false) {
            return [];
        }

        return collect(explode(',', $text))->map(function ($range) {
            [$a, $b] = array_map(fn ($t) => sprintf('%05s', trim($t)), explode('-', $range));

            return [str_pad($a, 5, '0', STR_PAD_LEFT), str_pad($b, 5, '0', STR_PAD_LEFT)];
        })->all();
    }

    // ── Output ─────────────────────────────────────────────────────

    /** The API shape (`site.properties`): grouped, decoded, URLs absolute. */
    public static function payload(Site $site): array
    {
        $p = self::get($site);
        $v = $p['values'];
        $abs = function (?string $url) use ($site) {
            $url = self::imageUrl($site, $url);

            return $url !== '' ? (str_starts_with($url, '/') ? url($url) : $url) : null;
        };
        $on = fn (string $k) => in_array(strtolower($v[$k] ?? ''), ['1', 'true', 'yes', 'on'], true);
        $list = fn (string $csv) => array_values(array_filter(array_map('trim', explode(',', $csv))));
        $socials = ['facebook', 'instagram', 'tiktok', 'linkedin', 'x', 'youtube'];
        $reviews = ['google', 'trustpilot', 'checkatrade', 'treatwell'];
        $icons = json_decode((string) $site->getAttr(config('site-properties.icons_attr')), true) ?: [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return [
            'name' => $v['site_name'] ?: Str::headline($site->name),
            'short_name' => $v['short_name'] ?: null,
            'tagline' => $v['tagline'] ?: null,
            'logo' => $abs($v['logo']),
            'logo_light' => $abs($v['logo_light']),
            'logo_text' => ($v['logo_text'] ?? '') ?: null,
            'logo_subtext' => ($v['logo_subtext'] ?? '') ?: null,
            'icon' => $abs($v['square_icon']),
            'icons' => array_map($abs, $icons),
            'share_image' => $abs($v['share_image']),
            'email' => $v['email'] ?: null,
            'phones' => $p['rows']['phones'],
            'emails' => $p['rows']['emails'],
            'whatsapp' => $v['whatsapp'] ?: null,
            'business' => [
                'legal_name' => $v['legal_name'] ?: null,
                'trading_name' => $v['trading_name'] ?: null,
                'type' => $v['business_type'] ?: 'LocalBusiness',
                'description' => $v['description'] ?: null,
                'address' => array_filter([
                    'street' => $v['address_street'], 'town' => $v['address_town'], 'county' => $v['address_county'],
                    'postcode' => $v['address_postcode'], 'country' => $v['address_country'],
                ], 'filled') ?: null,
                'geo' => is_numeric($v['latitude']) && is_numeric($v['longitude']) ? ['lat' => (float) $v['latitude'], 'lng' => (float) $v['longitude']] : null,
                'service_area' => $list($v['service_area']),
                'service_radius_km' => is_numeric($v['service_radius_km']) ? (float) $v['service_radius_km'] : null,
                'price_range' => $v['price_range'] ?: null,
                'google_business_url' => $v['google_business_url'] ?: null,
                'google_review_url' => $v['google_review_url'] ?: null,
                'year_established' => is_numeric($v['year_established']) ? (int) $v['year_established'] : null,
            ],
            'hours' => collect($days)->mapWithKeys(fn ($d) => [$d => self::parseHours($v["hours_{$d}"] ?? '')])->all()
                + ['closures' => $p['rows']['closures']],
            'social' => array_filter(array_combine($socials, array_map(fn ($s) => $v[$s] ?: null, $socials))),
            'reviews' => array_filter(array_combine($reviews, array_map(fn ($r) => $v["review_{$r}"] ?: null, $reviews))),
            'legal' => [
                'company_number' => $v['company_number'] ?: null,
                'vat_number' => $v['vat_number'] ?: null,
                'registered_office' => $v['registered_office'] ?: null,
                'ico_number' => $v['ico_number'] ?: null,
                'accreditations' => array_map(fn ($a) => ['badge' => $abs($a['badge'])] + $a, $p['rows']['accreditations']),
                'policies' => array_filter(['privacy' => $v['privacy_policy'] ?: null, 'terms' => $v['terms'] ?: null, 'cookies' => $v['cookie_policy'] ?: null]),
                'cookie_consent' => $on('cookie_consent'),
                'cookie_message' => $v['cookie_message'] ?: null,
            ],
            'seo' => [
                'title_pattern' => $v['title_pattern'] ?: '{page} | {site}',
                'meta_description' => $v['meta_description'] ?: null,
                'noindex' => $on('noindex') || ($on('noindex_while_draft') && ! $site->live),
                'canonical_host' => $v['canonical_host'] ?: 'apex',
                'sitemap' => $on('sitemap'),
            ],
            'tracking' => array_filter(['ga4_id' => $v['ga4_id'] ?: null, 'plausible_domain' => $v['plausible_domain'] ?: null, 'meta_pixel_id' => $v['meta_pixel_id'] ?: null]),
            'locale' => [
                'language' => $v['language'] ?: 'en-GB',
                'timezone' => $v['timezone'] ?: 'Europe/London',
                'date_format' => $v['date_format'] ?: 'j M Y',
                'currency' => strtoupper((string) ($site->currency ?: 'gbp')),
            ],
            'status' => [
                'live' => (bool) $site->live,
                'launch_date' => $v['launch_date'] ?: null,
                'maintenance' => $on('maintenance'),
                'maintenance_message' => $v['maintenance_message'] ?: null,
            ],
            'assistant' => [
                'name' => $v['assistant_name'] ?: null,
                'greeting' => $v['assistant_greeting'] ?: null,
                'tone' => $v['assistant_tone'] ?: 'friendly',
                'actions' => $list($v['assistant_actions']),
                'handover' => $v['assistant_handover'] ?: null,
            ],
            'variables' => collect($p['variables'])->mapWithKeys(fn ($x) => [$x['key'] => $x['type'] === 'image' ? $abs($x['value']) : $x['value']])->all(),
            'variable_types' => collect($p['variables'])->pluck('type', 'key')->all(),
        ];
    }

    /** schema.org LocalBusiness (or chosen subtype) JSON-LD for the live site's <head>. */
    public static function schemaOrg(Site $site, ?array $payload = null, ?string $url = null): array
    {
        $p = $payload ?? self::payload($site);
        $b = $p['business'];
        $dayNames = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];

        $hours = [];
        foreach ($dayNames as $d => $name) {
            foreach ($p['hours'][$d] ?? [] as [$open, $close]) {
                $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $name, 'opens' => $open, 'closes' => $close];
            }
        }
        foreach ($p['hours']['closures'] ?? [] as $c) {
            if (! filled($c['date'] ?? null)) {
                continue;
            }
            $ranges = self::parseHours((string) ($c['hours'] ?? ''));
            $hours[] = $ranges
                ? ['@type' => 'OpeningHoursSpecification', 'validFrom' => $c['date'], 'validThrough' => $c['date'], 'opens' => $ranges[0][0], 'closes' => end($ranges)[1]]
                : ['@type' => 'OpeningHoursSpecification', 'validFrom' => $c['date'], 'validThrough' => $c['date'], 'opens' => '00:00', 'closes' => '00:00'];
        }

        $a = $b['address'] ?? [];
        $phone = collect($p['phones'])->firstWhere('value')['value'] ?? null;

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $b['type'] ?: 'LocalBusiness',
            'name' => $p['name'],
            'legalName' => $b['legal_name'],
            'alternateName' => $b['trading_name'],
            'description' => $b['description'] ?: $p['tagline'],
            'url' => $url,
            'logo' => $p['logo'],
            'image' => $p['share_image'] ?: $p['logo'],
            'telephone' => $phone,
            'email' => $p['email'],
            'address' => $a ? array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $a['street'] ?? null, 'addressLocality' => $a['town'] ?? null,
                'addressRegion' => $a['county'] ?? null, 'postalCode' => $a['postcode'] ?? null,
                'addressCountry' => $a['country'] ?? null,
            ]) : null,
            'geo' => $b['geo'] ? ['@type' => 'GeoCoordinates', 'latitude' => $b['geo']['lat'], 'longitude' => $b['geo']['lng']] : null,
            'openingHoursSpecification' => $hours ?: null,
            'priceRange' => $b['price_range'],
            'areaServed' => $b['service_area'] ?: null,
            'sameAs' => array_values(array_filter(array_merge(array_values($p['social']), [$b['google_business_url']]))) ?: null,
            'foundingDate' => $b['year_established'] ? (string) $b['year_established'] : null,
            'vatID' => $p['legal']['vat_number'],
        ], fn ($x) => $x !== null && $x !== '' && $x !== []);
    }
}
