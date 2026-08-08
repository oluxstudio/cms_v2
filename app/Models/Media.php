<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Media extends Model
{
    use HasFactory;
    use HasUlids;

    public const TYPES = ['image', 'video', 'document', 'font'];

    protected $fillable = ['site_id', 'site_template_id', 'name', 'file_type', 'url', 'size', 'alt_text'];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /** Absolute, browser-usable URL — handles both external URLs and stored paths. */
    public function publicUrl(): string
    {
        if (Str::startsWith($this->url, ['http://', 'https://'])) {
            return $this->url;
        }

        return url($this->url);
    }

    /** Portable reference for this file, usable in any content field. */
    public function ref(): string
    {
        return '@media/'.basename($this->url);
    }

    /** Per-request cache for resolveRef lookups: siteId => [needle => url]. */
    private static array $refCache = [];

    /**
     * Resolve a "@media/{filename}" reference (also matches the item's display
     * name) to the stored URL, per site + case-insensitive. Non-references pass
     * through untouched; unresolved references return '' so renderers fall back
     * to their placeholder instead of a broken image.
     */
    public static function resolveRef(string $siteId, string $value): string
    {
        if (! str_starts_with($value, '@media/')) {
            return $value;
        }
        $needle = mb_strtolower(trim(substr($value, 7)));
        if ($needle === '') {
            return '';
        }

        if (! array_key_exists($needle, self::$refCache[$siteId] ?? [])) {
            $match = static::where('site_id', $siteId)
                ->get(['name', 'url'])
                ->first(fn ($m) => mb_strtolower(basename($m->url)) === $needle
                    || mb_strtolower($m->name) === $needle);
            self::$refCache[$siteId][$needle] = $match?->url ?? '';
        }

        return self::$refCache[$siteId][$needle];
    }

    /** Map a MIME type to one of our coarse media buckets. */
    public static function typeFromMime(?string $mime): string
    {
        return match (true) {
            $mime !== null && str_starts_with($mime, 'image/') => 'image',
            $mime !== null && str_starts_with($mime, 'video/') => 'video',
            default => 'document',
        };
    }

    /** Human-readable size from a byte count. */
    public static function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $i ? 1 : 0).' '.$units[$i];
    }
}
