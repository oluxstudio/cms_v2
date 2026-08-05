<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['site_id', 'sender_id', 'recipient_id', 'body', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * RBAC: messages on a site that a user may read —
     * broadcasts (no recipient), messages to them, or messages they sent.
     */
    public function scopeVisibleTo(Builder $q, Site $site, User $user): Builder
    {
        return $q->where('site_id', $site->id)
            ->where(fn ($q) => $q
                ->whereNull('recipient_id')
                ->orWhere('recipient_id', $user->id)
                ->orWhere('sender_id', $user->id));
    }
}
