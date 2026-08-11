<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A site blog post (Posts content module) with simple visit/engagement counters. */
class Post extends Model
{
    use HasUlids;

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

    /**
     * Comment records. The relation is deliberately NOT named `comments` — that
     * name is the cached approved-count column (`$post->comments`), and a
     * same-named relation would shadow the column whenever it isn't loaded.
     */
    public function commentThread(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Canonical API shape — shared by the posts list + detail endpoints.
     *
     * @param  bool  $withBody  include the full HTML body (detail view)
     * @param  bool  $withComments  embed approved comments + comments_count (detail)
     */
    public function toApiArray(bool $withBody = false, bool $withComments = false): array
    {
        $out = [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'cover_image' => $this->cover_image,
            'author' => $this->author?->name,
            'published_at' => $this->published_at?->toIso8601String(),
            'views' => (int) $this->views,
            'likes' => (int) $this->likes,
            // List views keep `comments` as the (cached) approved count for
            // back-compat; detail views replace it with the array below.
            'comments' => (int) $this->comments,
        ];

        if ($withBody) {
            $out['body'] = (string) $this->body;
        }

        if ($withComments) {
            $out['comments_count'] = (int) $this->comments;
            $out['comments'] = $this->commentThread()->approved()->latest()->get()
                ->map(fn (Comment $c) => $c->toApiArray())->all();
        }

        return $out;
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
    public static function uniqueSlug(string $siteId, string $title): string
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
