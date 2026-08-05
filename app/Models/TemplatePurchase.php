<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplatePurchase extends Model
{
    protected $fillable = [
        'uuid', 'template_id', 'template_version_id', 'user_id',
        'price_cents', 'currency', 'platform_fee_cents', 'creator_amount_cents',
        'stripe_checkout_session_id', 'stripe_payment_intent_id', 'status', 'purchased_at',
    ];

    protected $casts = ['purchased_at' => 'datetime'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
