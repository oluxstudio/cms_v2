<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;

/**
 * Collection fields the SYSTEM fills (a field's `auto` source), set in the
 * collection's "Edit fields" panel:
 *   created_at / updated_at   date & time ("2026-10-04 14:05", site clock)
 *   created_by / updated_by   who saved it (the editor's name; "Website
 *                             visitor" for public submissions, "System" for jobs)
 *   number                    1, 2, 3… in the order entries are added
 *   property:{token}          a Site Property / variable — stored as {{token}}
 *                             so it always shows the current value
 *   slug:{field}+{field}…     a web-address slug made from other fields of the
 *                             entry (dates as Y-m-d): "slug:title+date" →
 *                             "worship-night-2026-10-18"; unique within the
 *                             collection (-2, -3 … added) and rebuilt on save
 * Filled whenever an entry is created or its data changes (any path: editor,
 * API, submissions, the agent), never typed by hand. Separate from `hidden`
 * (kept out of the edit form, still sent to the website).
 */
class CollectionAutoFields
{
    public const SOURCES = [
        'created_at' => 'Date & time created',
        'updated_at' => 'Date & time last updated',
        'created_by' => 'Created by',
        'updated_by' => 'Updated by',
        'number' => 'Entry number (1, 2, 3…)',
    ];

    private const FORMAT = 'Y-m-d H:i';

    /** Every source for this site: the built-ins, then each Site Property / variable. @return array<string,string> */
    public static function options(Site $site): array
    {
        $props = collect(SiteTokens::all($site))
            ->mapWithKeys(fn ($t) => ['property:'.$t['token'] => 'Site property: '.$t['label']])->all();

        return self::SOURCES + $props;
    }

    public static function valid(?string $source): bool
    {
        return $source !== null && (isset(self::SOURCES[$source]) || preg_match('/^property:[a-z0-9_.-]+$/', $source) === 1
            || self::slugSources($source) !== []);
    }

    /** The fields a `slug:a+b` source is built from ([] when $source is not a slug source). @return list<string> */
    public static function slugSources(?string $source): array
    {
        if (! preg_match('/^slug:([A-Za-z][\w-]*(?:\+[A-Za-z][\w-]*)*)$/', (string) $source, $m)) {
            return [];
        }

        return array_values(array_unique(explode('+', $m[1])));
    }

