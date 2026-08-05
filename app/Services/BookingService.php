<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceDeparture;
use App\Models\Site;
use App\Services\Booking\PriceResolver;
use App\Services\Booking\SlotAvailability;
use App\Services\Booking\StayAvailability;
use App\Services\Booking\TripAvailability;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The booking engine facade — ONE entry point for all three archetypes
 * (services.kind): slot | stay | trip. Availability logic lives in the
 * per-kind strategies; booking is uniformly transactional with row locks
 * (SELECT … FOR UPDATE) as the anti-double-booking mutex.
 *
 * book() returns the created Booking, or a string ERROR MESSAGE when the
 * request is invalid/unavailable (null-safety replaced by explicit reasons).
 */
class BookingService
{
    /** Holds older than this are considered abandoned checkouts. */
    private const HOLD_MINUTES = 30;

    public function __construct(
        public readonly SlotAvailability $slots,
        public readonly StayAvailability $stays,
        public readonly TripAvailability $trips,
        public readonly PriceResolver $prices,
    ) {}

    // ── Back-compat facades (blade flow + widget use these) ──────────────

    public function settings(Site $site, ?Service $service = null): array
    {
        return $this->slots->settings($site, $service);
    }

    public function isOpenOn(Site $site, CarbonInterface $date, ?Service $service = null): bool
    {
        return $this->slots->isOpenOn($site, $service, $date);
    }

    public function slotsFor(Site $site, Service $service, CarbonInterface $date): array
    {
        $this->releaseStaleHolds($service);

        return $this->slots->slotsFor($site, $service, $date);
    }

    public function upcomingOpenDates(Site $site, int $limit = 14, ?Service $service = null): array
    {
        return $this->slots->upcomingOpenDates($site, $service, $limit);
    }

    public function isBookable(Site $site, Service $service, CarbonInterface $start): bool
    {
        return $this->slots->isBookable($site, $service, $start);
    }

    // ── Kind-dispatched availability ──────────────────────────────────────

    /** Stay: units left over a range. */
    public function stayAvailability(Service $service, CarbonInterface $in, CarbonInterface $out): int
    {
        $this->releaseStaleHolds($service);

        return $this->stays->availableUnits($service, $in, $out);
    }

    /** Trip: bookable departures. */
    public function tripDepartures(Service $service, ?CarbonInterface $date = null, ?string $origin = null, ?string $destination = null): array
    {
        $this->releaseStaleHolds($service);

        return $this->trips->departures($service, $date, $origin, $destination);
    }

    // ── Booking ───────────────────────────────────────────────────────────

    /**
     * Create a booking of any kind.
     *
     * @param  array  $input  kind payload + customer:
     *                        common: name, email, phone?, notes?
     *                        slot:   start (datetime string)
     *                        stay:   check_in, check_out (Y-m-d), guests?, units?
     *                        trip:   departure_id, qty
     * @return Booking|string the booking, or a human error message
     */
    public function book(Site $site, Service $service, array $input, string $status = 'pending'): Booking|string
    {
        $this->releaseStaleHolds($service);

        return DB::transaction(fn () => match ($service->kind) {
            'stay' => $this->bookStay($site, $service, $input, $status),
            'trip' => $this->bookTrip($site, $service, $input, $status),
            default => $this->bookSlot($site, $service, $input, $status),
        });
    }

    private function bookSlot(Site $site, Service $service, array $input, string $status): Booking|string
    {
        $start = Carbon::parse($input['start']);

        // Named STAFF: pin to the requested member, or auto-assign one
        // who is free at this start ("any").
        $resource = null;
        if ($service->usesResources()) {
            $requested = (int) ($input['resource_id'] ?? 0);
            $pool = $requested
                ? $service->activeResources()->whereKey($requested)->get()
                : $service->activeResources()->get();
            if ($pool->isEmpty()) {
                return 'That staff member is not available.';
            }

            // The service row is the mutex for this service's slots.
            Service::whereKey($service->id)->lockForUpdate()->first();
            foreach ($pool as $staff) {
                if ($this->slots->isBookable($site, $service, $start, $staff)
                    && $this->slots->windowFree($site, $service, $start, $staff)) {
                    $resource = $staff;
                    break;
                }
            }
            if (! $resource) {
                return $requested
                    ? 'That staff member is no longer free at that time.'
                    : 'That slot was just taken. Please pick another time.';
            }
        } else {
            if (! $this->slots->isBookable($site, $service, $start)) {
                return 'That time is no longer available. Please pick another slot.';
            }
            Service::whereKey($service->id)->lockForUpdate()->first();
            if (! $this->slots->windowFree($site, $service, $start, null)) {
                return 'That slot was just taken. Please pick another time.';
            }
        }

        [$busyFrom, $busyUntil] = $this->slots->busyWindowFor($service, $start);

        return $this->create($site, $service, $status, [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($service->duration_min),
            'busy_from' => $busyFrom,
            'busy_until' => $busyUntil,
            'resource_id' => $resource?->id,
            'params' => array_filter([
                'start' => $start->format('Y-m-d H:i:s'),
                'resource' => $resource?->name,
            ]),
            'quantity' => 1,
            'total' => $this->prices->priceFor($service, $resource, $start),
        ], $input);
    }

