<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable block: a named, id-less subtree the user saved from any canvas.
 * Scoped to the USER — their personal library, available on every site they
 * build. Dragging one in re-inserts a fresh copy through the ONE mutation service.
 */
class BlockPreset extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'name', 'root_type', 'tree'];

    protected $casts = ['tree' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
