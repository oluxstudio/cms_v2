<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $fillable = [
        'site_id', 'donor_email', 'donor_name', 'amount_cents',
        'currency', 'message', 'status', 'stripe_session_id', 'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_at'      => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function formattedAmount(): string
    {
        return \App\Support\Money::format((int) $this->amount_cents, $this->currency);
    }

    public function markPaid(): void
    {
        if ($this->status === 'paid') {
            return;
        }
        $this->update(['status' => 'paid', 'paid_at' => now()]);
    }
}
