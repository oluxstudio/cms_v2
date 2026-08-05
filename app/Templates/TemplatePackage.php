<?php

namespace App\Templates;

use Illuminate\Support\Facades\File;

/**
 * A TemplateContract backed by an on-disk split-file package (see
 * resources/templates/{key}/). The package separates concerns into:
 *
 *   template.json        — manifest (key, name, meta, page order, thumbnail)
 *   tokens/colors.json   — colour theme variables
 *   tokens/sizes.json    — radius / base font-size
 *   fonts/fonts.json     — font families to load
 *   css/template.css     — template-specific CSS layered on install
 *   assets/…             — placeholder images
 *   pages/{slug}.json    — one file per page (blocks)
 *   playground.html      — bundled design sandbox
 *
 * Built-in packages are discovered by TemplateRegistry; uploaded ones are unpacked
 * to the same shape by the importer (Phase 2).
 */
class TemplatePackage implements TemplateContract
{
    private array $manifest;

    public function __construct(private string $dir)
    {
        $this->manifest = $this->readJson('template.json') ?: [];
    }

    /** Build packages for every resources/templates/{key} dir with a manifest. */
    public static function discover(): array
    {
        $base = resource_path('templates');
        if (! File::isDirectory($base)) {
            return [];
        }

        $packages = [];
        foreach (File::directories($base) as $dir) {
            if (File::exists($dir.'/template.json')) {
                $packages[] = new self($dir);
            }
        }

        return $packages;
    }

    private function m(string $key, mixed $default = ''): mixed
    {
        return $this->manifest[$key] ?? $default;
    }

    private function readJson(string $relative): ?array
    {
        $path = $this->dir.'/'.$relative;

        return File::exists($path) ? (json_decode(File::get($path), true) ?: null) : null;
    }

    // ── TemplateContract metadata ──
    public function key(): string           { return (string) ($this->m('key') ?: basename($this->dir)); }
    public function name(): string          { return (string) $this->m('name', $this->key()); }
    public function description(): string   { return (string) $this->m('description', ''); }
    public function category(): string      { return (string) $this->m('category', 'Custom'); }
    public function gradientClass(): string { return (string) $this->m('gradientClass', 'from-slate-400 to-slate-600'); }
    public function accentColor(): string   { return (string) $this->m('accentColor', '#6366f1'); }
    public function author(): string        { return (string) $this->m('author', 'Olux Studio'); }
    public function version(): string       { return (string) $this->m('version', '1.0.0'); }
    public function createdAt(): string     { return (string) $this->m('createdAt', '2026-01-01'); }
    public function tags(): array           { return (array) $this->m('tags', []); }
    public function features(): array       { return (array) $this->m('features', []); }

    /** Page defs, read from pages/{slug}.json in the manifest's declared order. */
    public function pages(): array
    {
        $order = (array) $this->m('pages', []);
        if (! $order) {
            // Fall back to every file in pages/.
            $order = collect(File::glob($this->dir.'/pages/*.json'))
                ->map(fn ($p) => pathinfo($p, PATHINFO_FILENAME))->all();
        }

        $pages = [];
        foreach ($order as $slug) {
            $def = $this->readJson("pages/{$slug}.json");
            if (is_array($def)) {
                $pages[] = $def;
            }
        }

        return $pages;
    }

    /**
     * Layout definitions (layouts/*.json) — the wrappers every page is based on.
     * Each: ['name' => …, 'blocks' => [ …block defs…, ['type' => 'content'], … ]].
     * Keyed by layout slug; 'default' is the one pages reference implicitly.
     *
     * @return array<string,array{name:string,blocks:array}>
     */
    public function layouts(): array
    {
        $out = [];
        foreach (File::glob($this->dir.'/layouts/*.json') as $file) {
            $def = $this->readJson('layouts/'.basename($file));
            if (is_array($def) && ! empty($def['blocks'])) {
                $out[pathinfo($file, PATHINFO_FILENAME)] = $def;
            }
        }

        return $out;
    }

    /** Theme values merged from the colour + size tokens and the primary font. */
    public function theme(): array
    {
        $colors = $this->readJson('tokens/colors.json') ?: [];
        $sizes  = $this->readJson('tokens/sizes.json') ?: [];
        $fonts  = $this->readJson('fonts/fonts.json') ?: [];

        $theme = array_merge($colors, $sizes);
        if (! empty($fonts['primary']['family'])) {
            $theme['font'] = $fonts['primary']['family'];
        }

        return $theme;
    }

    /** Card thumbnail — the public convention file, else null. */
    public function thumbnail(): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp', 'avif', 'svg'] as $ext) {
            $rel = "template-thumbnails/{$this->key()}.{$ext}";
            if (is_file(public_path($rel))) {
                return asset($rel);
            }
        }

        return null;
    }

    /** The Nuxt app that renders sites made from this template ('' = generic blank). */
    public function renderer(): string        { return (string) $this->m('renderer', ''); }

    // ── Package resources (consumed by the install lifecycle, Phase 2) ──
    public function dir(): string            { return $this->dir; }
    public function fonts(): array           { return $this->readJson('fonts/fonts.json') ?: []; }
    public function css(): string            { return File::exists($this->dir.'/css/template.css') ? File::get($this->dir.'/css/template.css') : ''; }
    public function assetsDir(): ?string     { return File::isDirectory($this->dir.'/assets') ? $this->dir.'/assets' : null; }
    public function playgroundPath(): ?string { return File::exists($this->dir.'/playground.html') ? $this->dir.'/playground.html' : null; }
}
