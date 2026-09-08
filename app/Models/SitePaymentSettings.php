<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePaymentSettings extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'provider', 'enabled', 'connect_account_id', 'connect_charges_enabled', 'stripe_secret', 'stripe_publishable', 'stripe_webhook_secret', 'livemode',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'connect_charges_enabled' => 'boolean',
        'stripe_secret' => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
        'livemode' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isConfigured(): bool
    {
        return filled($this->stripe_secret) && filled($this->stripe_publishable);
    }
}
