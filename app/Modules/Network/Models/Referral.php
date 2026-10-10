<?php

namespace App\Modules\Network\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One lead passed from a referrer site to a receiving site.
 *
 * Status machine:
 *   pending_consent → (customer clicks Yes) shared → accepted | declined
 *   pending_consent → consent_refused | expired (no answer within consent_days)
 *   shared/accepted → converted (paid booking/invoice/order within the window, or marked won)
 *                   → expired (window passed without conversion)
 *   converted → disputed (receiver, within dispute_days) → converted (dispute rejected) | void (upheld)
 *   converted (dispute window over) → billed (line on the receiver's Olux bill) → collected (bill paid)
 *   cancelled — withdrawn by the referrer before sharing
 * The payout to the referrer lives in referral_payouts.
 */
class Referral extends Model
{
    use HasUlids;

    public const STATUSES = ['pending_consent', 'consent_refused', 'shared', 'accepted', 'declined', 'converted',
        'disputed', 'void', 'billed', 'collected', 'expired', 'cancelled'];

    /** Open = still able to convert. */
    public const OPEN = ['shared', 'accepted'];

    protected $fillable = [
        'reference', 'from_site_id', 'to_site_id', 'from_contact_id', 'to_contact_id', 'created_by',
        'customer_name', 'customer_email', 'customer_phone', 'note', 'status', 'fee_cents', 'currency', 'olux_cut_pct',
        'consent_token_hash', 'consent_method', 'consent_text', 'consent_requested_at', 'consented_at', 'consent_ip',
        'shared_at', 'accepted_at', 'declined_at', 'decline_reason', 'converted_at', 'converted_via',
        'disputed_at', 'dispute_reason', 'dispute_resolved_at', 'dispute_outcome',
        'billed_at', 'stripe_invoice_item_id', 'collected_at', 'expires_at',
    ];

    protected $casts = [
        'fee_cents' => 'integer', 'olux_cut_pct' => 'float',
        'consent_requested_at' => 'datetime', 'consented_at' => 'datetime', 'shared_at' => 'datetime',
        'accepted_at' => 'datetime', 'declined_at' => 'datetime', 'converted_at' => 'datetime',
        'disputed_at' => 'datetime', 'dispute_resolved_at' => 'datetime', 'billed_at' => 'datetime',
        'collected_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class)->orderBy('created_at');
    }

    public function payout(): HasOne
    {
        return $this->hasOne(ReferralPayout::class);
    }

    /** Fee minus Olux's cut, in pence — what the referrer receives. */
    public function netCents(): int
    {
        return $this->fee_cents - $this->oluxCents();
    }

    public function oluxCents(): int
    {
        return (int) round($this->fee_cents * $this->olux_cut_pct / 100);
    }
}
