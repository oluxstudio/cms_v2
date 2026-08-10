<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded page view on a client site. Append-only (no updated_at). The raw
 * IP is never stored — only a daily-salted hash for unique-visitor counting.
 */
class Visit extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'site_id', 'visitor_hash', 'session_id',
        'path', 'referrer', 'referrer_host', 'source',
        'utm_source', 'utm_medium', 'utm_campaign',
        'country_code', 'country', 'region', 'city', 'latitude', 'longitude',
        'device_type', 'os', 'os_version', 'browser', 'browser_version', 'device_brand',
        'language', 'is_bot', 'created_at',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Real human traffic only (bots excluded) — the default for dashboards. */
    public function scopeHumans(Builder $q): Builder
    {
        return $q->where('is_bot', false);
    }

    public function scopeForSite(Builder $q, string $siteId): Builder
    {
        return $q->where('site_id', $siteId);
    }
}
