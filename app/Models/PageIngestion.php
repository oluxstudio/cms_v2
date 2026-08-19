<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single ingested page (raw snapshot + crawl metadata) in the Site Connect
 * pipeline. Split into IngestedSection rows, then committed to real content.
 */
class PageIngestion extends Model
{
    use HasUlids;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_CLASSIFIED = 'classified';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id', 'page_id', 'source_url', 'raw_html', 'styles',
        'meta', 'discovered_links', 'status', 'error',
    ];

    protected $casts = [
        'meta' => 'array',
        'discovered_links' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(IngestedSection::class)->orderBy('position');
    }
}
