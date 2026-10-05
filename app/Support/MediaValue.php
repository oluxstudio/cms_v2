<?php

namespace App\Support;

use App\Models\Media;

/**
 * Is a field value an asset? Recognises Assets-page media (@media/… refs and
 * /storage/… paths) and any URL/path ending in a known media extension, and
 * resolves it to something a browser can show: ['kind' => image|video|audio|document, 'url' => …].
 *
 * Template paths (/assets/images/x.png) are looked up in the site's Assets
 * library by file name — installs copy every shipped asset there — so the
 * preview uses the library copy the CMS actually serves.
 *
 * kind() picks the editor for ANY value, nested or not:
 *   media · media-list · boolean · number · long-text · text · list · rows · group · json
 */
class MediaValue
{
    private const KINDS = [
        'image' => ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif', 'bmp', 'ico'],
        'video' => ['mp4', 'webm', 'mov', 'm4v', 'ogv'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
        'document' => ['pdf'],
    ];

    /** @return array{kind: string, url: string, name: string}|null */
    public static function detect(mixed $value, ?string $siteId = null): ?array
    {
        if (! is_string($value) || ($v = trim($value)) === '' || strlen($v) > 2048 || preg_match('/\s/', $v)) {
            return null;
        }
        $url = $v;
        if (str_starts_with($v, '@media/')) {
            $url = $siteId ? Media::resolveRef($siteId, $v) : '';
            if ($url === '') {
                return null;
            }
        } elseif (! preg_match('#^(https?://|/)#i', $v)) {
            return null; // plain text, not a path or URL
        }
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        foreach (self::KINDS as $kind => $exts) {
            if (in_array($ext, $exts, true)) {
                $name = basename((string) parse_url($url, PHP_URL_PATH));
                // A template path (not already a library file) → the Assets-library copy, if there is one.
                if ($siteId && str_starts_with($url, '/') && ! str_starts_with($url, '/storage/')
                    && ($lib = Media::resolveRef($siteId, '@media/'.$name)) !== '') {
                    $url = $lib;
                }

                return ['kind' => $kind, 'url' => $url, 'name' => $name];
            }
        }

        return null;
    }

    /**
     * Several media paths kept in ONE text value, one per line (or comma-separated)
     * — e.g. a template's "images" field. Returns the paths, or null if it isn't that.
     *
     * @return list<string>|null
     */
    public static function lines(mixed $value, ?string $siteId = null): ?array
    {
        if (! is_string($value) || ! preg_match('/[\n,]/', $value)) {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value)), fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            return null;
        }
        foreach ($parts as $p) {
            if (! self::detect($p, $siteId)) {
                return null;
            }
        }

        return $parts;
    }

    /** A list whose (non-empty) entries are all media — edited as a gallery. */
    public static function isMediaList(mixed $value, ?string $siteId = null): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }
        $filled = array_filter($value, fn ($v) => $v !== '' && $v !== null);
        if ($filled === []) {
            return false;
        }
        foreach ($filled as $v) {
            if (! self::detect($v, $siteId)) {
                return false;
            }
        }

        return true;
    }

    /** Which editor/viewer a value gets (used at every nesting level). */
    public static function kind(mixed $value, ?string $siteId = null): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) || is_float($value) => 'number',
            is_array($value) && self::isMediaList($value, $siteId) => 'media-list',
            is_array($value) && $value !== [] && ! array_is_list($value) => 'group',
            is_array($value) && array_is_list($value) && collect($value)->contains(fn ($r) => is_array($r)) => 'rows',
            is_array($value) => 'list',
            self::detect($value, $siteId) !== null => 'media',
            self::lines($value, $siteId) !== null => 'media-lines',
            is_string($value) && (mb_strlen($value) > 70 || str_contains($value, "\n")) => 'long-text',
            default => 'text',
        };
    }

    /**
     * Does a field get the Assets picker even while it isn't holding media yet?
     * Media types always; legacy "url" fields only when their name says picture
     * (photo, image, logo…) — a plain link ("#", "/contact") stays a link.
     */
    public static function isMediaType(?string $type, ?string $key = null): bool
    {
        if (in_array($type, ['image', 'video', 'audio', 'file', 'media'], true)) {
            return true;
        }

        return $type === 'url' && $key !== null
            && (bool) preg_match('/(image|img|photo|picture|logo|avatar|cover|thumb|banner|icon|poster|video|audio|media)/i', $key);
    }
}
