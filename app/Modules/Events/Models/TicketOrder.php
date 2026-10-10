<?php

namespace App\Modules\Events\Models;

use App\Models\Site;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One RSVP / purchase. While `pending` (awaiting Stripe) its tickets HOLD
 * capacity; `cancelled` (expired checkout) and `refunded` release it.
 */
class TicketOrder extends Model
{
    use HasUlids;

    /** Statuses whose tickets count against capacity. */
    public const HOLDING = ['pending', 'paid'];

    protected $table = 'event_ticket_orders';

    protected $fillable = [
        'site_id', 'event_id', 'reference', 'buyer_name', 'buyer_email', 'buyer_phone', 'quantity', 'total_cents',
        'currency', 'status', 'checkout_session_id', 'payment_ref', 'paid_at', 'refunded_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'total_cents' => 'integer',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketOrder $o) {
            if (! $o->reference) {
                do {
                    $ref = 'T-'.strtoupper(Str::random(7));
                } while (static::where('reference', $ref)->exists());
                $o->reference = $ref;
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'order_id');
    }

    public function formattedTotal(): string
    {
        return Money::format((int) $this->total_cents, $this->currency, free: true);
    }

    public function isFree(): bool
    {
        return (int) $this->total_cents === 0;
    }
}
