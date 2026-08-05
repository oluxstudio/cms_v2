<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A site blog post (Posts content module) with simple visit/engagement counters. */
class Post extends Model
{
    protected $fillable = [
        'site_id', 'user_id', 'title', 'slug', 'excerpt', 'body', 'cover_image',
        'status', 'published_at', 'views', 'likes', 'comments',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Engagement = reactions + comments (visits tracked separately as views). */
    public function engagement(): int
    {
        return (int) $this->likes + (int) $this->comments;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** A slug unique within the site, derived from the title. */
    public static function uniqueSlug(int $siteId, string $title): string
    {
        $base = Str::slug(Str::limit($title, 60, '')) ?: 'post';
        $slug = $base;
        $i = 2;
        while (static::where('site_id', $siteId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
