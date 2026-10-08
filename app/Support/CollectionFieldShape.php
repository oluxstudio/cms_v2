<?php

namespace App\Support;

/**
 * Collection fields declared with a type (a template's @olux-field, or the
 * Collections "Edit fields" panel): values shaped for the editors, and
 * required fields checked before an entry is saved.
 */
class CollectionFieldShape
{
    /** Types whose value is a list (edited as a gallery / tags / list). */
    public const LIST_TYPES = ['images', 'tags', 'list'];

    /**
     * Text stored under a field now declared as a list becomes an array, so
     * the structured editor can edit it: "a.png\nb.png" → [a.png, b.png];
     * tags also split on commas ("Nuxt, Vue" → [Nuxt, Vue]).
     */
    /** Field types edited as ONE piece of text — never as a list or JSON. */
    public const TEXT_TYPES = ['text', 'textarea', 'textarea2', 'textarea6', 'textarea10', 'textarea15', 'url', 'email', 'tel', 'number', 'date', 'datetime', 'image', 'media', 'slug'];

    public static function isTextType(?string $type): bool
    {
        return in_array((string) $type, self::TEXT_TYPES, true);
    }

    public static function shape(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            $key = $f['key'] ?? $f['name'] ?? '';
            $type = $f['type'] ?? 'text';
            // A text field still holding an older list (e.g. content stored as
            // paragraphs) edits as text: textareas get blank-line paragraphs.
            if ($key !== '' && self::isTextType($type) && is_array($data[$key] ?? null) && array_is_list($data[$key])
                && collect($data[$key])->every(fn ($v) => is_scalar($v) || $v === null)) {
                $data[$key] = implode(str_starts_with($type, 'textarea') ? "\n\n" : ', ', array_filter(array_map('strval', $data[$key]), 'strlen'));

                continue;
            }
            if ($key === '' || ! in_array($type, self::LIST_TYPES, true) || ! is_string($data[$key] ?? null)) {
                continue;
            }
            $split = $type === 'tags' ? '/[\n,]+/' : '/\n+/';
            $data[$key] = array_values(array_filter(array_map('trim', preg_split($split, $data[$key])), 'strlen'));
        }

        // Older "yes" / "no" values under a toggle or checkbox edit as on / off.
        return self::coerce($data, array_values(array_filter($fields, fn ($f) => \App\Models\Collection::isBooleanType($f['type'] ?? null))));
    }

    /**
     * Stored values converted to their field's (new) type, so entries keep
     * their meaning when a type changes: "yes" / "no" / "1" / "on" → true /
     * false for a toggle or checkbox, "12" → 12 for a number. Anything that
     * doesn't convert cleanly is left as it is.
     */
    public static function coerce(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            $key = $f['key'] ?? $f['name'] ?? '';
            if ($key === '' || ! array_key_exists($key, $data)) {
                continue;
            }
            $v = $data[$key];
            $type = $f['type'] ?? 'text';
            if (\App\Models\Collection::isBooleanType($type) && ! is_bool($v)) {
                $b = $v === '' || $v === null ? false : filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $data[$key] = $b ?? $v;
            } elseif (in_array($type, ['number', 'slider'], true) && is_string($v) && is_numeric(trim($v))) {
                $data[$key] = 0 + trim($v);
            }
        }

        return $data;
    }

    /** Every entry of $collection (deleted ones too) with $fields' values converted — see coerce(). Quiet saves. */
    public static function coerceEntries(\App\Models\Collection $collection, array $fields): void
    {
        $collection->items()->withTrashed()->get()->each(function ($item) use ($fields) {
            $data = self::coerce((array) ($item->data ?? []), $fields);
            if ($data !== $item->data) {
                $item->data = $data;
                $item->saveQuietly();
            }
        });
    }

    /** Labels of required fields that are empty in $data. */
    public static function missingRequired(array $data, array $fields): array
    {
        return collect($fields)->filter(fn ($f) => ! empty($f['required']))
            ->filter(function ($f) use ($data) {
                $v = $data[$f['key'] ?? $f['name'] ?? ''] ?? null;

                return $v === null || $v === [] || (is_string($v) && trim($v) === '');
            })
            ->map(fn ($f) => $f['label'] ?? $f['key'] ?? '')->values()->all();
    }
}
