<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Site;
use App\Support\SiteProperties;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Favicon + app icons from one square image: 32/48 (favicon), 180 (Apple
 * touch) and 192/512 (PWA) PNGs, saved into the site's Assets and listed in
 * the `site.icons` attribute. Only images already in the site's own storage
 * are read — never a remote URL (no server-side fetching of pasted links).
 */
class SiteIcons
{
    public const MIN_SIZE = 192;

    /**
     * Rebuild the icon set from $source (a /storage/... URL). Returns true when
     * icons were made, false when the image couldn't be used, null when the
     * source was cleared (icons removed).
     */
    public function generate(Site $site, string $source): ?bool
    {
        $this->forget($site);
        if (trim($source) === '') {
            return null;
        }

        $bytes = $this->localBytes(SiteProperties::imageUrl($site, $source));
        $img = $bytes !== null ? @imagecreatefromstring($bytes) : false;
        if (! $img) {
            return false;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $side = min($w, $h);
        if ($side < self::MIN_SIZE) {
            imagedestroy($img);

            return false;
        }

        $made = [];
        $stamp = Str::lower(Str::random(6));
        foreach (config('site-properties.icon_sizes') as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            // Centre-crop to a square, then scale.
            imagecopyresampled($canvas, $img, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
            ob_start();
            imagepng($canvas, null, 9);
            $png = (string) ob_get_clean();
            imagedestroy($canvas);

            $path = "media/{$site->name}/icons/favicon-{$size}-{$stamp}.png";
            Storage::disk('public')->put($path, $png);
            Media::create([
                'site_id' => $site->id,
                'name' => "favicon-{$size}.png",
                'file_type' => 'image',
                'url' => Storage::url($path),
                'size' => Media::humanSize(strlen($png)),
                'bytes' => strlen($png),
                'alt_text' => "Site icon {$size}×{$size}",
            ]);
            $made[$size] = Storage::url($path);
        }
        imagedestroy($img);

        $site->setAttr(config('site-properties.icons_attr'), json_encode($made, JSON_UNESCAPED_SLASHES));

        return true;
    }

    /** Remove the previously generated set (files, Assets rows, attribute). */
    public function forget(Site $site): void
    {
        $attr = config('site-properties.icons_attr');
        $old = json_decode((string) $site->getAttr($attr), true) ?: [];
        foreach ($old as $url) {
            $path = ltrim(Str::after((string) $url, '/storage/'), '/');
            if (str_starts_with($path, "media/{$site->name}/icons/")) {
                Storage::disk('public')->delete($path);
            }
            Media::where('site_id', $site->id)->where('url', $url)->delete();
        }
        $site->forgetAttr($attr);
    }

    /** Bytes of an image on this app's public disk, or null for anything else. */
    private function localBytes(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return null;
        }
        if (! str_starts_with($path, '/storage/')) {
            return null;
        }
        $rel = ltrim(Str::after($path, '/storage/'), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }
        $disk = Storage::disk('public');

        return $disk->exists($rel) ? $disk->get($rel) : null;
    }
}
