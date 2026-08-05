<?php

namespace App\Access;

/**
 * Reads the permission matrix in config/permissions.php — the single source
 * of truth for RBAC (mirrors how FeatureRegistry fronts config/features.php).
 */
class Permissions
{
    /** Grouped catalog: group label => [key => human label]. */
    public static function groups(): array
    {
        return config('permissions.groups', []);
    }

    /** Flat catalog: key => human label. */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()) ?: [[]]);
    }

    /** Every valid permission key. */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** Permission required for an admin-page segment (null = any member). */
    public static function forSegment(string $segment): ?string
    {
        return config("permissions.nav.{$segment}");
    }

    /** Default role templates: slug => [name, description, permissions]. */
    public static function defaultRoles(): array
    {
        return config('permissions.roles', []);
    }
}
