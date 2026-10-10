<?php

namespace App\Modules\Memberships;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Hashed member credentials: `magic` = an emailed sign-in link (30 min),
 * `session` = the bearer token template sites keep for the member (90 days,
 * sliding). Only the sha256 of a token is ever stored.
 */
class MemberToken extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'member_id', 'kind', 'token_hash', 'return_url', 'next', 'expires_at', 'used_at', 'last_used_at'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'last_used_at' => 'datetime'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /** @return array{0: string, 1: self} raw token + stored row */
    public static function issue(Member $member, string $kind, \DateTimeInterface $expires, array $extra = []): array
    {
        $raw = ($kind === 'session' ? 'mbr_' : 'mlk_').Str::random(48);
        $row = static::create([
            'site_id' => $member->site_id, 'member_id' => $member->id, 'kind' => $kind,
            'token_hash' => static::hash($raw), 'expires_at' => $expires,
        ] + $extra);

        return [$raw, $row];
    }

    /** A live token of this kind for the site, or null. */
    public static function findLive(string $siteId, string $kind, ?string $raw): ?self
    {
        if (! is_string($raw) || strlen($raw) < 20) {
            return null;
        }

        return static::where('site_id', $siteId)->where('kind', $kind)
            ->where('token_hash', static::hash($raw))
            ->where('expires_at', '>', now())
            ->first();
    }
}
