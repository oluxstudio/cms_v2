<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Admin-edited settings layered over config/*.php. Each row targets one
 * config path; mode "replace" swaps the value, "merge" deep-merges it into
 * the file's array. apply() runs at boot, so every config() reader sees the
 * edits. Long-running workers pick changes up on their next restart.
 */
class ConfigOverlay
{
    private const CACHE_KEY = 'platform-settings:v1';

    /** File values captured before the first overlay, per path. */
    private static array $defaults = [];

    public static function apply(): void
    {
        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, fn () => PlatformSetting::pluck('data', 'key')->all());
        } catch (Throwable) {
            return; // no table yet — file config stands
        }

        foreach ($rows as $path => $data) {
            $data = is_string($data) ? (json_decode($data, true) ?: []) : (array) $data;
            if (! array_key_exists($path, self::$defaults)) {
                self::$defaults[$path] = config($path);
            }
            $value = $data['value'] ?? null;
            $base = self::$defaults[$path];
            if (($data['mode'] ?? 'replace') === 'merge' && is_array($base) && is_array($value)) {
                config([$path => array_replace_recursive($base, $value)]);
            } else {
                config([$path => $value]);
            }
        }
    }

    /** The stored override (not the merged result), or null. */
    public static function stored(string $path): mixed
    {
        return PlatformSetting::where('key', $path)->value('data')['value'] ?? null;
    }

    /** The value from the config file, before any overlay. */
    public static function fileValue(string $path): mixed
    {
        return array_key_exists($path, self::$defaults) ? self::$defaults[$path] : config($path);
    }

    public static function set(string $path, mixed $value, string $mode = 'replace'): void
    {
        if (! array_key_exists($path, self::$defaults)) {
            self::$defaults[$path] = config($path);
        }
        PlatformSetting::updateOrCreate(['key' => $path], ['data' => ['value' => $value, 'mode' => $mode]]);
        self::refresh();
    }

    public static function forget(string $path): void
    {
        PlatformSetting::where('key', $path)->delete();
        if (array_key_exists($path, self::$defaults)) {
            config([$path => self::$defaults[$path]]);
        }
        self::refresh();
    }

    public static function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::apply();
    }
}
