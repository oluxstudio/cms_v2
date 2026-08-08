<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionItem extends Model
{
    use HasUlids;

    protected $fillable = ['collection_id', 'site_id', 'data', 'status', 'ip_address'];

    protected $casts = ['data' => 'array'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
