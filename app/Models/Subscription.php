<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A site's email-list subscriber (signup API + the Newsletter module).
 *
 * status: pending (awaiting double opt-in) · subscribed · unsubscribed · bounced.
 * `token` keys the subscriber's confirm / unsubscribe links.
 */
class Subscription extends Model
{
    use HasFactory;
    use HasUlids;

    public const PENDING = 'pending';

    public const SUBSCRIBED = 'subscribed';

    public const UNSUBSCRIBED = 'unsubscribed';

    public const BOUNCED = 'bounced';

    public const STATUSES = [self::PENDING, self::SUBSCRIBED, self::UNSUBSCRIBED, self::BOUNCED];

    protected $fillable = [
        'site_id',
        'email',
        'name',
        'status',
        'token',
        'ip_address',
        'source',
        'tags',
        'confirmation_sent_at',
        'confirmed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'confirmation_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscription $s) {
            $s->token ??= Str::random(40);
            $s->status ??= self::SUBSCRIBED;
        });
    }

    // ── Relationships ──────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    /** Receiving mail: confirmed / subscribed addresses. */
    public function scopeActive($query)
    {
        return $query->where('status', self::SUBSCRIBED);
    }

    public function scopeUnsubscribed($query)
    {
        return $query->where('status', self::UNSUBSCRIBED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    public function scopeTagged($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::SUBSCRIBED;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Normalise free-form tags ("vip, Members;news") to a unique list. */
    public static function cleanTags(array|string|null $tags): array
    {
        $list = is_array($tags) ? $tags : preg_split('/[,;|]/', (string) $tags);

        return collect($list)
            ->map(fn ($t) => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $t)), 0, 40))
            ->filter(fn ($t) => $t !== '')
            ->unique(fn ($t) => mb_strtolower($t))
            ->values()->all();
    }
}
