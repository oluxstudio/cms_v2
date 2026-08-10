<?php

namespace App\Support;

/**
 * Shared field-schema behaviour for any model that stores a JSON `fields` array
 * (Form, Collection). A field is: key, label, type, required, placeholder, min,
 * max, options[]. Centralises validation-rule building so Forms and declarative
 * modules (Collections) validate submissions identically.
 *
 * The using model MUST expose a `fields` array attribute (e.g. cast 'array').
 */
trait HasFieldSchema
{
    /** Canonical field types supported by the schema engine. */
    public const FIELD_TYPES = [
        'text', 'email', 'tel', 'number', 'url', 'date', 'textarea', 'select', 'radio', 'checkbox',
    ];

    /**
     * Build a Laravel Validator rules array from the stored field definitions.
     */
    public function buildValidationRules(): array
    {
        $rules = [];

        foreach ($this->fields ?? [] as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';
            $required = (bool) ($field['required'] ?? false);
            $options = $field['options'] ?? [];
            $min = $field['min'] ?? null;
            $max = $field['max'] ?? null;

            $fieldRules = [$required ? 'required' : 'nullable'];

            if ($type === 'email') {
                $fieldRules[] = 'email:rfc';
            }
            if ($type === 'url') {
                $fieldRules[] = 'url';
            }
            if ($type === 'number') {
                $fieldRules[] = 'numeric';
            }
            if ($type === 'date') {
                $fieldRules[] = 'date';
            }
            if ($type === 'tel') {
                $fieldRules[] = 'regex:/^[\+\d\s\-\(\)\.]{6,25}$/';
            }

            if (in_array($type, ['select', 'radio']) && ! empty($options)) {
                $fieldRules[] = 'in:'.implode(',', $options);
            }

            if ($min !== null && $min !== '') {
                $fieldRules[] = 'min:'.$min;
            }
            if ($max !== null && $max !== '') {
                $fieldRules[] = 'max:'.$max;
            }

            if ($type === 'checkbox') {
                $fieldRules = [$required ? 'required' : 'nullable', 'boolean'];
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    /** Human-friendly error messages keyed to the field labels. */
    public function buildValidationMessages(): array
    {
        $messages = [];

        foreach ($this->fields ?? [] as $field) {
            $key = $field['key'];
            $label = $field['label'] ?? $key;

            $messages["{$key}.required"] = "{$label} is required.";
            $messages["{$key}.email"] = "{$label} must be a valid email address.";
            $messages["{$key}.url"] = "{$label} must be a valid URL.";
            $messages["{$key}.numeric"] = "{$label} must be a number.";
            $messages["{$key}.date"] = "{$label} must be a valid date.";
            $messages["{$key}.boolean"] = "{$label} must be true or false.";
            $messages["{$key}.in"] = "{$label} must be one of the allowed options.";
            $messages["{$key}.min"] = "{$label} must be at least :min characters.";
            $messages["{$key}.max"] = "{$label} may not exceed :max characters.";
            $messages["{$key}.regex"] = "{$label} format is invalid.";
        }

        return $messages;
    }

    /** Short human-readable validation summary for one field (admin views). */
    public static function fieldValidationSummary(array $field): string
    {
        $parts = [];

        if ($field['required'] ?? false) {
            $parts[] = 'Required';
        }

        match ($field['type'] ?? 'text') {
            'email' => ($parts[] = 'Valid email'),
            'url' => ($parts[] = 'Valid URL'),
            'number' => ($parts[] = 'Numeric'),
            'date' => ($parts[] = 'Date'),
            'tel' => ($parts[] = 'Phone format'),
            'checkbox' => ($parts[] = 'Boolean'),
            default => null,
        };

        if (! empty($field['min'])) {
            $parts[] = "Min: {$field['min']}";
        }
        if (! empty($field['max'])) {
            $parts[] = "Max: {$field['max']}";
        }

        if (! empty($field['options']) && is_array($field['options'])) {
            $parts[] = 'Allowed: '.implode(', ', $field['options']);
        }

        return empty($parts) ? '—' : implode(' · ', $parts);
    }
}
