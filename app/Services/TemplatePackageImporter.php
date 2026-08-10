<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Imports a packaged template (.zip) and installs it to a site.
 *
 * Expected archive layout:
 *   template.json            (required) — { name, description, category, accentColor,
 *                                           gradientClass, tags[], features[], version,
 *                                           author, theme{}, pages[] }
 *   thumbnail.(png|svg|jpg|jpeg|webp)   (optional) — card screenshot
 *   assets/…                            (optional) — images referenced by the pages
 */
class TemplatePackageImporter
{
    private const MANIFEST = 'template.json';

    private const THUMB_EXTS = ['png', 'svg', 'jpg', 'jpeg', 'webp', 'avif'];

    public function __construct(private TemplateSecurity $security) {}

    /**
     * @throws RuntimeException on an invalid/corrupt/unsafe package.
     */
    public function import(Site $site, string $zipPath): SiteTemplate
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the .zip file.');
        }

        try {
            $this->security->inspectZip($zip); // size/count/path-traversal/file-type guards

            $manifestRaw = $this->readManifest($zip);
            $manifest = json_decode($manifestRaw, true);
            if (! is_array($manifest)) {
                throw new RuntimeException('template.json is not valid JSON.');
            }

            $name = trim((string) ($manifest['name'] ?? ''));
            $pages = $manifest['pages'] ?? null;
            if ($name === '') {
                throw new RuntimeException('template.json is missing a "name".');
            }
            if (! is_array($pages) || $pages === []) {
                throw new RuntimeException('template.json must contain a non-empty "pages" array.');
            }

            // Validate/clean pages + css against the catalog before storing.
            $pages = $this->security->sanitizePages($pages);

            $slug = Str::slug($name) ?: 'template';

            $thumbPath = $this->extractThumbnail($zip, $site, $slug);
            $this->extractAssets($zip, $slug);

            // Bake extracted asset URLs into the pages, and capture the template's
            // stylesheet so the imported template shows its images + skin.
            $pages = $this->rewriteAssetUrls($pages, $slug);
            $css = $this->extractCss($zip);

            return $site->installedTemplates()->create([
                'source' => 'custom',
                'builtin_key' => null,
                'name' => $name,
                'description' => (string) ($manifest['description'] ?? ''),
                'category' => (string) ($manifest['category'] ?? 'Custom'),
                'accent_color' => (string) ($manifest['accentColor'] ?? '#6366f1'),
                'gradient_class' => (string) ($manifest['gradientClass'] ?? 'from-slate-400 to-slate-600'),
                'thumbnail_path' => $thumbPath,
                'payload' => [
                    'theme' => is_array($manifest['theme'] ?? null) ? $manifest['theme'] : [],
                    'css' => $css,
                    'pages' => $pages,
                    'tags' => (array) ($manifest['tags'] ?? []),
                    'features' => (array) ($manifest['features'] ?? []),
                    'version' => (string) ($manifest['version'] ?? '1.0.0'),
                    'author' => (string) ($manifest['author'] ?? 'Imported'),
                    'createdAt' => (string) ($manifest['createdAt'] ?? now()->toDateString()),
                ],
            ]);
        } finally {
            $zip->close();
        }
    }

    /** Read template.json from the archive root (tolerates a single wrapping folder). */
    private function readManifest(ZipArchive $zip): string
    {
        $raw = $zip->getFromName(self::MANIFEST);
        if ($raw !== false) {
            return $raw;
        }
        // Tolerate "my-template/template.json"
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry !== false && preg_match('#(^|/)'.preg_quote(self::MANIFEST, '#').'$#', $entry)) {
                $raw = $zip->getFromIndex($i);
                if ($raw !== false) {
                    return $raw;
                }
            }
        }

        throw new RuntimeException('The .zip is missing template.json.');
    }

    /** Pull the first thumbnail.* out of the archive into public/template-thumbnails. */
    private function extractThumbnail(ZipArchive $zip, Site $site, string $slug): ?string
    {
        foreach (self::THUMB_EXTS as $ext) {
            $raw = $this->firstBySuffix($zip, "thumbnail.{$ext}");
            if ($raw === null) {
                continue;
            }
            $dir = public_path('template-thumbnails');
            File::ensureDirectoryExists($dir);
            $rel = "template-thumbnails/site{$site->id}-{$slug}-".Str::random(6).".{$ext}";
            File::put(public_path($rel), $raw);

            return $rel;
        }

        return null;
    }

    /** Extract an optional assets/ folder verbatim into public/template-assets/{slug}. */
    private function extractAssets(ZipArchive $zip, string $slug): void
    {
        $base = "template-assets/{$slug}";
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || ! preg_match('#(^|/)assets/(.+)$#', $entry, $m)) {
                continue;
            }
            $inner = $m[2];
            if ($inner === '' || str_ends_with($entry, '/')) {
                continue;
            }
            $raw = $zip->getFromIndex($i);
            if ($raw === false) {
                continue;
            }
            if ($this->security->isSvg($inner)) {
                $raw = $this->security->sanitizeSvg($raw); // strip script/handlers from SVG
            }
            $rel = "{$base}/{$inner}";
            File::ensureDirectoryExists(dirname(public_path($rel)));
            File::put(public_path($rel), $raw);
        }
    }

    /** Read css/template.css (sanitised) from the archive, or '' if absent. */
    private function extractCss(ZipArchive $zip): string
    {
        $raw = $this->firstBySuffix($zip, 'css/template.css') ?? $this->firstBySuffix($zip, 'template.css');

        return $raw ? $this->security->sanitizeCss($raw) : '';
    }

    /**
     * Rewrite node values like "assets/hero.png" to the public URL of the file we
     * extracted to public/template-assets/{slug}, so images resolve on the site.
     */
    private function rewriteAssetUrls(array $pages, string $slug): array
    {
        $base = "template-assets/{$slug}/";

        foreach ($pages as &$page) {
            if (! isset($page['blocks']) || ! is_array($page['blocks'])) {
                continue;
            }
            foreach ($page['blocks'] as &$block) {
                if (! isset($block['nodes']) || ! is_array($block['nodes'])) {
                    continue;
                }
                foreach ($block['nodes'] as &$node) {
                    $v = (string) ($node['value'] ?? '');
                    if ($v !== '' && preg_match('#^assets/(.+)$#', $v, $m)) {
                        $node['value'] = asset($base.$m[1]);
                    }
                }
                unset($node);
            }
            unset($block);
        }
        unset($page);

        return $pages;
    }

    /** First archive entry whose name ends with $suffix (root or wrapped), or null. */
    private function firstBySuffix(ZipArchive $zip, string $suffix): ?string
    {
        $raw = $zip->getFromName($suffix);
        if ($raw !== false) {
            return $raw;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry !== false && str_ends_with(strtolower($entry), strtolower($suffix))) {
                $raw = $zip->getFromIndex($i);

                return $raw === false ? null : $raw;
            }
        }

        return null;
    }
}
