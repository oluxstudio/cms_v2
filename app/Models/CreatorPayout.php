<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorPayout extends Model
{
    use HasUlids;

    protected $fillable = [
        'creator_user_id', 'amount_cents', 'currency', 'status', 'method',
        'stripe_transfer_id', 'note', 'paid_at', 'created_by',
    ];

    protected $casts = ['paid_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(TemplatePurchase::class, 'payout_id');
    }
}
