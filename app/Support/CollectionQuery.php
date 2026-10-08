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
 *    'filter_op' => 'is',     // exact match (any element of a list/tags field); default: contains
 *    'take' => 'last',        // which `limit` entries: first (default) | last | random
 *    'all' => true,           // explicitly every entry (overrides the block's own default)
 *    'order' => [itemId…],    // this block's own order (used with manual sort)
 *    'exclude' => [itemId…]]  // hidden from this block only — still in the collection
 *
 * An empty query means "every published item, in manual order".
 */
class CollectionQuery
{
    public const SORTS = ['manual', 'newest', 'oldest', 'field'];

    public const MAX_LIMIT = 100;

    /** Which end of the (filtered, sorted) entries `limit` takes. */
    public const TAKES = ['first', 'last', 'random'];

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
        $search = mb_substr(trim((string) ($q['search'] ?? '')), 0, 100);
        // The panel's "Show" choice: the block's default, every entry, or entries matching.
        $mode = in_array($q['mode'] ?? null, ['default', 'all', 'match'], true) ? $q['mode'] : null;
        $filtered = $search !== '' || ($filterField && $filterValue !== '');
        $all = ! $filtered && ($mode === 'all' || ($mode === null && ! empty($q['all'])));

        $ids = fn ($v) => array_values(array_unique(array_slice(array_filter(array_map(
            fn ($x) => is_scalar($x) ? trim((string) $x) : '', is_array($v) ? $v : []), fn ($x) => $x !== '' && strlen($x) <= 40), 0, 500)));
        $exclude = $ids($q['exclude'] ?? []);
        // Pick mode: exactly these entries, in this order (an empty pick shows none).
        $pick = array_key_exists('pick', $q) && is_array($q['pick']) ? $ids($q['pick']) : null;
        $order = $sort === 'manual' ? array_values(array_diff($ids($q['order'] ?? []), $exclude)) : [];

