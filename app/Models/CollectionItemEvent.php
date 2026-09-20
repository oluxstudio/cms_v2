<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One visitor engagement beacon on a collection item — append-only. */
class CollectionItemEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const EVENTS = ['view', 'play'];

    protected $fillable = ['site_id', 'collection_id', 'collection_item_id', 'event', 'session_hash', 'created_at'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }
}
