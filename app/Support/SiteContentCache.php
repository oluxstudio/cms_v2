<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Versioned cache of each site's public content payload. A site's version is
 * bumped on any content change, which retires every cached copy at once
 * (old keys simply expire). Cache failures never break a request: the
 * payload is then built fresh.
 */
class SiteContentCache
{
    private static ?Repository $store = null;

    public static function store(): Repository
    {
        if (self::$store) {
            return self::$store;
        }
        try {
            $store = Cache::store(config('content_cache.store', 'redis'));
            $store->get('site-content:ping'); // fail fast if unreachable

            return self::$store = $store;
        } catch (Throwable) {
            return self::$store = Cache::store();
        }
    }

    public static function version(string $siteId): string
    {
        try {
            return (string) (self::store()->get("site-content-v:{$siteId}") ?? '0');
        } catch (Throwable) {
            return '0';
        }
    }

    /** Retire every cached copy of this site's content. */
    public static function bump(?string $siteId): void
    {
        if (! $siteId) {
            return;
        }
        try {
            self::store()->forever("site-content-v:{$siteId}", Str::lower(Str::random(10)));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** The cached payload for ($siteId, $variant), built by $build on a miss. */
    public static function remember(string $siteId, string $variant, callable $build): array
    {
        if (! config('content_cache.enabled', true)) {
            return $build();
        }
        $key = 'site-content:'.$siteId.':'.self::version($siteId).':'.md5($variant);
        try {
            return self::store()->remember($key, (int) config('content_cache.ttl', 900), $build);
        } catch (Throwable $e) {
            report($e);

            return $build();
        }
    }

    /** ETag for a site's current content (+ variant). */
    public static function etag(string $siteId, string $variant): string
    {
        return '"'.md5($siteId.'|'.self::version($siteId).'|'.$variant).'"';
    }

    /** Tests / long-running workers: forget the resolved store. */
    public static function reset(): void
    {
        self::$store = null;
    }
}
