<?php

namespace App\Templates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Discovers the curated, first-party template APPS that render sites exactly.
 *
 * A template app is a self-contained content-driven Nuxt project under
 * `backend/templates/{key}/` (its own components + CSS + animation libs, reading the
 * standard content.json contract). The built-in `blank` template is the generic
 * `backend/nuxt-template/`. The CMS renders / previews / exports a site with the app
 * resolved from the site's template key — so the preview is literally the site.
 */
class TemplateAppRegistry
{
    public const BLANK = 'blank';

    /**
     * All available template apps, keyed by template key.
     *
     * @return array<string,array{key:string,name:string,dir:string,manifest:array,thumbnail:?string}>
     */
    public static function all(): array
    {
        $out = [self::BLANK => [
            'key' => self::BLANK,
            'name' => 'Blank',
            'dir' => base_path('nuxt-template'),
            'manifest' => [],
            'thumbnail' => null,
        ]];

        $root = base_path('templates');
        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $dir) {
                $key = basename($dir);
                // Must be a real front-end app (has a package.json) to be renderable.
                if (! File::exists("{$dir}/package.json")) {
                    continue;
                }
                $manifest = [];
                if (File::exists("{$dir}/template.json")) {
                    $manifest = json_decode(File::get("{$dir}/template.json"), true) ?: [];
                }
                $out[$key] = [
                    'key' => $key,
                    'name' => (string) ($manifest['name'] ?? Str::headline($key)),
                    'dir' => $dir,
                    'manifest' => $manifest,
                    'thumbnail' => static::thumbnailUrl($key, $manifest),
                ];
            }
        }

        return $out;
    }

    /**
     * Card thumbnail URL: a web-served convention file public/template-thumbnails/{key}.*
     * (generated via the ?shot=1 capture), else an explicit manifest thumbnail, else null.
     */
    private static function thumbnailUrl(string $key, array $manifest): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
            $rel = "template-thumbnails/{$key}.{$ext}";
            if (File::exists(public_path($rel))) {
                return asset($rel);
            }
        }

        return isset($manifest['thumbnail']) ? (string) $manifest['thumbnail'] : null;
    }

    /** A single template descriptor, or null. */
    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }

    /** Absolute path to the template's Nuxt app dir, or null if the key is unknown. */
    public static function appDir(string $key): ?string
    {
        return static::find($key)['dir'] ?? null;
    }

    /** Does this key resolve to a real, buildable template app? */
    public static function exists(string $key): bool
    {
        return static::find($key) !== null;
    }
}
