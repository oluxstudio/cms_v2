<?php

namespace App\Livewire\Concerns;

use App\Templates\TemplateAppRegistry;
use Livewire\Attributes\Computed;

/**
 * Shared "browse curated template apps" behaviour for the Templates + Marketplace
 * pages. A curated template renders the site EXACTLY (its own app), so its preview
 * is what publishes. Expects the host component to expose a `$site` property.
 */
trait InteractsWithCuratedTemplates
{
    /** Curated template apps available to browse (excludes the generic "blank"). */
    #[Computed]
    public function curated(): array
    {
        $installedKeys = $this->site->installedTemplates()->pluck('builtin_key')->filter()->all();

        return collect(TemplateAppRegistry::all())
            ->reject(fn ($t) => $t['key'] === TemplateAppRegistry::BLANK)
            ->map(fn ($t) => [
                'key' => $t['key'],
                'name' => $t['name'],
                'description' => (string) ($t['manifest']['description'] ?? ''),
                'category' => (string) ($t['manifest']['category'] ?? 'Template'),
                'accent' => (string) ($t['manifest']['theme']['accent'] ?? $t['manifest']['accentColor'] ?? '#6366f1'),
                'thumbnail' => $t['thumbnail'],
                'installed' => in_array($t['key'], $installedKeys, true),
                'previewUrl' => url("nuxt-preview/{$t['key']}/").'?template='.urlencode($t['key']),
            ])
            ->values()
            ->all();
    }

    /** Live-preview a curated template's REAL app (exact) before using it. */
    public function previewCurated(string $key): void
    {
        if (! TemplateAppRegistry::exists($key)) {
            return;
        }
        $this->dispatch('open-preview', url: url("nuxt-preview/{$key}/").'?template='.urlencode($key));
    }

    /** Use a curated template: scaffold editable content + bind the site to its app. */
    public function useCurated(string $key)
    {
        $t = TemplateAppRegistry::find($key);
        if (! $t || $key === TemplateAppRegistry::BLANK) {
            return null;
        }
        $m = $t['manifest'];

        $st = $this->site->installedTemplates()->create([
            'source' => 'custom',
            'builtin_key' => $key, // binds the render app via TemplateInstaller::resolveAppKey()
            'name' => $t['name'],
            'description' => (string) ($m['description'] ?? ''),
            'category' => (string) ($m['category'] ?? 'Template'),
            'accent_color' => (string) ($m['theme']['accent'] ?? $m['accentColor'] ?? '#6366f1'),
            'gradient_class' => (string) ($m['gradientClass'] ?? 'from-slate-400 to-slate-600'),
            'payload' => [
                'theme' => $m['theme'] ?? [],
                'css' => (string) ($m['css'] ?? ''),
                // Keep image nodes — they point at the template's bundled assets
                // (assets/images/*) and stay editable in the builder.
                'pages' => $m['pages'] ?? [],
                'version' => (string) ($m['version'] ?? '1.0.0'),
                'author' => (string) ($m['author'] ?? 'Curated'),
            ],
        ]);

        // Legacy wireframe scaffolding removed — the site just binds the record;
        // pages are built with blocks.

        return $this->redirect(url($this->site->name.'/configure'), navigate: true);
    }
}
