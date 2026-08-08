<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Date-range price override (seasonal/weekend pricing) at service and/or
 * resource level. Specificity when resolving a date's price:
 *   resource+range > service+range > resource base > service base.
 */
class PriceRule extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'service_id', 'resource_id', 'starts_on', 'ends_on', 'price_cents', 'label'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'price_cents' => 'integer'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }
}
