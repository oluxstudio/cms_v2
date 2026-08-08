<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bearer token for the management API. Stored HASHED (sha256) — the raw
 * value is shown exactly once at creation. Optionally scoped to one site
 * and/or a subset of permission abilities, with an optional expiry.
 */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'site_id', 'name', 'token', 'token_preview', 'abilities', 'expires_at', 'last_used_at'];

    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Find the token row for a raw bearer value (hashed lookup). */
    public static function findByBearer(?string $bearer): ?self
    {
        return $bearer ? static::where('token', hash('sha256', $bearer))->first() : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** May this token act on the given site? (null site_id = any of the user's sites) */
    public function allowsSite(Site $site): bool
    {
        return $this->site_id === null || $this->site_id === $site->id;
    }

    /** May this token use the given permission? (null abilities = all the user has) */
    public function can(string $ability): bool
    {
        return $this->abilities === null || in_array($ability, $this->abilities, true);
    }

    public function maskedToken(): string
    {
        return ($this->token_preview ?: substr($this->token, 0, 8)).'••••••••••••••••';
    }
}
