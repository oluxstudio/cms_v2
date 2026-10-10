<?php

namespace App\Modules\Reviews\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer review of a site's business (separate from store ProductReviews). */
class Review extends Model
{
    use HasUlids;

    public const STATUSES = ['pending', 'published', 'hidden'];

    public const SOURCES = ['on_site' => 'On site', 'request' => 'Request', 'manual' => 'Manual', 'import' => 'Import'];

    protected $fillable = [
        'site_id', 'request_id', 'name', 'email', 'rating', 'title', 'body', 'photo',
        'status', 'source', 'featured', 'reply_body', 'replied_at', 'ip_address', 'ip_hash', 'published_at',
    ];

    protected $hidden = ['email', 'ip_address', 'ip_hash'];

    protected $casts = [
        'rating' => 'integer',
        'featured' => 'boolean',
        'replied_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReviewRequest::class, 'request_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst((string) $this->source);
    }
}
