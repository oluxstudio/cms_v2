<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A concrete scheduled departure of a `trip` service (bus/car/transport):
 * origin → destination at departs_at, with a fixed number of seats.
 */
class ServiceDeparture extends Model
{
    protected $fillable = [
        'service_id', 'resource_id', 'origin', 'destination', 'departs_at',
        'seats', 'price_cents', 'is_active',
    ];

    protected $casts = [
        'departs_at'  => 'datetime',
        'seats'       => 'integer',
        'price_cents' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'departure_id');
    }

    /** The vehicle operating this departure (optional). */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ServiceResource::class, 'resource_id');
    }

    /** Seats held by active (non-cancelled) bookings. */
    public function seatsBooked(): int
    {
        return (int) $this->bookings()->active()->sum('quantity');
    }

    public function seatsLeft(): int
    {
        return max(0, $this->seats - $this->seatsBooked());
    }

    /** Seat price in cents — departure override, else the service price. */
    public function effectivePriceCents(): int
    {
        return $this->price_cents ?? (int) ($this->service?->price_cents ?? 0);
    }

    public function routeLabel(): string
    {
        return "{$this->origin} → {$this->destination}";
    }
}
