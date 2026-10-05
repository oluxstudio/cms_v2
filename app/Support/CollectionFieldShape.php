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
    public static function shape(array $data, array $fields): array
    {
        foreach ($fields as $f) {
            $key = $f['key'] ?? $f['name'] ?? '';
            $type = $f['type'] ?? 'text';
            if ($key === '' || ! in_array($type, self::LIST_TYPES, true) || ! is_string($data[$key] ?? null)) {
                continue;
            }
            $split = $type === 'tags' ? '/[\n,]+/' : '/\n+/';
            $data[$key] = array_values(array_filter(array_map('trim', preg_split($split, $data[$key])), 'strlen'));
        }

        return $data;
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
