<?php

namespace App\Models;

use App\Templates\ArrayTemplate;
use App\Templates\TemplateContract;
use App\Templates\TemplateRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A template installed to a site (from the Marketplace). Built-in installs point
 * at a TemplateRegistry key; custom installs (uploaded .zip) carry their theme +
 * pages in `payload`. Either resolves to a TemplateContract for applying.
 */
class SiteTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'template_id', 'template_version_id', 'source', 'builtin_key',
        'name', 'description', 'category', 'accent_color', 'gradient_class',
        'payload', 'thumbnail_path', 'applied_at', 'previous_theme', 'previous_state',
    ];

    protected $casts = [
        'payload' => 'array',
        'previous_theme' => 'array',
        'previous_state' => 'array',
        'applied_at' => 'datetime',
    ];

    /** Whether this template is currently applied (in use) on the site. */
    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function isBuiltin(): bool
    {
        return $this->source === 'builtin';
    }

    /** The underlying built-in template definition, if this is a built-in install. */
    public function builtin(): ?TemplateContract
    {
        return $this->builtin_key ? TemplateRegistry::find($this->builtin_key) : null;
    }

    /**
     * Resolve to a TemplateContract the installer can apply. Priority:
     *   pinned catalog version → built-in registry → inline payload (legacy custom).
     */
    public function toContract(): ?TemplateContract
    {
        if ($this->template_version_id && $this->templateVersion) {
            return $this->templateVersion->toContract();
        }

        if ($this->isBuiltin()) {
            return $this->builtin();
        }

        $payload = $this->payload ?? [];

        return new ArrayTemplate(
            meta: [
                'key' => 'site-template-'.$this->id,
                'name' => $this->name,
                'description' => (string) $this->description,
                'category' => (string) $this->category,
                'accentColor' => $this->accent_color ?: '#6366f1',
                'gradientClass' => $this->gradient_class ?: 'from-slate-400 to-slate-600',
                'tags' => $payload['tags'] ?? [],
                'features' => $payload['features'] ?? [],
                'version' => $payload['version'] ?? '1.0.0',
                'author' => $payload['author'] ?? 'Imported',
                'createdAt' => $payload['createdAt'] ?? $this->created_at?->toDateString() ?? '2026-01-01',
            ],
            pages: $payload['pages'] ?? [],
            theme: $payload['theme'] ?? [],
            thumbnailUrl: $this->thumbnailUrl(),
        );
    }

    /** Public URL of this template's screenshot, or null (card falls back to gradient). */
    public function thumbnailUrl(): ?string
    {
        if ($this->template_id && $this->template?->thumbnail_url) {
            return $this->template->thumbnail_url;
        }

        if ($this->thumbnail_path && is_file(public_path($this->thumbnail_path))) {
            return asset($this->thumbnail_path);
        }

        // Built-ins fall back to the convention file public/template-thumbnails/{key}.{ext}
        if ($this->builtin_key) {
            foreach (['png', 'jpg', 'jpeg', 'webp', 'avif', 'svg'] as $ext) {
                $rel = "template-thumbnails/{$this->builtin_key}.{$ext}";
                if (is_file(public_path($rel))) {
                    return asset($rel);
                }
            }
        }

        return null;
    }

    /** The page count for display, resolved from the underlying definition. */
    public function pageCount(): int
    {
        if ($this->template_version_id && $this->templateVersion) {
            return count($this->templateVersion->payload['pages'] ?? []);
        }
        if ($this->isBuiltin()) {
            return count($this->builtin()?->pages() ?? []);
        }

        return count($this->payload['pages'] ?? []);
    }
}
