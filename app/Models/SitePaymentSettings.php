<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePaymentSettings extends Model
{
    protected $fillable = [
        'site_id', 'stripe_secret', 'stripe_publishable', 'stripe_webhook_secret', 'livemode',
    ];

    protected $casts = [
        'stripe_secret'         => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
        'livemode'              => 'boolean',
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
