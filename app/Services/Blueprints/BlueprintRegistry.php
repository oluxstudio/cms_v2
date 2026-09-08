<?php

namespace App\Services\Blueprints;

/**
 * Maps a business type chosen at signup ("barber", "electrician"…) to the
 * blueprint that provisions it. Types are declared by each blueprint's config.
 */
class BlueprintRegistry
{
    /** blueprint key => class */
    public const BLUEPRINTS = [
        'salon' => SalonBlueprint::class,
        'trades' => TradesBlueprint::class,
    ];

    /** All business types across blueprints: type => ['label', 'icon', 'blueprint']. */
    public static function types(): array
    {
        $out = [];
        foreach (self::BLUEPRINTS as $key => $class) {
            foreach ((array) config("blueprints.{$key}.types", []) as $type => $meta) {
                $out[$type] = $meta + ['blueprint' => $key];
            }
        }

        return $out;
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::types());
    }

    public static function label(string $type): string
    {
        return self::types()[$type]['label'] ?? ucfirst($type);
    }

    /** The blueprint instance for a business type. */
    public static function forType(string $type): Blueprint
    {
        $key = self::types()[$type]['blueprint'] ?? null;
        abort_unless($key !== null, 422, "Unknown business type: {$type}");

        return app(self::BLUEPRINTS[$key]);
    }
}
