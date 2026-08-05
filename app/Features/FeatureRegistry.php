<?php

namespace App\Features;

class FeatureRegistry
{
    /** All registered features, keyed by feature key. */
    public static function all(): array
    {
        return config('features', []);
    }

    /** A single feature definition, or null if unknown. */
    public static function get(string $key): ?array
    {
        return config("features.$key");
    }

    /** All valid feature keys. */
    public static function keys(): array
    {
        return array_keys(static::all());
    }

    public static function exists(string $key): bool
    {
        return static::get($key) !== null;
    }

    /** Default config values derived from a feature's settings schema. */
    public static function defaults(string $key): array
    {
        $settings = static::get($key)['settings'] ?? [];

        return collect($settings)
            ->mapWithKeys(fn ($field, $name) => [$name => $field['default'] ?? null])
            ->all();
    }
}
