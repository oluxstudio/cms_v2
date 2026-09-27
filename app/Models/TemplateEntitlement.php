<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateEntitlement extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'template_id', 'source', 'purchase_id', 'price_paid_cents', 'stripe_session_id', 'purchased_at'];

    protected $casts = ['purchased_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
