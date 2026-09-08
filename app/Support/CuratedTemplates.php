<?php

namespace App\Support;

use App\Templates\TemplateAppRegistry;

/**
 * The first-party template apps as PUBLIC gallery data — no site context, so
 * both the guest gallery and the per-site pages can share one source.
 */
class CuratedTemplates
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return collect(TemplateAppRegistry::all())
            ->reject(fn ($t) => $t['key'] === TemplateAppRegistry::BLANK)
            ->map(fn ($t) => [
                'key' => $t['key'],
                'name' => $t['name'],
                'description' => (string) ($t['manifest']['description'] ?? ''),
                'category' => (string) ($t['manifest']['category'] ?? 'Template'),
                'accent' => (string) ($t['manifest']['theme']['accent'] ?? $t['manifest']['accentColor'] ?? '#6366f1'),
                'thumbnail' => $t['thumbnail'],
                'manifest' => $t['manifest'],
                'previewUrl' => is_file(public_path("nuxt-preview/{$t['key']}/index.html"))
                    ? url("nuxt-preview/{$t['key']}/").'?template='.urlencode($t['key'])
                    : null,
            ])
            ->values()
            ->all();
    }

    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }
}
