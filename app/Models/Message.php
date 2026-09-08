<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasUlids;

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

    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    /** Read state is PER USER: DMs use read_at, broadcasts use message_reads rows. */
    public function isReadBy(User $user): bool
    {
        if ($this->sender_id === $user->id) {
            return true;
        }
        if ($this->recipient_id !== null) {
            return $this->recipient_id !== $user->id || $this->read_at !== null;
        }

        return $this->reads()->where('user_id', $user->id)->exists();
    }

    public function markReadFor(User $user): void
    {
        if ($this->recipient_id === $user->id && $this->read_at === null) {
            $this->update(['read_at' => now()]);
        } elseif ($this->recipient_id === null && $this->sender_id !== $user->id) {
            $this->reads()->firstOrCreate(['user_id' => $user->id], ['read_at' => now()]);
        }
    }

    /** One source of truth for the unread badge (page, rail, header). */
    public static function unreadCountFor(Site $site, User $user): int
    {
        $direct = static::where('site_id', $site->id)
            ->where('recipient_id', $user->id)->whereNull('read_at')->count();
        $broadcast = static::where('site_id', $site->id)
            ->whereNull('recipient_id')->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return $direct + $broadcast;
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
