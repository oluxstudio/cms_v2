<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-site Site Connect wiring: delivery mode, domain allow-list, and
 * ingest/publish timestamps. The bearer token lives in `api_tokens` (hashed,
 * ability-scoped) — this record is the tenant-level state connect.js reads via
 * GET /api/v1/connect/status.
 */
class SiteConnection extends Model
{
    use HasUlids;

    public const MODE_COLLECT = 'collect';

    public const MODE_HYDRATE = 'hydrate';

    protected $fillable = [
        'site_id', 'mode', 'allowed_origins', 'last_ingested_at', 'last_published_at',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'last_ingested_at' => 'datetime',
        'last_published_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isHydrating(): bool
    {
        return $this->mode === self::MODE_HYDRATE;
    }

    /** Is `$origin` (a scheme://host) allowed to read/POST for this site? */
    public function allowsOrigin(?string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST) ?: $origin;

        foreach ($this->allowed_origins ?? [] as $allowed) {
            $allowedHost = parse_url($allowed, PHP_URL_HOST) ?: $allowed;
            if (strcasecmp($host, $allowedHost) === 0) {
                return true;
            }
        }

        return false;
    }
}
