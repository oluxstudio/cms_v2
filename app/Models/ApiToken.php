<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Bearer token for the management API. Stored HASHED (sha256) — the raw
 * value is shown exactly once at creation. Optionally scoped to one site
 * and/or a subset of permission abilities, with an optional expiry.
 */
class ApiToken extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'site_id', 'name', 'token', 'token_preview', 'abilities', 'expires_at', 'last_used_at', 'plain'];

    protected $hidden = ['token', 'plain'];

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

    /**
     * Mint a Site Connect key: site-scoped, connect abilities only, with an
     * ENCRYPTED retrievable copy — connect keys are public-by-design (they
     * ship in the client's HTML), so the build can fetch them on demand.
     * Returns [$model, $raw].
     */
    public static function mintConnect(Site $site, string $name = 'Site Connect'): array
    {
        $raw = 'olx_live_'.Str::random(48);
        $token = static::create([
            'user_id' => $site->user_id,
            'site_id' => $site->id,
            'name' => $name,
            'token' => hash('sha256', $raw),
            'token_preview' => substr($raw, 0, 12),
            'abilities' => config('site_connect.abilities', ['connect:ingest', 'content:read']),
            'plain' => Crypt::encryptString($raw),
        ]);

        return [$token, $raw];
    }

    /** The raw connect key, when this token was minted retrievable. */
    public function plainValue(): ?string
    {
        return $this->plain ? Crypt::decryptString($this->plain) : null;
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
