<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Component;
use App\Models\Node;
use App\Models\Site;
use App\Services\ContentVersioner;
use Illuminate\Support\Str;

/**
 * Two-way link between Site Properties (site name, email, phone, address,
 * socials…) and the places a template keeps the same details in its own
 * content:
 *
 *   • a field on a profile-style collection entry   site-profile → {name, email, phone, city…}
 *   • a contact row in a label/value collection     contact-info → {label: "Email Address", value, href}
 *   • a field on a block (component node)           Site Header → Phone (+ "Phone Link" = tel:…)
 *
 * load: empty properties are FILLED from those places (shown as "from …").
 * save: a changed property is WRITTEN to every place (links kept in step:
 * mailto:/tel:), each place checkpointed first. A property with no place
 * yet is ADDED — contact details as a row in the contact collection, the
 * rest to a "Site Profile" entry (both created when missing).
 *
 * Only these structured spots are touched — never free text (a policy
 * paragraph mentioning the old email stays as written).
 */
class PropertyLinks
{
    /**
     * Property → how to recognise it.
     *   keys:   item field names that hold it (any collection entry with a profile shape)
     *   labels: block field labels (case-insensitive) that hold it
     *   row:    regex on a contact row's label
     *   link:   href format for contact rows / "<Label> Link" fields
     *   rowLabel: label for a contact row this creates
     */
    public const PROPS = [
        'site_name' => ['keys' => ['site_name', 'business_name', 'brand', 'brand_name', 'company_name', 'organisation', 'organization', 'church_name'],
            'profileKeys' => ['name'], 'labels' => ['site name', 'business name', 'brand', 'brand name', 'logo text', 'company name', 'church name']],
        'tagline' => ['keys' => ['tagline', 'slogan', 'strapline'], 'labels' => ['tagline', 'slogan', 'strapline']],
        'logo' => ['keys' => ['logo', 'logo_url', 'brand_logo', 'site_logo'], 'labels' => ['logo', 'site logo', 'logo image', 'brand logo']],
        'square_icon' => ['keys' => ['favicon', 'site_icon', 'favicon_url', 'app_icon'], 'labels' => ['favicon', 'site icon', 'app icon']],
        // Written as a summary ("Mon–Fri 09:00–17:00 · Sat Closed"); never read back (free text can't split into days).
        'hours' => ['keys' => ['hours', 'opening_hours', 'office_hours', 'open_hours', 'opening_times'], 'labels' => ['opening hours', 'office hours', 'hours', 'opening times'],
            'row' => '/\b(opening hours|office hours|opening times|hours)\b/i', 'rowLabel' => 'Opening Hours', 'pushOnly' => true],
        'email' => ['keys' => ['email', 'email_address', 'contact_email'], 'labels' => ['email', 'email address', 'contact email'],
            'row' => '/\b(e-?mail)\b/i', 'link' => 'mailto:', 'rowLabel' => 'Email Address'],
        'phone' => ['keys' => ['phone', 'phone_number', 'telephone', 'tel', 'contact_phone', 'contact_number'], 'labels' => ['phone', 'phone number', 'telephone', 'contact number'],
            'row' => '/\b(phone|tel|telephone|call|mobile)\b/i', 'link' => 'tel:', 'rowLabel' => 'Phone Number'],
        'whatsapp' => ['keys' => ['whatsapp'], 'labels' => ['whatsapp'], 'row' => '/\bwhats ?app\b/i', 'link' => 'https://wa.me/', 'rowLabel' => 'WhatsApp'],
        'address_street' => ['keys' => ['address', 'street', 'address_line1', 'address_street', 'street_address'], 'labels' => ['address', 'street address']],
        'address_town' => ['keys' => ['city', 'town', 'address_town'], 'labels' => ['city', 'town']],
        'address_postcode' => ['keys' => ['postcode', 'post_code', 'zip', 'zipcode', 'postal_code'], 'labels' => ['postcode', 'zip code']],
        'address_country' => ['keys' => ['country'], 'labels' => ['country']],
        'facebook' => ['keys' => ['facebook', 'facebook_url'], 'labels' => ['facebook']],
        'instagram' => ['keys' => ['instagram', 'instagram_url'], 'labels' => ['instagram']],
        'x' => ['keys' => ['x', 'twitter', 'twitter_url'], 'labels' => ['x', 'twitter']],
        'linkedin' => ['keys' => ['linkedin', 'linkedin_url'], 'labels' => ['linkedin']],
        'youtube' => ['keys' => ['youtube', 'youtube_url'], 'labels' => ['youtube']],
        'tiktok' => ['keys' => ['tiktok', 'tiktok_url'], 'labels' => ['tiktok']],
    ];

