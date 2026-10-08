<?php

namespace App\Models;

use App\Support\Money;
use App\Support\TemplateAccess;
use App\Support\TemplatePaths;
use App\Templates\TemplateContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
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
    use HasUlids;

    protected $fillable = [
        'uuid', 'user_id', 'creator_id', 'name', 'slug', 'description', 'short_description', 'category', 'tags', 'required_features', 'reset_collections', 'live_preview_url',
        'status', 'status_before_hide', 'visibility', 'price_cents', 'currency', 'source', 'builtin_key', 'source_repo', 'source_branch',
        'accent_color', 'gradient_class', 'thumbnail_url', 'latest_version_id',
        'installs_count', 'rating_avg', 'rating_count',
        'published_at', 'submitted_at', 'rejection_reason',
    ];

    protected $casts = [
        'tags' => 'array',
        'required_features' => 'array',
        'reset_collections' => 'array',
        'published_at' => 'datetime',
        'submitted_at' => 'datetime',
        'rating_avg' => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TemplateCreator::class, 'creator_id');
    }

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
        return Money::format((int) $this->price_cents, $this->currency ?? 'gbp', free: true);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(TemplateEntitlement::class);
    }

    /** Private templates are only for the accounts they're assigned to. */
    public function isPrivate(): bool
    {
        return $this->visibility === 'private' || $this->status === 'private';
    }

    /** What the public store, gallery and APIs may list: published AND public. */
    /**
     * A customer's own upload (Design page): always private to their account.
     * Admin store uploads are also source "upload" but carry the Olux Studio
     * creator — those can be public.
     */
    public function isAccountUpload(): bool
    {
        return $this->status === 'private' || ($this->source === 'upload' && $this->creator_id === null);
    }

    public function scopePubliclyListed($q)
    {
        return $q->where('status', 'published')->where('visibility', 'public');
    }

    /**
     * Live preview URL if a static demo exists for this template, else null.
     * Private templates get a short-lived signed token — callers must have
     * checked access (TemplateAccess) before handing this URL out.
     */
    public function previewUrl(?string $siteName = null): ?string
    {
        $key = $this->builtin_key ?: $this->slug;
        if (! $key || ! TemplatePaths::hasShell($key)) {
            return null;
        }

        return TemplatePaths::shellUrl($key).'?'.http_build_query(array_filter([
            'site' => $siteName,
            'template' => $key,
            // Same rule the preview API enforces (uploads stay token-only until published publicly).
            'pt' => TemplateAccess::isPrivateKey($key) ? TemplateAccess::previewToken($key) : null,
        ]));
    }

    /** Resolve the latest published version to a TemplateContract for applying. */
    public function toContract(): ?TemplateContract
    {
        return $this->latestVersion?->toContract();
    }
}
