<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Creator publishing pipeline (P3): turn an uploaded .zip into a creator-owned
 * catalog Template (draft) + immutable TemplateVersion, then move it through
 * moderation (draft → in_review → published | rejected). All package content is
 * run through TemplateSecurity before it touches the catalog or disk.
 */
class TemplatePublisher
{
    private const MANIFEST = 'template.json';

    private const THUMBS = ['thumbnail.png', 'thumbnail.jpg', 'thumbnail.jpeg', 'thumbnail.webp', 'thumbnail.svg'];

    public function __construct(private TemplateSecurity $security) {}

    /** Whether a user may approve/reject submissions. */
    public function isModerator(?User $user): bool
    {
        return $user && in_array($user->email, (array) config('templates.moderators'), true);
    }

    /**
     * Create a new draft catalog template owned by $creator from an uploaded zip.
     *
     * @param  array{name?:string,category?:string,price_cents?:int}  $meta
     */
    public function publishFromZip(User $creator, string $zipPath, array $meta = []): Template
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the .zip file.');
        }

        try {
            $this->security->inspectZip($zip);

            $manifest = json_decode((string) $this->readEntry($zip, self::MANIFEST), true);
            if (! is_array($manifest)) {
                throw new RuntimeException('Missing or invalid template.json.');
            }

            $name = trim((string) ($meta['name'] ?? $manifest['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('A template name is required.');
            }
            if (empty($manifest['pages']) || ! is_array($manifest['pages'])) {
                throw new RuntimeException('template.json must contain a "pages" array.');
            }

            $pages = $this->security->sanitizePages($manifest['pages']);

            return DB::transaction(function () use ($creator, $zip, $manifest, $meta, $name, $pages) {
                $uuid = (string) Str::uuid();

                // Publish assets (sanitised) → disk, baking absolute URLs into pages.
                [$pages, $assetMap] = $this->publishAssets($zip, $uuid, $pages);
                $thumbUrl = $this->publishThumbnail($zip, $uuid);

                $template = Template::create([
                    'uuid' => $uuid,
                    'user_id' => $creator->id,
                    'name' => $name,
                    'slug' => $this->uniqueSlug($name),
                    'description' => $this->security->sanitizePlain($manifest['description'] ?? ''),
                    'category' => $this->security->sanitizePlain($meta['category'] ?? $manifest['category'] ?? 'Custom'),
                    'tags' => array_slice((array) ($manifest['tags'] ?? []), 0, 12),
                    'status' => 'draft',
                    'price_cents' => max(0, (int) ($meta['price_cents'] ?? 0)),
                    'currency' => 'usd',
                    'source' => 'custom',
                    'accent_color' => $this->security->sanitizePlain($manifest['accentColor'] ?? '#6366f1'),
                    'gradient_class' => $this->security->sanitizePlain($manifest['gradientClass'] ?? 'from-slate-400 to-slate-600'),
                    'thumbnail_url' => $thumbUrl,
                ]);

                $version = $this->makeVersion($template, '1.0.0', $manifest, $pages);
                $template->update(['latest_version_id' => $version->id]);

                return $template;
            });
        } finally {
            $zip->close();
        }
    }

    /** Add a new version to an existing template (re-uploads require re-review). */
    public function newVersion(Template $template, string $zipPath): TemplateVersion
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the .zip file.');
        }

        try {
            $this->security->inspectZip($zip);
            $manifest = json_decode((string) $this->readEntry($zip, self::MANIFEST), true) ?: [];
            $pages = $this->security->sanitizePages($manifest['pages'] ?? []);

            return DB::transaction(function () use ($template, $zip, $manifest, $pages) {
                [$pages] = $this->publishAssets($zip, $template->uuid, $pages);
                $next = $this->bumpVersion($template->versions()->max('version') ?: '1.0.0');
                $version = $this->makeVersion($template, $next, $manifest, $pages);
                $template->update(['latest_version_id' => $version->id, 'status' => 'draft', 'submitted_at' => null]);

                return $version;
            });
        } finally {
            $zip->close();
        }
    }

    // ── Moderation lifecycle ──
    public function submit(Template $t): void
    {
        if (in_array($t->status, ['draft', 'rejected'], true)) {
            $t->update(['status' => 'in_review', 'submitted_at' => now(), 'rejection_reason' => null]);
        }
    }

    public function approve(Template $t): void
    {
        $t->update(['status' => 'published', 'published_at' => now(), 'rejection_reason' => null]);
    }

    public function reject(Template $t, string $reason): void
    {
        $t->update(['status' => 'rejected', 'rejection_reason' => mb_substr($reason, 0, 500)]);
    }

    // ── internals ──

    private function makeVersion(Template $t, string $version, array $manifest, array $pages): TemplateVersion
    {
        return $t->versions()->create([
            'version' => $version,
            'manifest' => [
                'name' => $t->name,
                'description' => $t->description,
                'category' => $t->category,
                'accentColor' => $t->accent_color,
                'gradientClass' => $t->gradient_class,
                'tags' => $t->tags,
                'author' => $t->user?->name ?? 'Creator',
            ],
            'payload' => [
                'theme' => is_array($manifest['theme'] ?? null) ? $manifest['theme'] : [],
                'fonts' => is_array($manifest['fonts'] ?? null) ? $manifest['fonts'] : [],
                'css' => $this->security->sanitizeCss($manifest['css'] ?? ''),
                'pages' => $pages,
            ],
            'status' => 'published',
        ]);
    }

    /**
     * Extract assets/* to the templates disk (sanitising SVG) and rewrite matching
     * "assets/x" node values to their absolute URLs.
     *
     * @return array{0:array,1:array<string,string>}
     */
    private function publishAssets(ZipArchive $zip, string $uuid, array $pages): array
    {
        $disk = Storage::disk(config('templates.disk'));
        $map = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || ! preg_match('#(^|/)assets/(.+)$#', $entry, $m) || str_ends_with($entry, '/')) {
                continue;
            }
            $rel = $m[2];
            $data = $zip->getFromIndex($i);
            if ($data === false) {
                continue;
            }
            if ($this->security->isSvg($rel)) {
                $data = $this->security->sanitizeSvg($data);
            }
            $key = "{$uuid}/assets/{$rel}";
            $disk->put($key, $data);
            $map["assets/{$rel}"] = $disk->url($key);
        }

        return [$this->rewritePages($pages, $map), $map];
    }

    private function publishThumbnail(ZipArchive $zip, string $uuid): ?string
    {
        foreach (self::THUMBS as $thumb) {
            $data = $this->readEntry($zip, $thumb);
            if ($data === null) {
                continue;
            }
            $ext = strtolower(pathinfo($thumb, PATHINFO_EXTENSION));
            if ($ext === 'svg') {
                $data = $this->security->sanitizeSvg($data);
            }
            $disk = Storage::disk(config('templates.disk'));
            $key = "{$uuid}/thumbnail.{$ext}";
            $disk->put($key, $data);

            return $disk->url($key);
        }

        return null;
    }

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
                    if (isset($node['value'], $map[$node['value']])) {
                        $node['value'] = $map[$node['value']];
                    }
                }
                unset($node);
            }
            unset($block);
        }
        unset($page);

        return $pages;
    }

    /** Read an archive entry by exact name or wrapped (folder/<name>); null if absent. */
    private function readEntry(ZipArchive $zip, string $name): ?string
    {
        $raw = $zip->getFromName($name);
        if ($raw !== false) {
            return $raw;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry !== false && preg_match('#(^|/)'.preg_quote($name, '#').'$#', $entry)) {
                $raw = $zip->getFromIndex($i);

                return $raw === false ? null : $raw;
            }
        }

        return null;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $i = 2;
        while (Template::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function bumpVersion(string $v): string
    {
        $parts = array_map('intval', explode('.', $v) + [0, 0, 0]);
        $parts[2]++;

        return implode('.', array_slice($parts, 0, 3));
    }
}
