<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFeature extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'key', 'enabled', 'config'];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
