<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Support\Collection as LaravelCollection;

/**
 * Which items a block shows from a collection: how many, in what order,
 * matching what. One engine for the Edit panel preview, the content API
 * (per-block "views") and static page.json exports.
 *
 *   ['limit' => 3, 'sort' => 'field', 'field' => 'title', 'dir' => 'asc',
 *    'search' => 'wedding', 'filter_field' => 'category', 'filter_value' => 'Hair',
 *    'order' => [itemId…],    // this block's own order (used with manual sort)
 *    'exclude' => [itemId…]]  // hidden from this block only — still in the collection
 *
 * An empty query means "every published item, in manual order".
 */
class CollectionQuery
{
    public const SORTS = ['manual', 'newest', 'oldest', 'field'];

    public const MAX_LIMIT = 100;

    /** Field keys of a collection's schema (fields[].key|name). */
    public static function fieldKeys(Collection $col): array
    {
        return collect((array) $col->fields)->map(fn ($f) => is_array($f) ? ($f['key'] ?? $f['name'] ?? null) : (is_string($f) ? $f : null))
            ->filter()->map(fn ($k) => (string) $k)->unique()->values()->all();
    }

    /** Clean a query against the collection's fields. Returns [] when it changes nothing. */
    public static function normalize(array $q, array $fieldKeys): array
    {
        $limit = (int) ($q['limit'] ?? 0);
        $sort = in_array($sort = (string) ($q['sort'] ?? 'manual'), self::SORTS, true) ? $sort : 'manual';
        $field = in_array((string) ($q['field'] ?? ''), $fieldKeys, true) ? (string) $q['field'] : null;
        if ($sort === 'field' && ! $field) {
            $sort = 'manual';
        }
        $filterField = in_array((string) ($q['filter_field'] ?? ''), $fieldKeys, true) ? (string) $q['filter_field'] : null;
        $filterValue = mb_substr(trim((string) ($q['filter_value'] ?? '')), 0, 100);

        $ids = fn ($v) => array_values(array_unique(array_slice(array_filter(array_map(
            fn ($x) => is_scalar($x) ? trim((string) $x) : '', is_array($v) ? $v : []), fn ($x) => $x !== '' && strlen($x) <= 40), 0, 500)));
        $exclude = $ids($q['exclude'] ?? []);
        $order = $sort === 'manual' ? array_values(array_diff($ids($q['order'] ?? []), $exclude)) : [];

        $out = array_filter([
            'limit' => $limit > 0 ? min($limit, self::MAX_LIMIT) : null,
            'sort' => $sort !== 'manual' ? $sort : null,
            'field' => $sort === 'field' ? $field : null,
            'dir' => $sort === 'field' ? (($q['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc') : null,
            'search' => mb_substr(trim((string) ($q['search'] ?? '')), 0, 100) ?: null,
            'filter_field' => $filterField && $filterValue !== '' ? $filterField : null,
            'filter_value' => $filterField && $filterValue !== '' ? $filterValue : null,
            'order' => $order ?: null,
            'exclude' => $exclude ?: null,
        ], fn ($v) => $v !== null);

        return $out;
    }

    /**
     * Apply a query to a collection's published items (manual order first).
     *
     * @return LaravelCollection<int, CollectionItem>
     */
    public static function apply(Collection $col, array $q, ?LaravelCollection $items = null): LaravelCollection
    {
        $items ??= $col->items()->where('status', 'published')->get();
        $q = self::normalize($q, self::fieldKeys($col));
        if ($q === []) {
            return $items->values();
        }

        if ($ex = $q['exclude'] ?? null) {
            $items = $items->reject(fn (CollectionItem $i) => in_array((string) $i->id, $ex, true));
        }
        if ($s = $q['search'] ?? null) {
            $needle = mb_strtolower($s);
            $items = $items->filter(fn (CollectionItem $i) => str_contains(mb_strtolower(self::flatText($i->data ?? [])), $needle));
        }
        if ($ff = $q['filter_field'] ?? null) {
            $needle = mb_strtolower($q['filter_value']);
            $items = $items->filter(fn (CollectionItem $i) => str_contains(mb_strtolower(self::flatText(data_get($i->data, $ff) ?? '')), $needle));
        }

        $items = match ($q['sort'] ?? 'manual') {
            'newest' => $items->sortByDesc(fn ($i) => $i->created_at?->getTimestamp() ?? 0),
            'oldest' => $items->sortBy(fn ($i) => $i->created_at?->getTimestamp() ?? 0),
            'field' => $items->sort(function ($a, $b) use ($q) {
                $cmp = strnatcasecmp(self::flatText(data_get($a->data, $q['field']) ?? ''), self::flatText(data_get($b->data, $q['field']) ?? ''));

                return ($q['dir'] ?? 'asc') === 'desc' ? -$cmp : $cmp;
            }),
            // Manual: the block's own order first (if set), then the rest in the collection's order.
            default => ($o = $q['order'] ?? null)
                ? (function () use ($items, $o) {
                    $rank = array_flip($o);
                    $base = $items->values()->pluck('id')->map(fn ($id) => (string) $id)->flip();

                    return $items->sortBy(fn ($i) => $rank[(string) $i->id] ?? 1_000_000 + $base[(string) $i->id]);
                })()
                : $items,
        };

        return ($q['limit'] ?? null) ? $items->take($q['limit'])->values() : $items->values();
    }

    /** "Showing 3 of 12 · newest first · matching “x”" */
    public static function summary(array $q, int $total, int $shown): string
    {
        $parts = [$shown === $total ? 'All '.$total : 'Showing '.$shown.' of '.$total];
        $parts[] = match ($q['sort'] ?? 'manual') {
            'newest' => 'newest first',
            'oldest' => 'oldest first',
            'field' => 'by '.str_replace('_', ' ', (string) ($q['field'] ?? '')).(($q['dir'] ?? 'asc') === 'desc' ? ' (Z–A)' : ' (A–Z)'),
            default => empty($q['order']) ? 'manual order' : 'custom order',
        };
        if (! empty($q['exclude'])) {
            $parts[] = count($q['exclude']).' hidden';
        }
        if (! empty($q['search'])) {
            $parts[] = 'matching “'.$q['search'].'”';
        }
        if (! empty($q['filter_field'])) {
            $parts[] = str_replace('_', ' ', $q['filter_field']).' = “'.$q['filter_value'].'”';
        }

        return implode(' · ', $parts);
    }

    /** All string values of an item (nested too), joined — for search/sort. */
    private static function flatText(mixed $v): string
    {
        if (is_array($v)) {
            return implode(' ', array_map(fn ($x) => self::flatText($x), $v));
        }

        return is_scalar($v) ? strip_tags((string) $v) : '';
    }
}
