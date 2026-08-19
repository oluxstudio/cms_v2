<?php

namespace App\Services\SiteConnect;

use App\Models\PageIngestion;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Builds the transformed-site export (Part 5): each committed page's HTML with
 * data-olx-* attributes baked in AND the current content pre-filled (so the
 * static HTML is correct for SEO), plus the connect.js hydrate script. The
 * client uploads these files to replace their originals; connect.js then patches
 * only diffs when a newer page.json version is published.
 *
 * Returns the path to a generated .zip on the local disk.
 */
class SiteExporter
{
    public function __construct(private HtmlAnnotator $annotator) {}

    /** @return array{path:string, pages:int} absolute zip path + page count */
    public function export(Site $site): array
    {
        $ingestions = PageIngestion::where('site_id', $site->id)
            ->whereNotNull('page_id')
            ->where('status', PageIngestion::STATUS_COMMITTED)
            ->with(['sections', 'page'])
            ->get();

        $dir = storage_path('app/site-connect-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $zipPath = $dir.'/'.$site->name.'.zip';

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $count = 0;
        foreach ($ingestions as $ingestion) {
            $zip->addFromString($this->filename($ingestion), $this->pageHtml($site, $ingestion));
            $count++;
        }
        $zip->addFromString('README.md', $this->readme($site, $count));
        $zip->close();

        return ['path' => $zipPath, 'pages' => $count];
    }

    private function pageHtml(Site $site, PageIngestion $ingestion): string
    {
        $page = $ingestion->page;
        $version = (int) ($page->page_json_version ?: 1);
        $title = e($page->name ?? $site->name);
        $meta = $ingestion->meta ?? [];
        $description = e($meta['description'] ?? '');

        $sections = $ingestion->sections
            ->filter(fn ($s) => $s->committed_ref_id)
            ->sortBy('position')
            ->map(fn ($s) => $this->annotator->annotate($s))
            ->implode("\n");

        $script = $this->scriptTag($site);
        $styles = (string) $ingestion->styles;

        return <<<HTML
        <!doctype html>
        <html lang="en" data-olx-version="{$version}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title}</title>
            <meta name="description" content="{$description}">
            <style>{$styles}</style>
        </head>
        <body>
        {$sections}
        {$script}
        </body>
        </html>
        HTML;
    }

    private function scriptTag(Site $site): string
    {
        $src = rtrim((string) config('app.url'), '/').'/connect.js';

        // The raw token is never stored (hashed at rest) — the owner pastes their
        // Site Connect key here. Mint one with: php artisan connect:token {site}
        return '<script src="'.e($src).'" data-site-name="'.e($site->name).'" '
            .'data-site-token="PASTE_YOUR_SITE_CONNECT_TOKEN" defer></script>';
    }

    private function filename(PageIngestion $ingestion): string
    {
        $url = trim((string) $ingestion->page?->url, '/');
        $slug = $url === '' ? 'index' : Str::slug(str_replace('/', '-', $url));

        return $slug.'.html';
    }

    private function readme(Site $site, int $count): string
    {
        return <<<MD
        # {$site->name} — Site Connect export

        {$count} transformed page(s). Each page is your original markup with:

        - `data-olx-id` on every managed section and `data-olx-field` on editable
          nodes (so the CMS can hydrate it),
        - the **current content baked in** (correct for SEO on first paint),
        - `data-olx-version` on `<html>` (connect.js only patches when a newer
          `page.json` version is published),
        - the `connect.js` hydrate script.

        ## To go live
        1. Mint a key: `php artisan connect:token {$site->name}`
        2. Replace `PASTE_YOUR_SITE_CONNECT_TOKEN` in each file with that key.
        3. Upload these files to replace your existing pages.

        Edits in the CMS + Publish now update the live site with no redeploy.
        MD;
    }
}
