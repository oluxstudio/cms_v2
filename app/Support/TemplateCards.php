<?php

namespace App\Support;

use App\Models\SiteTemplate;
use App\Models\Template;
use App\Services\TemplateCatalog;

/**
 * One card shape for every template surface (public gallery, detail page,
 * buy page): normalises a curated first-party app or a catalog Template row.
 * Keys are 'curated:{app}' / 'catalog:{slug}' — also used in /designs/{key}
 * URLs with ':' swapped for '-' ("curated-verita").
 */
class TemplateCards
{
    /**
     * Screenshot list for the slideshow: convention folder first
     * (public/template-screenshots/{key}/*), then any URLs stored in the
     * version payload, then the single thumbnail. [] when nothing exists.
     *
     * @return list<string>
     */
    public static function screenshotsFor(?string $dirKey, array $payloadShots = [], ?string $thumbnail = null): array
    {
        if ($dirKey) {
            $dir = public_path("template-screenshots/{$dirKey}");
            if (is_dir($dir)) {
                $files = collect(glob($dir.'/*.{png,jpg,jpeg,webp}', GLOB_BRACE))->sort()->values();
                if ($files->isNotEmpty()) {
                    return $files->map(fn ($f) => asset('template-screenshots/'.$dirKey.'/'.basename($f)))->all();
                }
            }
        }
        if ($payloadShots !== []) {
            return array_values(array_filter($payloadShots, 'is_string'));
        }

        return $thumbnail ? [$thumbnail] : [];
    }

    /** Per-category fallback bullets when a template ships no designedFor list. */
    private static function designedForFallback(string $category): array
    {
        return match (strtolower($category)) {
            'salon', 'beauty' => ['Salons & barbershops taking bookings online', 'Showcasing styles, prices and the team', 'Turning Instagram followers into appointments'],
            'trades', 'business' => ['Tradespeople and local services winning work online', 'Publishing services, prices and accreditations', 'Capturing quote requests while on the tools'],
            default => ['Small businesses that want a polished site fast', 'Showcasing services and capturing enquiries', 'Looking professional without hiring a designer'],
        };
    }

    public static function fromCurated(array $t): array
    {
        // "Times added" for curated designs = saved-to-site rows bound to the app.
        $installCounts = SiteTemplate::whereNotNull('builtin_key')
            ->selectRaw('builtin_key, COUNT(*) as c')->groupBy('builtin_key')->pluck('c', 'builtin_key');

        return [
            'key' => 'curated:'.$t['key'],
            'slug' => 'curated-'.$t['key'],
            'builtin' => $t['key'],
            'name' => $t['name'],
            'description' => $t['description'],
            'category' => $t['category'],
            'accent' => $t['accent'],
            'thumbnail' => $t['thumbnail'],
            'previewUrl' => $t['previewUrl'],
            'priceLabel' => 'Free',
            'priceCents' => 0,
            'author' => (string) ($t['manifest']['author'] ?? 'Olux'),
            'rating' => null,
            'ratingCount' => 0,
            'installs' => (int) ($installCounts[$t['key']] ?? 0),
            'framework' => 'Nuxt 4 app',
            'createdAt' => (string) ($t['manifest']['createdAt'] ?? '') ?: null,
            'tags' => array_values((array) ($t['manifest']['tags'] ?? [])),
            'screenshots' => self::screenshotsFor($t['key'], [], $t['thumbnail']),
            'highlights' => array_values((array) ($t['manifest']['highlights'] ?? $t['manifest']['features'] ?? $t['manifest']['tags'] ?? [])),
            'designedFor' => array_values((array) ($t['manifest']['designedFor'] ?? [])) ?: self::designedForFallback((string) $t['category']),
            'pages' => collect($t['manifest']['pages'] ?? [])->map(fn ($p) => ['name' => $p['name'] ?? 'Page', 'url' => $p['url'] ?? '/'])->all(),
        ];
    }

    public static function fromCatalog(Template $t): array
    {
        return [
            'key' => 'catalog:'.$t->slug,
            'slug' => $t->slug,
            'builtin' => $t->builtin_key,
            'name' => $t->name,
            'description' => (string) $t->description,
            'category' => (string) $t->category,
            'accent' => $t->accent_color ?: '#6366f1',
            'thumbnail' => $t->thumbnail_url,
            'previewUrl' => $t->previewUrl(),
            'priceLabel' => $t->priceLabel(),
            'priceCents' => (int) $t->price_cents,
            'author' => $t->user?->name ?? 'Creator',
            'rating' => $t->rating_count ? round((float) $t->rating_avg, 1) : null,
            'ratingCount' => (int) $t->rating_count,
            'installs' => (int) $t->installs_count,
            'framework' => $t->builtin_key ? 'Nuxt 4 app' : 'Olux block renderer',
            'createdAt' => optional($t->published_at ?? $t->created_at)->toFormattedDateString(),
            'tags' => array_values((array) ($t->tags ?? [])),
            'screenshots' => self::screenshotsFor($t->slug, array_values((array) ($t->latestVersion?->payload['screenshots'] ?? [])), $t->thumbnail_url),
            'highlights' => array_values((array) ($t->tags ?? [])),
            'designedFor' => self::designedForFallback((string) $t->category),
            'pages' => collect($t->latestVersion?->payload['pages'] ?? [])->map(fn ($p) => ['name' => $p['name'] ?? 'Page', 'url' => $p['url'] ?? '/'])->all(),
        ];
    }

    /** Resolve a /designs/{key} URL segment to [card, ?Template]. Published only. */
    public static function resolve(string $urlKey): ?array
    {
        if (str_starts_with($urlKey, 'curated-')) {
            $t = CuratedTemplates::find(substr($urlKey, 8));

            return $t ? [self::fromCurated($t), null] : null;
        }
        $tpl = app(TemplateCatalog::class)->find($urlKey);

        return $tpl ? [self::fromCatalog($tpl), $tpl] : null;
    }
}
