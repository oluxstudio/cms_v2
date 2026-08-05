<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One undo/redo unit: a human action or a whole AI turn. */
class BlockBatch extends Model
{
    protected $fillable = ['page_id', 'source', 'label', 'forward', 'inverse', 'undone'];

    protected $casts = ['forward' => 'array', 'inverse' => 'array', 'undone' => 'boolean'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
