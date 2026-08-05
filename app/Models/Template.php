<?php

namespace App\Models;

use App\Templates\TemplateContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog template — the marketplace's source of truth (replaces the filesystem
 * registry for browsing). Built-in templates are seeded here by `templates:sync`;
 * user-created ones land here in later phases.
 */
class Template extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'name', 'slug', 'description', 'category', 'tags',
        'status', 'price_cents', 'currency', 'source', 'builtin_key',
        'accent_color', 'gradient_class', 'thumbnail_url', 'latest_version_id',
        'installs_count', 'rating_avg', 'rating_count',
        'published_at', 'submitted_at', 'rejection_reason',
    ];

    protected $casts = [
        'tags'         => 'array',
        'published_at' => 'datetime',
        'submitted_at' => 'datetime',
        'rating_avg'   => 'float',
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(TemplateRating::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'latest_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFree(): bool
    {
        return (int) $this->price_cents === 0;
    }

    public function priceLabel(): string
    {
        return \App\Support\Money::format((int) $this->price_cents, $this->currency ?? 'gbp', free: true);
    }

    /** Live preview URL if a static demo exists for this template, else null. */
    public function previewUrl(?string $siteName = null): ?string
    {
        $key = $this->builtin_key ?: $this->slug;
        if (! is_file(public_path("vue-templates/{$key}/index.html"))) {
            return null;
        }
        $url = url("vue-templates/{$key}/index.html");

        return $siteName ? $url.'?site='.urlencode($siteName) : $url;
    }

    /** Resolve the latest published version to a TemplateContract for applying. */
    public function toContract(): ?TemplateContract
    {
        return $this->latestVersion?->toContract();
    }
}
