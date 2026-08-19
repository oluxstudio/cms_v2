<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One top-level section extracted from an ingested page, with its classification
 * (component | collection | post | form), a confidence score, and the extracted
 * fields. Low-confidence rows are flagged for human review before commit.
 */
class IngestedSection extends Model
{
    use HasUlids;

    public const COMPONENT = 'component';

    public const COLLECTION = 'collection';

    public const POST = 'post';

    public const FORM = 'form';

    /** Below this, a classification is flagged for human review, not auto-committed. */
    public const REVIEW_THRESHOLD = 0.7;

    protected $fillable = [
        'page_ingestion_id', 'site_id', 'position', 'tag', 'html', 'css',
        'classification', 'confidence', 'needs_review', 'fields',
        'committed_ref_type', 'committed_ref_id',
    ];

    protected $casts = [
        'fields' => 'array',
        'confidence' => 'float',
        'needs_review' => 'boolean',
        'position' => 'integer',
    ];

    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(PageIngestion::class, 'page_ingestion_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
