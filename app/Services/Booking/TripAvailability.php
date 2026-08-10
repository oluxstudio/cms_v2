<?php

namespace App\Services\Booking;

use App\Models\Service;
use App\Models\ServiceDeparture;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * kind: trip — car/bus/transport departures with seat capacity.
 * Availability = future active departures with seats left.
 */
class TripAvailability
{
    /**
     * Future departures for a service, optionally filtered.
     *
     * @return array<int,array{id:int,origin:string,destination:string,departs_at:string,departs_label:string,seats_left:int,price_cents:int}>
     */
    public function departures(Service $service, ?CarbonInterface $date = null, ?string $origin = null, ?string $destination = null): array
    {
        return $service->departures()
            ->where('is_active', true)
            ->where('departs_at', '>', Carbon::now())
            ->when($date, fn ($q) => $q->whereBetween('departs_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]))
            ->when($origin, fn ($q) => $q->where('origin', 'like', "%{$origin}%"))
            ->when($destination, fn ($q) => $q->where('destination', 'like', "%{$destination}%"))
            ->orderBy('departs_at')
            ->get()
            ->map(fn (ServiceDeparture $d) => [
                'id' => $d->id,
                'origin' => $d->origin,
                'destination' => $d->destination,
                'departs_at' => $d->departs_at->toIso8601String(),
                'departs_label' => $d->departs_at->format('D, M j · g:i A'),
                'seats_left' => $d->seatsLeft(),
                'price_cents' => $d->effectivePriceCents(),
            ])
            ->filter(fn ($d) => $d['seats_left'] > 0)
            ->values()
            ->all();
    }
}
