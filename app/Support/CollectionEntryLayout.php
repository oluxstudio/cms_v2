<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * How an entry's fields are laid out in the collection side panel. ONE place
 * decides it, so view mode and edit mode show the fields in the same order:
 *
 *   heading (the name/title shown as the panel title — first in the form)
 *   → media (pictures, video) → facts (short values) → chips (word lists)
 *   → text (long text) → nested (rows / groups)
 *
 * A field is placed by what it holds; an empty one (a new entry) by its type.
 */
class CollectionEntryLayout
{
    public const ORDER = ['media', 'facts', 'chips', 'text', 'nested'];

    /** The field whose value is the panel title: name, title or label first, else the first filled field. */
    public static function headingKey(array $fields, array $data): ?string
    {
        $keys = self::keys($fields);
        foreach (array_unique(['name', 'title', 'label', ...$keys]) as $k) {
            if (in_array($k, $keys, true) && is_string($data[$k] ?? null) && trim($data[$k]) !== '') {
                return $k;
            }
        }
        foreach (['name', 'title', 'label'] as $k) {
            if (in_array($k, $keys, true)) {
                return $k;   // a new entry: the field that will become its title
            }
        }

        return null;
    }

    /**
     * The view-mode sections: section => list of rows (key, name, value, type,
     * kind, items for chips), plus 'empty' => names of empty fields and
     * 'heading' => the title field's key. $data values are as stored.
     */
    public static function sections(array $fields, array $data, Site $site): array
    {
        $out = array_fill_keys([...self::ORDER, 'empty'], []);
        $out['heading'] = self::headingKey($fields, $data);
        $headingText = $out['heading'] ? trim(strip_tags((string) ($data[$out['heading']] ?? ''))) : null;

        foreach ($fields as $f) {
            $key = $f['key'] ?? $f['name'] ?? null;
            if (! $key) {
                continue;
            }
            $v = data_get($data, $key);
            // {{tokens}} (e.g. a field filled from a Site Property) show their current value.
            $v = is_string($v) ? SiteTokens::apply($site, $v) : $v;
            $type = $f['type'] ?? 'text';
            $name = $f['label'] ?? Str::headline($key);
            if (self::isEmpty($v, $type)) {
                $out['empty'][] = $name;

                continue;
            }
            if (is_string($v) && trim(strip_tags($v)) === $headingText) {
                continue; // already the panel title
            }
            $row = ['key' => $key, 'name' => $name, 'value' => $v, 'type' => $type, 'kind' => MediaValue::kind($v, $site->id)];
            $section = self::sectionFor($v, $type, $site->id, $row['kind'], $items);
            $out[$section][] = $row + ($section === 'chips' ? ['items' => $items] : []);
        }

        return $out;
    }

    /**
     * Field keys in view-mode order, for the edit form: the title field, then
     * each section in turn (schema order within a section). Empty fields are
     * placed by their type. Uses the STORED data, so nothing jumps while typing.
     *
     * @return list<string>
     */
    public static function order(array $fields, array $data, Site $site): array
    {
        $heading = self::headingKey($fields, $data);
        $buckets = array_fill_keys(self::ORDER, []);
        foreach ($fields as $f) {
            $key = $f['key'] ?? $f['name'] ?? null;
            if (! $key || $key === $heading) {
                continue;
            }
            $v = data_get($data, $key);
            $type = $f['type'] ?? 'text';
            $section = self::isEmpty($v, $type)
                ? self::sectionForType($type, $key)
                : self::sectionFor($v, $type, $site->id, MediaValue::kind($v, $site->id), $items);
            $buckets[$section][] = $key;
        }

        return array_values(array_filter([$heading, ...array_merge(...array_values($buckets))]));
    }

    /** Sort schema fields into view-mode order (fields missing from the order keep their place at the end). */
    public static function sortFields(array $fields, array $data, Site $site): array
    {
        $rank = array_flip(self::order($fields, $data, $site));

        return collect($fields)->values()
            ->sortBy(fn ($f, $i) => [($rank[$f['key'] ?? $f['name'] ?? ''] ?? 1000), $i])
            ->values()->all();
    }

    /** @return list<string> the schema's field keys */
    private static function keys(array $fields): array
    {
        return array_values(array_filter(array_map(fn ($f) => (string) ($f['key'] ?? $f['name'] ?? ''), $fields)));
    }

    private static function isEmpty(mixed $v, string $type): bool
    {
        return ! Collection::isBooleanType($type) && ($v === null || $v === '' || $v === []);
    }

    /** Where a filled value goes (same rules the view used). $items = the chip words. */
    private static function sectionFor(mixed $v, string $type, string $siteId, ?string $kind, ?array &$items = null): string
    {
        $lines = is_string($v) ? array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $v)), 'strlen')) : null;
        $items = null;

        if (in_array($kind, ['media', 'media-list', 'media-lines'], true)) {
            return 'media';
        }
        if ($type === 'tags' || ($kind === 'list' && collect($v)->every(fn ($x) => is_scalar($x) && mb_strlen((string) $x) <= 40))
            || ($lines && count($lines) > 1 && collect($lines)->every(fn ($l) => mb_strlen($l) <= 40))) {
            $items = is_array($v) ? array_map('strval', $v) : $lines;

            return 'chips';
        }
        if (in_array($kind, ['rows', 'group', 'list'], true)) {
            return 'nested';
        }
        if (Collection::textareaRows($type) || $kind === 'long-text' || (is_string($v) && mb_strlen(strip_tags($v)) > 80)) {
            return 'text';
        }

        return 'facts';
    }

    /** Where an EMPTY field goes, judged by its type. */
    private static function sectionForType(string $type, string $key): string
    {
        return match (true) {
            MediaValue::isMediaType($type, $key) => 'media',
            $type === 'tags' => 'chips',
            in_array($type, ['list', 'rows', 'group', 'json', 'array'], true) => 'nested',
            (bool) Collection::textareaRows($type) => 'text',
            default => 'facts',
        };
    }
}