    /** Collections that describe the site itself (one entry of "about us" details). */
    // Whole-slug match: "site-profile", "business-details"… — not "profile-skills".
    private const PROFILE_SLUG = '/^((site|business|company|church|organi[sz]ation)-?)?(profile|info|details|settings)$|^(business|company|organi[sz]ation|contact-?details)$/i';

    /**
     * Every place each property lives in this site's content.
     *
     * @return array<string, list<array{kind: string, id: string, field: string, link: ?string, where: string, value: string}>>
     */
    public static function discover(Site $site): array
    {
        $out = array_fill_keys(array_keys(self::PROPS), []);

        // Collections: profile entries + contact rows.
        $collections = Collection::where('site_id', $site->id)->get();
        $items = CollectionItem::where('site_id', $site->id)->whereIn('collection_id', $collections->pluck('id'))->get()->groupBy('collection_id');
        foreach ($collections as $col) {
            $rows = $items[$col->id] ?? collect();
            // Named fields only count on a profile-style collection (its slug says so, or it has a
            // single entry) — a "leadership" list where each person has an email is left alone.
            $profileSlug = (bool) preg_match(self::PROFILE_SLUG, (string) $col->slug);
            $profile = $profileSlug || $rows->count() === 1;
            foreach ($rows as $item) {
                $d = (array) ($item->data ?? []);
                $where = $col->name ?: Str::headline((string) $col->slug);

                // Contact row: {label, value(, href)}
                if (is_string($d['label'] ?? null) && array_key_exists('value', $d) && is_scalar($d['value'])) {
                    foreach (self::PROPS as $prop => $p) {
                        if (! empty($p['row']) && preg_match($p['row'], $d['label'])) {
                            $out[$prop][] = ['kind' => 'item', 'id' => (string) $item->id, 'field' => 'value',
                                'link' => array_key_exists('href', $d) ? 'href' : null, 'where' => $where, 'value' => (string) $d['value']];
                            break;
                        }
                    }

                    continue;
                }
                // Profile entry: named fields ("name" only on an explicitly profile-named collection).
                if (! $profile) {
                    continue;
                }
                foreach (self::PROPS as $prop => $p) {
                    $keys = array_merge($p['keys'], $profileSlug ? ($p['profileKeys'] ?? []) : []);
                    foreach ($keys as $k) {
                        if (array_key_exists($k, $d) && is_scalar($d[$k])) {
                            $out[$prop][] = ['kind' => 'item', 'id' => (string) $item->id, 'field' => $k, 'link' => null, 'where' => $where, 'value' => (string) $d[$k]];
                            break;
                        }
                    }
                }
            }
        }

        // Block fields (not the Site Properties block itself).
        $components = Component::where('site_id', $site->id)->get(['id', 'name', 'tags']);
        $spIds = $components->filter(fn ($c) => SiteProperties::isComponent($c))->pluck('id')->all();
        $nodes = Node::whereIn('component_id', $components->pluck('id'))->whereNotIn('component_id', $spIds)
            ->whereIn('type', ['text', 'url', 'image'])->get(['id', 'component_id', 'label', 'value']);
        $byComponent = $nodes->groupBy('component_id');
        foreach ($nodes as $n) {
            $label = mb_strtolower(trim((string) $n->label));
            foreach (self::PROPS as $prop => $p) {
                if (in_array($label, $p['labels'], true)) {
                    // A sibling "<Label> Link" field carries the tel:/mailto: href.
                    $link = $byComponent[$n->component_id]->first(fn ($x) => mb_strtolower(trim((string) $x->label)) === $label.' link');
                    $out[$prop][] = ['kind' => 'node', 'id' => (string) $n->id, 'field' => 'value', 'link' => $link ? (string) $link->id : null,
                        'where' => (string) ($components->firstWhere('id', $n->component_id)?->name ?? 'a block'), 'value' => (string) $n->value];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Values to fill into EMPTY properties, with where they came from.
     *
     * @param  array<string,string>  $current  property => value (phone = first phone row)
     * @return array<string, array{value: string, where: string}>
     */
    public static function fills(Site $site, array $current, ?array $slots = null): array
    {
        $slots ??= self::discover($site);
        $fills = [];
        foreach ($slots as $prop => $list) {
            if (trim((string) ($current[$prop] ?? '')) !== '' || ! empty(self::PROPS[$prop]['pushOnly'])) {
                continue;
            }
            $found = collect($list)->first(fn ($s) => trim($s['value']) !== '' && ! str_starts_with(trim($s['value']), '#'));
            if ($found) {
                $fills[$prop] = ['value' => trim($found['value']), 'where' => $found['where']];
            }
        }

        return $fills;
    }

    /** Where each property is used, for "Also updates …" hints. @return array<string, list<string>> */
    public static function places(?array $slots): array
    {
        return collect($slots ?? [])->map(fn ($list) => collect($list)->pluck('where')->unique()->values()->all())->filter()->all();
    }

    /**
     * Write changed properties into the site's content (and add missing ones).
     *
     * @param  array<string,string>  $old  values before saving
     * @param  array<string,string>  $new  values saved
     * @return list<string> names of the places that changed
     */
    public static function push(Site $site, array $old, array $new, ?string $editor = null): array
    {
        $slots = self::discover($site);
        $versioner = app(ContentVersioner::class);
        $captured = [];
        $touched = [];
        $capture = function ($subject) use ($versioner, $editor, &$captured) {
            $key = $subject::class.':'.$subject->getKey();
            if (! isset($captured[$key])) {
                $versioner->capture($subject, $editor);
                $captured[$key] = true;
            }
        };

        foreach (self::PROPS as $prop => $p) {
            $value = trim((string) ($new[$prop] ?? ''));
            if ($value === '' || $value === trim((string) ($old[$prop] ?? ''))) {
                continue; // unchanged or cleared — never blank out the template's content
            }
            $link = isset($p['link']) ? $p['link'].($p['link'] === 'tel:' || $p['link'] === 'https://wa.me/' ? preg_replace('/[^\d+]/', '', $value) : $value) : null;

            if ($slots[$prop] === []) {
                self::create($site, $prop, $value, $link, $capture, $touched);

                continue;
            }
            foreach ($slots[$prop] as $s) {
                if ($s['kind'] === 'item') {
                    $item = CollectionItem::where('site_id', $site->id)->find($s['id']);
                    if (! $item) {
                        continue;
                    }
                    $capture($item->collection);
                    $d = (array) $item->data;
                    $d[$s['field']] = $value;
                    if ($s['link'] && $link) {
                        $d[$s['link']] = $link;
                    }
                    $item->update(['data' => $d]);
                } else {
                    $node = Node::find($s['id']);
                    if (! $node) {
                        continue;
                    }
                    $capture($node->component);
                    $node->update(['value' => $value]);
                    if ($s['link'] && $link) {
                        Node::whereKey($s['link'])->update(['value' => $link]);
                    }
                }
                $touched[] = $s['where'];
            }
        }
        if ($touched !== []) {
            SiteContentCache::bump($site->id);
        }

        return array_values(array_unique($touched));
    }

    /** No place holds this property yet: contact details → a contact row; the rest → the Site Profile entry. */
    private static function create(Site $site, string $prop, string $value, ?string $link, callable $capture, array &$touched): void
    {
        $p = self::PROPS[$prop];
        if (! empty($p['rowLabel'])) {
            $contact = self::contactCollection($site);
            $capture($contact);
            $contact->items()->create(['site_id' => $site->id, 'status' => 'published',
                'position' => (int) $contact->items()->max('position') + 1,
                'data' => array_filter(['label' => $p['rowLabel'], 'value' => $value, 'href' => $link], fn ($v) => $v !== null)]);
            $touched[] = $contact->name;

            return;
        }
        $profile = Collection::where('site_id', $site->id)->where('slug', 'site-profile')->first()
            ?? Collection::create(['site_id' => $site->id, 'name' => 'Site Profile', 'slug' => 'site-profile', 'type' => 'list', 'is_public' => true,
                'description' => 'Your site\'s details (kept in step with Site Properties).', 'fields' => []]);
        $capture($profile);
        $key = $prop === 'site_name' ? 'name' : $prop;
        $fields = collect((array) $profile->fields);
        if (! $fields->contains(fn ($f) => ($f['key'] ?? null) === $key)) {
            $profile->update(['fields' => $fields->push(['key' => $key, 'label' => Str::headline($key), 'type' => in_array($prop, ['facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok'], true) ? 'url' : 'text'])->all()]);
        }
        $item = $profile->items()->first() ?? $profile->items()->create(['site_id' => $site->id, 'status' => 'published', 'position' => 0, 'data' => []]);
        $item->update(['data' => array_merge((array) $item->data, [$key => $value])]);
        $touched[] = $profile->name;
    }

    /** The site's label/value contact collection — or a new "Contact Info" one. */
    private static function contactCollection(Site $site): Collection
    {
        $found = Collection::where('site_id', $site->id)->get()->first(function (Collection $c) {
            $first = $c->items()->first();

            return $first && is_array($first->data) && array_key_exists('label', $first->data) && array_key_exists('value', $first->data);
        });

        return $found ?? Collection::create(['site_id' => $site->id, 'name' => 'Contact Info', 'slug' => 'contact-info', 'type' => 'list', 'is_public' => true,
            'fields' => [['key' => 'label', 'label' => 'Label', 'type' => 'text'], ['key' => 'value', 'label' => 'Value', 'type' => 'text'], ['key' => 'href', 'label' => 'Link', 'type' => 'url']]]);
    }
}
