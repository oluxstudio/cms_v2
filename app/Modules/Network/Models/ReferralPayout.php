<?php

namespace App\Modules\Network\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money owed to a referrer for one converted referral.
 * pending (fee not yet collected from the receiver) → ready (collected) →
 * transferred (Stripe Connect transfer) | credited (Olux bill credit) | failed (retry).
 */
class ReferralPayout extends Model
{
    use HasUlids;

    public const STATUSES = ['pending', 'ready', 'transferred', 'credited', 'failed'];

    protected $fillable = [
        'referral_id', 'site_id', 'gross_cents', 'olux_cents', 'net_cents', 'currency', 'status', 'method',
        'stripe_transfer_id', 'stripe_balance_txn_id', 'failure', 'paid_at',
    ];

    protected $casts = ['gross_cents' => 'integer', 'olux_cents' => 'integer', 'net_cents' => 'integer', 'paid_at' => 'datetime'];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