    private function bookStay(Site $site, Service $service, array $input, string $status): Booking|string
    {
        $in = Carbon::parse($input['check_in'])->startOfDay();
        $out = Carbon::parse($input['check_out'])->startOfDay();
        $guests = max(1, (int) ($input['guests'] ?? 1));
        $units = max(1, (int) ($input['units'] ?? 1));

        if ($err = $this->stays->validRange($service, $in, $out, $guests)) {
            return $err;
        }

        // Lock the service row — the mutex for this room type's units.
        Service::whereKey($service->id)->lockForUpdate()->first();

        // Named ROOMS/HOUSES: one booking = one specific unit (requested or
        // auto-assigned); anonymous capacity applies only without resources.
        $resource = null;
        if ($service->usesResources()) {
            $units = 1;
            $requested = (int) ($input['resource_id'] ?? 0);
            if ($requested) {
                $resource = $service->activeResources()->whereKey($requested)->first();
                if (! $resource || ! $this->stays->resourceFree($resource, $in, $out)) {
                    return 'That room is not available for those dates.';
                }
            } else {
                $resource = $this->stays->freeResources($service, $in, $out)->first();
                if (! $resource) {
                    return 'Not enough availability for those dates.';
                }
            }
        } elseif ($this->stays->heldUnits($service, $in, $out) + $units > $service->capacity) {
            return 'Not enough availability for those dates.';
        }

        $nights = $this->stays->nights($in, $out);

        return $this->create($site, $service, $status, [
            'starts_at' => $in,
            'ends_at' => $out,
            'busy_from' => $in,
            'busy_until' => $out,
            'resource_id' => $resource?->id,
            'params' => array_filter([
                'check_in' => $in->format('Y-m-d'),
                'check_out' => $out->format('Y-m-d'),
                'nights' => $nights,
                'guests' => $guests,
                'units' => $units,
                'resource' => $resource?->name,
            ]),
            'quantity' => $units,
            'total' => $this->prices->stayTotal($service, $resource, $in, $out, $units),
        ], $input);
    }

    private function bookTrip(Site $site, Service $service, array $input, string $status): Booking|string
    {
        $qty = max(1, (int) ($input['qty'] ?? 1));

        // Lock the departure row — the mutex for its seats.
        $dep = ServiceDeparture::whereKey((int) ($input['departure_id'] ?? 0))
            ->where('service_id', $service->id)
            ->lockForUpdate()
            ->first();

        if (! $dep || ! $dep->is_active || $dep->departs_at->isPast()) {
            return 'That departure is not available.';
        }
        if ($dep->seatsBooked() + $qty > $dep->seats) {
            return 'Not enough seats left on that departure.';
        }

        return $this->create($site, $service, $status, [
            'starts_at' => $dep->departs_at,
            'ends_at' => $dep->departs_at,
            'busy_from' => $dep->departs_at,
            'busy_until' => $dep->departs_at,
            'departure_id' => $dep->id,
            'resource_id' => $dep->resource_id,
            'params' => array_filter([
                'departure_id' => $dep->id,
                'origin' => $dep->origin,
                'destination' => $dep->destination,
                'departs_at' => $dep->departs_at->format('Y-m-d H:i:s'),
                'qty' => $qty,
                'resource' => $dep->resource?->name,
            ]),
            'quantity' => $qty,
            'total' => ($dep->price_cents ?? $this->prices->priceFor($service, $dep->resource, $dep->departs_at)) * $qty,
        ], $input);
    }

    private function create(Site $site, Service $service, string $status, array $bag, array $input): Booking
    {
        $booking = Booking::create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'departure_id' => $bag['departure_id'] ?? null,
            'resource_id' => $bag['resource_id'] ?? null,
            'customer_name' => $input['name'],
            'customer_email' => $input['email'],
            'customer_phone' => $input['phone'] ?? null,
            'starts_at' => $bag['starts_at'],
            'ends_at' => $bag['ends_at'],
            'busy_from' => $bag['busy_from'] ?? $bag['starts_at'],
            'busy_until' => $bag['busy_until'] ?? $bag['ends_at'],
            'status' => $status,
            'notes' => $input['notes'] ?? null,
            'params' => $bag['params'],
            'quantity' => $bag['quantity'],
            'total_cents' => max(0, (int) $bag['total']),
            'currency' => $service->currency ?? $site->currency ?? 'gbp',
        ]);

        // Dashboard recent-activity: every booking, whichever entry point made it.
        try {
            ActivityLogger::bookingEvent($booking->setRelation('service', $service), 'created');
        } catch (\Throwable $e) {
            report($e);
        }

        return $booking;
    }

    /**
     * Lazy cleanup — no scheduler runs in this environment: abandoned
     * payment holds free their capacity the next time anyone looks.
     */
    private function releaseStaleHolds(Service $service): void
    {
        Booking::where('service_id', $service->id)
            ->where('status', 'awaiting_payment')
            ->where('created_at', '<', Carbon::now()->subMinutes(self::HOLD_MINUTES))
            ->update(['status' => 'cancelled']);
    }
}
