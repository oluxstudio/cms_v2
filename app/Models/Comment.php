<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A visitor comment on a blog post. Moderated: only `approved` show publicly. */
class Comment extends Model
{
    use HasUlids;

    protected $fillable = [
        'post_id', 'site_id', 'author_name', 'author_email', 'body', 'status',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
