<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Templates\TemplateContract;
use App\Templates\TemplatePackage;
use App\Templates\TemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seed/refresh the DB catalog (`templates` + `template_versions`) from the built-in
 * registry (class templates + on-disk packages). Idempotent — safe to re-run after
 * editing a package. Publishes package assets to the configured templates disk and
 * bakes absolute asset URLs into the version payload.
 */
class SyncTemplateCatalog extends Command
{
    protected $signature = 'templates:sync';
    protected $description = 'Seed/refresh the template catalog from built-in templates & packages';

    public function handle(): int
    {
        $disk = Storage::disk(config('templates.disk'));

        foreach (TemplateRegistry::all() as $contract) {
            $slug = $contract->key();

            // 1. Publish assets (packages only) → disk; map "assets/x" → absolute URL.
            $assetMap = $this->publishAssets($contract, $slug, $disk);

            // 2. Bake asset URLs into the pages.
            $pages = $this->rewritePages($contract->pages(), $assetMap);

            $manifest = [
                'name'          => $contract->name(),
                'description'   => $contract->description(),
                'category'      => $contract->category(),
                'accentColor'   => $contract->accentColor(),
                'gradientClass' => $contract->gradientClass(),
                'author'        => $contract->author(),
                'tags'          => $contract->tags(),
                'features'      => $contract->features(),
                'createdAt'     => $contract->createdAt(),
            ];

            // 3. Upsert the catalog row (preserve uuid on update).
            $template = Template::firstOrNew(['slug' => $slug]);
            if (! $template->exists) {
                $template->uuid = (string) Str::uuid();
            }
            $template->fill([
                'name'           => $contract->name(),
                'description'    => $contract->description(),
                'category'       => $contract->category(),
                'tags'           => $contract->tags(),
                'status'         => 'published',
                'source'         => 'builtin',
                'builtin_key'    => $slug,
                'accent_color'   => $contract->accentColor(),
                'gradient_class' => $contract->gradientClass(),
                'thumbnail_url'  => method_exists($contract, 'thumbnail') ? $contract->thumbnail() : null,
                'published_at'   => $template->published_at ?? now(),
            ])->save();

            // 4. Upsert the version + point latest at it.
            $version = $template->versions()->updateOrCreate(
                ['version' => $contract->version()],
                [
                    'manifest' => $manifest,
                    'payload'  => [
                        'theme' => method_exists($contract, 'theme') ? ($contract->theme() ?: []) : [],
                        'fonts' => method_exists($contract, 'fonts') ? $contract->fonts() : [],
                        'css'   => method_exists($contract, 'css') ? $contract->css() : '',
                        'pages' => $pages,
                    ],
                    'status' => 'published',
                ],
            );
            $template->update(['latest_version_id' => $version->id]);

            $this->line("  ✓ {$contract->name()}  ({$slug} v{$contract->version()}, ".count($pages).' pages)');
        }

        $this->info('Template catalog synced.');

        return self::SUCCESS;
    }

    /** Copy a package's assets to the disk; return [ "assets/rel" => absolute URL ]. */
    private function publishAssets(TemplateContract $contract, string $slug, $disk): array
    {
        if (! $contract instanceof TemplatePackage || ! ($dir = $contract->assetsDir())) {
            return [];
        }

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
