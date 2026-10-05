<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A previous web address of a site — see Site::changeAddress(). */
class SiteAlias extends Model
{
    protected $fillable = ['site_id', 'name'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
