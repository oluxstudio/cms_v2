<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Templates\TemplateContract;
use App\Templates\TemplatePackage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes one template contract into the catalog (`templates` row + its
 * version), publishing package assets to the templates disk. templates:sync
 * runs it for every first-party package; uploads run it for one private one.
 */
class TemplateCatalogWriter
{
    /**
     * @param  array  $overrides  column values that win over the defaults (status, source, user_id…)
     * @param  Template|null  $into  write into this row instead of looking it up by slug (new versions)
     */
    public function upsert(TemplateContract $contract, array $overrides = [], ?Template $into = null): Template
    {
        $slug = $contract->key();

        // 1. Publish assets (packages only) → disk; map "assets/x" → absolute URL.
        $assetMap = $this->publishAssets($contract, $slug);

        // 2. Bake asset URLs into the pages.
        $pages = $this->rewritePages($contract->pages(), $assetMap);

        $manifest = [
            'name' => $contract->name(),
            'description' => $contract->description(),
            'category' => $contract->category(),
            'accentColor' => $contract->accentColor(),
            'gradientClass' => $contract->gradientClass(),
            'author' => $contract->author(),
            'tags' => $contract->tags(),
            'features' => $contract->features(),
            'createdAt' => $contract->createdAt(),
        ];

        // 3. Upsert the catalog row (preserve uuid on update).
        $template = $into ?? Template::firstOrNew(['slug' => $slug]);
        if (! $template->exists) {
            $template->uuid = (string) Str::uuid();
        }
        $template->fill(array_merge([
            'name' => $contract->name(),
            'description' => $contract->description(),
            'category' => $contract->category(),
            'tags' => $contract->tags(),
            'status' => $template->status === 'archived' ? 'archived' : 'published',
            'source' => 'builtin',
            'builtin_key' => $slug,
            'accent_color' => $contract->accentColor(),
            'gradient_class' => $contract->gradientClass(),
            'thumbnail_url' => method_exists($contract, 'thumbnail') ? $contract->thumbnail() : null,
            'published_at' => $template->published_at ?? now(),
        ], $overrides))->save();

        // 4. Upsert the version + point latest at it.
        $this->writeVersion($template, $contract, $contract->version(), $manifest, $pages);

        return $template->fresh();
    }

    /**
     * A NEW VERSION of a template that's already in the catalog, from its
     * contract — without touching the catalog row's admin-edited fields
     * (name, tagline, thumbnail, status…). Used by the built-in "Update
     * template" button. $extraManifest is merged in (e.g. source_hash).
     */
    public function publishVersion(Template $template, TemplateContract $contract, string $label, array $extraManifest = []): TemplateVersion
    {
        $assetMap = $this->publishAssets($contract, $contract->key());
        $pages = $this->rewritePages($contract->pages(), $assetMap);
        $manifest = [
            'name' => $contract->name(),
            'description' => $contract->description(),
            'category' => $contract->category(),
            'accentColor' => $contract->accentColor(),
            'gradientClass' => $contract->gradientClass(),
            'author' => $contract->author(),
            'tags' => $contract->tags(),
            'features' => $contract->features(),
            'createdAt' => $contract->createdAt(),
        ] + $extraManifest;

        return $this->writeVersion($template, $contract, $label, $manifest, $pages);
    }

    private function writeVersion(Template $template, TemplateContract $contract, string $label, array $manifest, array $pages): TemplateVersion
    {
        $version = $template->versions()->updateOrCreate(
            ['version' => $label],
            [
                'manifest' => $manifest,
                'payload' => [
                    'theme' => method_exists($contract, 'theme') ? ($contract->theme() ?: []) : [],
                    'fonts' => method_exists($contract, 'fonts') ? $contract->fonts() : [],
                    'css' => method_exists($contract, 'css') ? $contract->css() : '',
                    'pages' => $pages,
                ],
                'status' => 'published',
            ],
        );
        $template->update(['latest_version_id' => $version->id]);

        return $version;
    }

    /** Copy a package's assets to the disk; return [ "assets/rel" => absolute URL ]. */
    private function publishAssets(TemplateContract $contract, string $slug): array
    {
        if (! $contract instanceof TemplatePackage || ! ($dir = $contract->assetsDir())) {
            return [];
        }

        $disk = Storage::disk(config('templates.disk'));
        $map = [];
        foreach (File::allFiles($dir) as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());
            $key = "{$slug}/assets/{$rel}";
            $disk->put($key, File::get($file->getPathname()));
            $map["assets/{$rel}"] = $disk->url($key);
        }

        return $map;
    }

    /** Replace "assets/x" node values with their published URLs. */
    private function rewritePages(array $pages, array $map): array
    {
        if (! $map) {
            return $pages;
        }

        foreach ($pages as &$page) {
            if (empty($page['blocks'])) {
                continue;
            }
            foreach ($page['blocks'] as &$block) {
                if (empty($block['nodes'])) {
                    continue;
                }
                foreach ($block['nodes'] as &$node) {
                    $val = $node['value'] ?? null;
                    if (is_string($val) && isset($map[$val])) {
                        $node['value'] = $map[$val];
                    }
                }
                unset($node);
            }
            unset($block);
        }
        unset($page);

        return $pages;
    }
}
