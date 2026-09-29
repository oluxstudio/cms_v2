<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client's uploaded Nuxt app, tracked from zip to a private library template. */
class TemplateUpload extends Model
{
    use HasUlids;

    public const QUEUED = 'queued';

    public const SCANNING = 'scanning';

    public const BUILDING = 'building';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $fillable = [
        'user_id', 'site_id', 'for_store', 'key', 'name', 'original_filename', 'status', 'step', 'error',
        'lint_score', 'warnings', 'template_id', 'build_started_at', 'finished_at',
    ];

    protected $casts = [
        'for_store' => 'boolean',
        'warnings' => 'array',
        'build_started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function inProgress(): bool
    {
        return in_array($this->status, [self::QUEUED, self::SCANNING, self::BUILDING], true);
    }

    /** Where the uploaded zip waits until the scan job unpacks it. */
    public function zipPath(): string
    {
        return storage_path("app/private/template-upload-zips/{$this->id}.zip");
    }
}