    /** A slug source for these field keys (null when none). */
    public static function slugSource(array $keys): ?string
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => is_string($k) && preg_match('/^[A-Za-z][\w-]*$/', $k))));

        return $keys === [] ? null : 'slug:'.implode('+', $keys);
    }

    public static function label(?string $source): string
    {
        if (isset(self::SOURCES[(string) $source])) {
            return self::SOURCES[$source];
        }

        if (($from = self::slugSources($source)) !== []) {
            return 'Slug from '.implode(' + ', array_map(fn ($k) => \Illuminate\Support\Str::headline($k), $from));
        }

        return str_starts_with((string) $source, 'property:') ? 'Site property {{'.substr($source, 9).'}}' : '';
    }

    /** Fields of $collection with a system source: key => source. @return array<string,string> */
    public static function autoFields(?Collection $collection): array
    {
        return collect($collection?->fields ?? [])
            ->filter(fn ($f) => self::valid($f['auto'] ?? null) && filled($f['key'] ?? null))
            ->mapWithKeys(fn ($f) => [(string) $f['key'] => (string) $f['auto']])->all();
    }

    /**
     * Fill $item's system fields in place (before it is saved). $backfill =
     * filling entries that already existed: "updated" uses their own last
     * change, not now, and nothing is attributed to the person editing fields.
     */
    public static function fill(CollectionItem $item, bool $backfill = false): void
    {
        $collection = $item->relationLoaded('collection') ? $item->collection : Collection::find($item->collection_id);
        $auto = self::autoFields($collection);
        if ($auto === []) {
            return;
        }
        $data = (array) ($item->data ?? []);
        $actor = $backfill ? null : self::actor();
        // On an edit, values set once (number, created …) come from what was stored — never from the incoming data.
        $stored = $item->exists ? (array) ($item->getOriginal('data') ?? []) : null;

        foreach ($auto as $key => $source) {
            $current = $data[$key] ?? null;
            if ($stored !== null && in_array($source, ['number', 'created_at', 'created_by'], true) && ($stored[$key] ?? '') !== '') {
                $current = $stored[$key];
            }
            $empty = $current === null || $current === '';
            $data[$key] = match (true) {
                $source === 'created_at' => $empty ? ($item->created_at ?? now())->format(self::FORMAT) : $current,
                $source === 'updated_at' => ($backfill ? ($item->updated_at ?? $item->created_at ?? now()) : now())->format(self::FORMAT),
                $source === 'created_by' => $empty ? ($actor ?? 'System') : $current,
                $source === 'updated_by' => $backfill ? ($empty ? 'System' : $current) : $actor,
                $source === 'number' => $empty ? self::nextNumber($item, $key) : $current,
                str_starts_with($source, 'property:') => '{{'.substr($source, 9).'}}',
                str_starts_with($source, 'slug:') => self::uniqueSlug($item, $key, self::slugFrom($data, self::slugSources($source), $collection)) ?: $current,
                default => $current,
            };
        }
        $item->data = $data;
    }

    /**
     * A field just got a system source: fill it on the existing entries, oldest
     * first (so entry numbers follow the order they were added). Quiet saves —
     * nothing else about the entries changes.
     */
    public static function backfill(Collection $collection): void
    {
        $collection->items()->withTrashed()->orderBy('created_at')->orderBy('id')->get()
            ->each(function (CollectionItem $item) use ($collection) {
                $item->setRelation('collection', $collection);
                $before = $item->data;
                self::fill($item, backfill: true);
                if ($item->data !== $before) {
                    $item->saveQuietly();
                }
            });
    }

    /**
     * The slug for $data from its $from fields: each value slugified, dates
     * (date / datetime fields, or any "2026-10-18T19:00"-style value) as
     * Y-m-d, empty values skipped. "" when every source is empty.
     */
    public static function slugFrom(array $data, array $from, ?Collection $collection = null): string
    {
        $types = collect($collection?->fields ?? [])->mapWithKeys(fn ($f) => [(string) ($f['key'] ?? '') => (string) ($f['type'] ?? 'text')]);
        $parts = [];
        foreach ($from as $k) {
            $v = $data[$k] ?? null;
            if (! is_scalar($v) || trim((string) $v) === '' || is_bool($v)) {
                continue;
            }
            $v = trim((string) $v);
            if (in_array($types[$k] ?? '', ['date', 'datetime'], true) || preg_match('/^\d{4}-\d{2}-\d{2}([T ]|$)/', $v)) {
                try {
                    $v = \Illuminate\Support\Carbon::parse($v)->format('Y-m-d');
                } catch (\Throwable) {
                    // not a date after all — slugified as text
                }
            }
            $parts[] = \Illuminate\Support\Str::slug($v);
        }

        return trim(implode('-', array_filter($parts, 'strlen')), '-');
    }

    /** $base, or $base-2, -3 … — the first no other entry of the collection (deleted ones too) uses for $key. */
    private static function uniqueSlug(CollectionItem $item, string $key, string $base): string
    {
        if ($base === '') {
            return '';
        }
        $taken = CollectionItem::withTrashed()->where('collection_id', $item->collection_id)
            ->when($item->exists, fn ($q) => $q->whereKeyNot($item->getKey()))
            ->pluck('data')
            ->map(fn ($d) => (is_array($d) ? $d : (json_decode((string) $d, true) ?: []))[$key] ?? null)
            ->filter(fn ($v) => is_string($v) && $v !== '')->flip();
        $slug = $base;
        for ($n = 2; $taken->has($slug); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private static function actor(): string
    {
        return Auth::user()?->name ?: (app()->runningInConsole() ? 'System' : 'Website visitor');
    }

    private static function nextNumber(CollectionItem $item, string $key): int
    {
        $max = CollectionItem::withTrashed()->where('collection_id', $item->collection_id)
            ->when($item->exists, fn ($q) => $q->whereKeyNot($item->getKey()))
            ->pluck('data')
            ->map(fn ($d) => (is_array($d) ? $d : (json_decode((string) $d, true) ?: []))[$key] ?? null)
            ->filter(fn ($v) => is_numeric($v))->max();

        return (int) $max + 1;
    }
}
