<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A point-in-time snapshot of one content model's editable payload, captured
 * before every mutation (see ContentVersioner). Pruned to the last N per
 * subject; restoring writes the payload back and republishes.
 */
class ContentVersion extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'subject_type', 'subject_id', 'payload', 'label', 'created_by'];

    protected $casts = ['payload' => 'array'];
}
