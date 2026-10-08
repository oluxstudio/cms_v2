<?php

namespace App\Support;

use App\Models\Collection;
use Illuminate\Support\Str;

/**
 * The choices of a select / radio collection field, as value => label:
 * its own fixed `options`, or — with `optionsFrom` — the entries of another
 * collection on the same site (e.g. a sermon's Series picks from the
 * "Sermon Series" collection, storing each entry's slug, showing its name),
 * so new entries there appear as choices without editing the field.
 *
 *   optionsFrom: {collection: 'sermon-series', value: 'slug', label: 'name'}
 */
class CollectionFieldOptions
{
    /** @return array<string, string> */
    public static function for(?string $siteId, array $field): array
    {
        $from = $field['optionsFrom'] ?? null;
        if (is_array($from) && filled($from['collection'] ?? null)) {
            if (! $siteId) {
                return [];
            }
            $want = Str::slug(Str::kebab((string) $from['collection']));
            $col = Collection::where('site_id', $siteId)->get()
                ->first(fn ($c) => Str::slug((string) ($c->slug ?: $c->name)) === $want || Str::slug((string) $c->name) === $want);
            if (! $col) {
                return [];
            }
            $valueKey = (string) ($from['value'] ?? 'slug');
            $labelKey = (string) ($from['label'] ?? 'name');

            return $col->items()->orderBy('position')->orderBy('created_at')->get()
                ->mapWithKeys(function ($i) use ($valueKey, $labelKey) {
                    $d = (array) ($i->data ?? []);
                    $label = trim((string) ($d[$labelKey] ?? ''));
                    // An entry without a slug is identified by its name, slugified
                    // (templates derive the same slug when reading the entry).
                    $value = trim((string) ($d[$valueKey] ?? '')) ?: ($valueKey === 'slug' && $label !== '' ? Str::slug($label) : '');

                    return $value === '' ? [] : [$value => $label ?: $value];
                })->all();
        }

        return collect((array) ($field['options'] ?? []))
            ->mapWithKeys(fn ($o) => [(string) $o => (string) $o])->all();
    }

    /** Parse `from=collection:value:label` (value defaults to slug, label to name). */
    public static function parseFrom(string $spec): ?array
    {
        $parts = explode(':', $spec);
        if (! preg_match('/^[A-Za-z][\w-]*$/', $parts[0] ?? '')) {
            return null;
        }

        return ['collection' => $parts[0], 'value' => ($parts[1] ?? '') ?: 'slug', 'label' => ($parts[2] ?? '') ?: 'name'];
    }
}
