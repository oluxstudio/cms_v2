<?php

namespace App\Services\SiteConnect;

use App\Models\Page;
use App\Models\SiteConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Publishes a page's `page.json`: bumps the version, generates the document,
 * writes it to the configured disk (local `public` in dev, R2 in prod) under a
 * tenant-prefixed path, records the path/version/time on the page, flips the
 * site connection to `hydrate`, and purges the CDN cache.
 *
 * Generation is deliberately separate (PageJsonGenerator) so a preview/read can
 * render the exact same shape without a write.
 */
class PageJsonPublisher
{
    public function __construct(private PageJsonGenerator $generator) {}

    /**
     * @return array{path:string, url:string, version:int, disk:string}
     */
    public function publish(Page $page): array
    {
        $version = ((int) $page->page_json_version) + 1;
        $document = $this->generator->generate($page, $version);

        $disk = config('site_connect.disk', 'public');
        $path = $this->pathFor($page, $document['pageData']['slug']);

        $written = Storage::disk($disk)->put(
            $path,
            json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // put() returns false instead of throwing (e.g. a permission problem on
        // the disk). Failing loudly here beats bumping the version while clients
        // keep hydrating from a stale file.
        if (! $written) {
            throw new \RuntimeException(
                "site-connect: failed writing page.json to disk '{$disk}' at '{$path}' — check permissions."
            );
        }

        $page->forceFill([
            'page_json_version' => $version,
            'page_json_generated_at' => now(),
            'page_json_path' => $path,
        ])->save();

        $this->markConnectionPublished($page);
        $this->purgeCache($disk, $path);

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'version' => $version,
            'disk' => $disk,
        ];
    }

    /** Tenant-prefixed disk path from the config template. */
    public function pathFor(Page $page, ?string $slug = null): string
    {
        $slug ??= $this->generator->generate($page)['pageData']['slug'];

        return Str::of(config('site_connect.path_template', 'tenants/{site}/pages/{slug}.json'))
            ->replace('{site}', $page->site_id)
            ->replace('{slug}', $slug)
            ->value();
    }

    private function markConnectionPublished(Page $page): void
    {
        $connection = SiteConnection::firstOrNew(['site_id' => $page->site_id]);
        $connection->mode = SiteConnection::MODE_HYDRATE;
        $connection->last_published_at = now();
        $connection->save();
    }

    /**
     * Cloudflare cache purge for the just-written JSON. Real purge lands with
     * the R2 wiring in a later stage; for now it's a logged no-op so the publish
     * flow is complete and observable.
     */
    private function purgeCache(string $disk, string $path): void
    {
        Log::info('site-connect: page.json published', ['disk' => $disk, 'path' => $path]);
    }
}