        $out = array_filter([
            'limit' => $limit > 0 ? min($limit, self::MAX_LIMIT) : null,
            'sort' => $sort !== 'manual' ? $sort : null,
            'field' => $sort === 'field' ? $field : null,
            'dir' => $sort === 'field' ? (($q['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc') : null,
            'all' => $all ?: null,
            'search' => $search ?: null,
            'filter_field' => $filterField && $filterValue !== '' ? $filterField : null,
            'filter_value' => $filterField && $filterValue !== '' ? $filterValue : null,
            'filter_op' => $filterField && $filterValue !== '' && ($q['filter_op'] ?? '') === 'is' ? 'is' : null,
            'take' => in_array($q['take'] ?? null, ['last', 'random'], true) ? $q['take'] : null,
            'order' => $order ?: null,
            'exclude' => $exclude ?: null,
        ], fn ($v) => $v !== null);
        if ($pick !== null) {
            // Picked entries ARE the selection: order/sort/search/filter don't apply.
            $out = array_filter(['limit' => $out['limit'] ?? null, 'pick' => $pick], fn ($v) => $v !== null);
        }

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

        if (array_key_exists('pick', $q)) {
            $byId = $items->keyBy(fn (CollectionItem $i) => (string) $i->id);
            $picked = collect($q['pick'])->map(fn ($id) => $byId->get($id))->filter()->values();

            return ($q['limit'] ?? null) ? $picked->take($q['limit'])->values() : $picked;
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
            $items = ($q['filter_op'] ?? null) === 'is'
                // exact: the value itself, or any one entry of a list / tags field
                ? $items->filter(fn (CollectionItem $i) => in_array($needle, array_map(fn ($v) => mb_strtolower(trim($v)), self::values(data_get($i->data, $ff))), true))
                : $items->filter(fn (CollectionItem $i) => str_contains(mb_strtolower(self::flatText(data_get($i->data, $ff) ?? '')), $needle));
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

        return self::take($items->values(), $q);
    }

    /** The `limit` entries from the chosen end — first, last, or a random few (kept in their order). */
    private static function take(LaravelCollection $items, array $q): LaravelCollection
    {
        $n = $q['limit'] ?? null;

        return match ($q['take'] ?? 'first') {
            'last' => $n ? $items->slice(-$n)->values() : $items,
            'random' => $n
                ? $items->keys()->shuffle()->take($n)->sort()->map(fn ($k) => $items[$k])->values()
                : $items->shuffle()->values(),
            default => $n ? $items->take($n)->values() : $items,
        };
    }

    /**
     * THE reusable query: which entries of a collection match a rule —
     * search, filter, sort, then first / last / random N — as entry ids in
     * order. Used to fill a block's list (Edit panel "Add entries by rule"),
     * and by anything else that needs "the 3 newest", "a random 2 where
     * category is Men", "the last 5"…
     *
     *   CollectionQuery::select($faqs, ['filter_field' => 'category', 'filter_op' => 'is',
     *       'filter_value' => 'Men', 'take' => 'random', 'limit' => 3]);
     *
     * @return list<string>
     */
    public static function select(Collection $col, array $rule): array
    {
        // A rule always narrows — never the block's order/pick/exclude state.
        $rule = array_intersect_key($rule, array_flip(['search', 'filter_field', 'filter_value', 'filter_op', 'sort', 'field', 'dir', 'take', 'limit']));
        $rule['all'] = true;

        return self::apply($col, $rule)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    }

    /** "Showing 3 of 12 · newest first · matching “x”" */
    public static function summary(array $q, int $total, int $shown): string
    {
        if (array_key_exists('pick', $q)) {
            return $shown === 0 ? 'None chosen yet — add entries from the source' : $shown.' of '.$total.' chosen';
        }
        $parts = [$shown === $total ? 'All '.$total : 'Showing '.$shown.' of '.$total];
        $parts[] = match ($q['sort'] ?? 'manual') {
            'newest' => 'newest first',
            'oldest' => 'oldest first',
            'field' => 'by '.str_replace('_', ' ', (string) ($q['field'] ?? '')).(($q['dir'] ?? 'asc') === 'desc' ? ' (Z–A)' : ' (A–Z)'),
            default => empty($q['order']) ? 'manual order' : 'custom order',
        };
        if (($q['take'] ?? null) === 'last') {
            $parts[] = 'the last '.($q['limit'] ?? '');
        } elseif (($q['take'] ?? null) === 'random') {
            $parts[] = 'random pick (changes each visit)';
        }
        if (! empty($q['exclude'])) {
            $parts[] = count($q['exclude']).' hidden';
        }
        if (! empty($q['search'])) {
            $parts[] = 'matching “'.$q['search'].'”';
        }
        if (! empty($q['filter_field'])) {
            $parts[] = str_replace('_', ' ', $q['filter_field']).(($q['filter_op'] ?? null) === 'is' ? ' is “' : ' contains “').$q['filter_value'].'”';
        }

        return implode(' · ', $parts);
    }

    /** The distinct values a field holds across entries (list / tags entries one by one) — filter suggestions. */
    public static function fieldValues(LaravelCollection $items, string $field, int $max = 40): array
    {
        return $items->flatMap(fn (CollectionItem $i) => self::values(data_get($i->data, $field)))
            ->map(fn ($v) => trim($v))->filter(fn ($v) => $v !== '' && mb_strlen($v) <= 60)
            ->unique(fn ($v) => mb_strtolower($v))->sort(SORT_NATURAL | SORT_FLAG_CASE)->take($max)->values()->all();
    }

    /** A field's scalar values: a list / tags field gives each entry; "a, b" text stays one value. */
    private static function values(mixed $v): array
    {
        if (is_array($v)) {
            return array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v), fn ($x) => $x !== ''));
        }

        return is_scalar($v) && (string) $v !== '' ? [strip_tags((string) $v)] : [];
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
