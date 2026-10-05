<?php

namespace App\Models;

use App\Support\CollectionAutoFields;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionItem extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = ['collection_id', 'site_id', 'data', 'position', 'status', 'ip_address'];

    protected $casts = ['data' => 'array'];

    protected static function booted(): void
    {
        // System-filled fields (dates, who, entry number, site properties) —
        // on create and whenever the data changes, never on a reorder.
        static::saving(function (self $item) {
            if (! $item->exists || $item->isDirty('data')) {
                CollectionAutoFields::fill($item);
            }
        });
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
