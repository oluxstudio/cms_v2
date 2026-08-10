<?php

namespace App\Support;

/**
 * Converts stored field definitions into a frontend-friendly shape (labels, types,
 * options + a client-side `rules` hint mirroring server validation). Shared by the
 * Form schema API and the declarative module schema API.
 */
class FieldSchemaPresenter
{
    /** @param array<int,array> $fields @return array<int,array> */
    public static function fields(array $fields): array
    {
        return array_values(array_map([self::class, 'field'], $fields));
    }

    public static function field(array $f): array
    {
        return [
            'key' => $f['key'],
            'label' => $f['label'],
            'type' => $f['type'],
            'required' => (bool) ($f['required'] ?? false),
            'placeholder' => $f['placeholder'] ?? null,
            'min' => ($f['min'] ?? '') !== '' ? $f['min'] : null,
            'max' => ($f['max'] ?? '') !== '' ? $f['max'] : null,
            'options' => $f['options'] ?? [],
            'rules' => self::clientRules($f),
        ];
    }

    public static function clientRules(array $f): array
    {
        $rules = [];
        $type = $f['type'] ?? 'text';

        if ($f['required'] ?? false) {
            $rules[] = 'required';
        }

        match ($type) {
            'email' => ($rules[] = 'email'),
            'url' => ($rules[] = 'url'),
            'number' => ($rules[] = 'numeric'),
            'date' => ($rules[] = 'date'),
            'tel' => ($rules[] = 'phone'),
            'checkbox' => ($rules[] = 'boolean'),
            default => null,
        };

        if (! empty($f['min'])) {
            $rules[] = "min:{$f['min']}";
        }
        if (! empty($f['max'])) {
            $rules[] = "max:{$f['max']}";
        }

        if (in_array($type, ['select', 'radio']) && ! empty($f['options'])) {
            $rules[] = 'in:'.implode(',', $f['options']);
        }

        return $rules;
    }
}
