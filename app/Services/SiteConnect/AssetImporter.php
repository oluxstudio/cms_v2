<?php

namespace App\Services\SiteConnect;

use App\Models\Media;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a remote asset (an image URL found during ingestion) into the site's
 * media library, so committed components reference a CMS-hosted copy instead of
 * hot-linking the client site. Returns a portable `@media/…` ref the page.json
 * generator resolves to the stored URL. Best-effort: any failure (SSRF-blocked
 * host, unreachable URL, oversized file) leaves the original value untouched.
 */
class AssetImporter
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private SsrfGuard $guard) {}

    /**
     * Import an image-node VALUE (editor/API-supplied) into the media library.
     * Root-relative paths ('/assets/x.jpg') are resolved against the site's
     * client_url; existing @media refs and CMS-own URLs pass through untouched.
     * Returns the @media ref, or the original value when import isn't possible.
     */
    public function importNodeValue(Site $site, string $value): string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '@media/') || str_starts_with($value, 'data:')) {
            return $value;
        }
        // CMS-hosted already (media library path or our own origin) — leave it.
        if (str_starts_with($value, '/storage/') || str_starts_with($value, rtrim(config('app.url'), '/').'/')) {
            return $value;
        }
        if (str_starts_with($value, '/')) {
            $base = rtrim((string) $site->getAttr('client_url', ''), '/');
            if ($base === '') {
                return $value; // relative with no client site configured — nothing to fetch
            }
            $value = $base.$value;
        }

        // Editor/API-driven: any public host is fetchable (the SSRF guard still
        // rejects private/reserved IPs via the empty-allowlist own-host path).
        return $this->importRef($site, $value);
    }

    /**
     * @param  array<int,string>  $allowedHosts  hosts imports may be fetched from
     *                                           (the ingested page's host); when empty the URL's own
     *                                           host is used, so only the private-IP check applies.
     */
    public function importRef(Site $site, string $url, array $allowedHosts = []): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return $url; // relative path, data: URI, or already an @media ref
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! $this->guard->allows($url, $allowedHosts ?: [$host])) {
            return $url;
        }

        $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'asset';

        // Already imported (matched the way Media::resolveRef matches) → reuse
        // — but only when the file is actually STORED here. A match that just
        // registers an external URL gets upgraded to a local copy below.
        $existing = Media::where('site_id', $site->id)->get()
            ->first(fn (Media $m) => mb_strtolower($m->name) === mb_strtolower($name)
                || mb_strtolower(basename($m->url)) === mb_strtolower($name));
        if ($existing && ! Str::startsWith($existing->url, ['http://', 'https://'])) {
            return $existing->ref();
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(['max_redirects' => 3] + $this->guard->pinnedOptions($url))
                ->get($url);
        } catch (\Throwable $e) {
            Log::info('site-connect: asset fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return $url;
        }
        $body = $response->body();
        if (! $response->successful() || $body === '' || strlen($body) > self::MAX_BYTES) {
            return $url;
        }

        $disk = Storage::disk('public');
        $path = 'media/'.$site->name.'/'.$name;
        if ($disk->exists($path)) { // same basename, different site file — keep both
            $info = pathinfo($name);
            $name = $info['filename'].'-'.Str::lower(Str::random(4)).(isset($info['extension']) ? '.'.$info['extension'] : '');
            $path = 'media/'.$site->name.'/'.$name;
        }
        $disk->put($path, $body);

        $attrs = [
            'file_type' => Media::guessType($response->header('Content-Type'), $name),
            'url' => Storage::url($path),
            'size' => Media::humanSize(strlen($body)),
            'bytes' => strlen($body),
        ];
        if ($existing) {
            // Upgrade the external registration in place — same asset identity.
            $existing->update($attrs);

            return $existing->ref();
        }
        $media = Media::create($attrs + ['site_id' => $site->id, 'name' => $name, 'alt_text' => null]);

        return $media->ref();
    }

    /**
     * Import a LOCAL image file (e.g. a template's shipped asset) into the
     * site's media library. Same storage layout + dedupe as importRef().
     * Returns the @media ref, or null when the file can't be imported.
     */
    public function importLocal(Site $site, string $absolutePath, ?string $name = null): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }
        $name ??= basename($absolutePath);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        // Every template asset type enters the media library — images, and
        // (with their own generous caps) fonts, video, audio and documents.
        $caps = [
            'png' => self::MAX_BYTES, 'jpg' => self::MAX_BYTES, 'jpeg' => self::MAX_BYTES,
            'gif' => self::MAX_BYTES, 'webp' => self::MAX_BYTES, 'svg' => self::MAX_BYTES,
            'avif' => self::MAX_BYTES, 'ico' => self::MAX_BYTES,
            'woff' => 2 * 1024 * 1024, 'woff2' => 2 * 1024 * 1024, 'ttf' => 4 * 1024 * 1024, 'otf' => 4 * 1024 * 1024,
            'mp4' => 200 * 1024 * 1024, 'webm' => 200 * 1024 * 1024, 'ogv' => 200 * 1024 * 1024,
            'mp3' => 40 * 1024 * 1024, 'wav' => 80 * 1024 * 1024, 'm4a' => 40 * 1024 * 1024, 'ogg' => 40 * 1024 * 1024,
            'pdf' => 25 * 1024 * 1024,
        ];
        if (! isset($caps[$ext]) || filesize($absolutePath) > $caps[$ext]) {
            return null;
        }

        // Already imported (same matching as resolveRef) and stored here → reuse.
        $existing = Media::where('site_id', $site->id)->get()
            ->first(fn (Media $m) => mb_strtolower($m->name) === mb_strtolower($name)
                || mb_strtolower(basename($m->url)) === mb_strtolower($name));
        if ($existing && ! Str::startsWith($existing->url, ['http://', 'https://'])) {
            return $existing->ref();
        }

        $body = (string) file_get_contents($absolutePath);
        $disk = Storage::disk('public');
        $path = 'media/'.$site->name.'/'.$name;
        if ($disk->exists($path)) {
            $info = pathinfo($name);
            $name = $info['filename'].'-'.Str::lower(Str::random(4)).(isset($info['extension']) ? '.'.$info['extension'] : '');
            $path = 'media/'.$site->name.'/'.$name;
        }
        $disk->put($path, $body);

        $attrs = [
            'file_type' => Media::guessType(mime_content_type($absolutePath) ?: null, $name),
            'url' => Storage::url($path),
            'size' => Media::humanSize(strlen($body)),
            'bytes' => strlen($body),
        ];
        if ($existing) {
            $existing->update($attrs);

            return $existing->ref();
        }

        // Correct label: "pastor-2.jpg" → "Pastor 2"; the parent folder gives
        // context ("Gallery · Pastor 2") so the Assets page groups sensibly.
        // The stored FILE keeps its real name (in the url), so @media/<file>
        // refs still resolve via basename matching.
        $label = Str::headline(pathinfo($name, PATHINFO_FILENAME));
        $folder = basename(dirname($absolutePath));
        if ($folder !== '' && ! in_array(strtolower($folder), ['assets', 'images', 'public', '.'], true)) {
            $label = Str::headline($folder).' · '.$label;
        }

        return Media::create($attrs + ['site_id' => $site->id, 'name' => $label, 'alt_text' => $label])->ref();
    }
}
