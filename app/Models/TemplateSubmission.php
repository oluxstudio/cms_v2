<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Nuxt template app discovered in the staging folder, awaiting moderator
 * review before publication to the marketplace. `extraction` is the
 * TemplateExtractor manifest: pages → blocks → nodes, plus theme, fonts,
 * behaviours and an asset summary.
 */
class TemplateSubmission extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['key', 'name', 'status', 'extraction', 'note', 'reviewed_by', 'reviewed_at'];

    protected $casts = [
        'extraction' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Absolute path of this submission's app inside the staging mount. */
    public function stagingPath(): string
    {
        return rtrim(config('templates.staging_path'), '/').'/'.$this->key;
    }

    /** Quick counts for the review card: [pages, blocks, assets]. */
    public function summary(): array
    {
        $x = $this->extraction ?? [];
        $pages = $x['pages'] ?? [];

        return [
            'pages' => count($pages),
            'blocks' => array_sum(array_map(fn ($p) => count($p['blocks'] ?? []), $pages)),
            'assets' => $x['assets']['count'] ?? 0,
            'behaviours' => $x['behaviours'] ?? [],
            'fonts' => array_column($x['fonts'] ?? [], 'family'),
            'theme' => $x['theme'] ?? [],
        ];
    }
}
