<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One booking of ANY kind (slot appointment, stay reservation, trip seats).
 * Kind-specific parameters live in `params`; starts_at/ends_at are always
 * populated (stay: check-in/check-out midnights; trip: departure both ways)
 * so overlap queries and sorting stay uniform.
 *
 * status: pending | awaiting_payment | confirmed | cancelled | no_show
 */
class Booking extends Model
{
    use HasUlids;

    public const STATUSES = ['pending', 'awaiting_payment', 'confirmed', 'cancelled', 'no_show'];

    protected $fillable = [
        'site_id', 'service_id', 'departure_id', 'resource_id', 'reference',
        'customer_name', 'customer_email', 'customer_phone',
        'starts_at', 'ends_at', 'busy_from', 'busy_until', 'status', 'notes',
        'params', 'quantity', 'total_cents', 'paid_cents', 'currency', 'stripe_session_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'busy_from' => 'datetime',
        'busy_until' => 'datetime',
        'params' => 'array',
        'quantity' => 'integer',
        'total_cents' => 'integer',
        'paid_cents' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->reference ??= Str::upper(Str::random(10));
        });
    }

    /** Everything that occupies capacity — i.e. not cancelled or a no-show. */
    /** Bookings happening today (not cancelled). */
    public function scopeToday(Builder $q): Builder
    {
        return $q->active()->whereDate('starts_at', today());
    }

    /** Future bookings (not cancelled), soonest first. */
    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->active()->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['cancelled', 'no_show']);
    }

    /** Ordered lifecycle events for the card's lifecycle box. */
    public function timeline(): array
    {
        $paid = $this->total_cents > 0 && $this->paid_cents > 0;

        return [
            ['label' => 'Created',   'at' => $this->created_at],
            ['label' => 'Confirmed', 'at' => $this->status === 'confirmed' ? $this->updated_at : null],
            ['label' => $this->status === 'cancelled' ? 'Cancelled' : 'Paid',
                'at' => $this->status === 'cancelled' ? $this->updated_at
                    : ($paid && $this->balanceCents() === 0 ? $this->updated_at : null)],
        ];
    }

    public function markConfirmed(): void
    {
        $this->update(['status' => 'confirmed']);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /** The customer didn't turn up — only meaningful for past bookings. */
    public function markNoShow(): void
    {
        $this->update(['status' => 'no_show']);
    }

    public function formattedTotal(): string
    {
        return $this->total_cents <= 0
            ? 'Free'
            : Money::format((int) $this->total_cents, $this->currency);
    }

    /** Money still owed (deposit flows): total − paid. */
    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    public function formattedBalance(): string
    {
        return Money::format($this->balanceCents(), $this->currency);
    }

    public function formattedPaid(): string
    {
        return Money::format((int) $this->paid_cents, $this->currency);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(ServiceDeparture::class, 'departure_id');
    }

    /** The staff member / room / vehicle this booking is pinned to. */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }
}
