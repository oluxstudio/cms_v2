<?php

namespace App\Modules\Network\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A site's membership of the Referral Network: what it does, where, and its fee per converted lead. */
class NetworkProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'business_type', 'services', 'area', 'postcode', 'lat', 'lng', 'radius_km',
        'pitch', 'fee_cents', 'currency', 'accepting', 'terms_accepted_at', 'terms_version',
    ];

    protected $casts = [
        'services' => 'array', 'accepting' => 'boolean', 'terms_accepted_at' => 'datetime',
        'lat' => 'float', 'lng' => 'float', 'fee_cents' => 'integer', 'radius_km' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Joined and current on the network terms. */
    public function isMember(): bool
    {
        return $this->terms_accepted_at !== null && $this->terms_version === config('network.terms_version');
    }
}
