<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A domain bought through the platform (+ optionally the hosting plan bought with it). */
class DomainOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'site_id', 'domain', 'type', 'years', 'price_cents', 'plan', 'status',
        'stripe_session_id', 'stripe_payment_intent_id', 'stripe_subscription_id', 'registrar_ref', 'error', 'expires_at',
    ];

    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'registered';
    }
}
