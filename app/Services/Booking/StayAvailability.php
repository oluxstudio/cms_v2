<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceResource;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * kind: stay — rooms/hotels/houses booked per night.
 * One service = one room type; service.capacity = identical units.
 * A date range is available while overlapping active bookings hold fewer
 * than `capacity` units for every night of [check_in, check_out).
 */
class StayAvailability
{
    /** Units still free across the whole [in, out) range (read-only check). */
    public function availableUnits(Service $service, CarbonInterface $in, CarbonInterface $out): int
    {
        if ($service->usesResources()) {
            return $this->freeResources($service, $in, $out)->count();
        }

        return max(0, $service->capacity - $this->heldUnits($service, $in, $out));
    }

    /**
     * Named rooms/houses with NO overlapping active booking in [in, out) —
     * checked across ALL services (resources are shared site-wide).
     */
    public function freeResources(Service $service, CarbonInterface $in, CarbonInterface $out)
    {
        $ids = $service->activeResources()->pluck('service_resources.id');

        $busy = Booking::query()
            ->active()
            ->whereIn('resource_id', $ids)
            ->where('busy_from', '<', $out->format('Y-m-d H:i:s'))
            ->where('busy_until', '>', $in->format('Y-m-d H:i:s'))
            ->pluck('resource_id')
            ->all();

        return $service->activeResources()->whereNotIn('service_resources.id', $busy)->get();
    }

    /** Is one SPECIFIC room/house free for the range? */
    public function resourceFree(ServiceResource $resource, CarbonInterface $in, CarbonInterface $out): bool
    {
        return ! Booking::where('resource_id', $resource->id)
            ->active()
            ->where('busy_from', '<', $out->format('Y-m-d H:i:s'))
            ->where('busy_until', '>', $in->format('Y-m-d H:i:s'))
            ->exists();
    }

    /**
     * Units held by overlapping active bookings. Call inside a transaction
     * with the service row locked when booking (the lock is the mutex).
     */
    public function heldUnits(Service $service, CarbonInterface $in, CarbonInterface $out): int
    {
        return (int) Booking::where('service_id', $service->id)
            ->active()
            ->where('busy_from', '<', $out->format('Y-m-d H:i:s'))
            ->where('busy_until', '>', $in->format('Y-m-d H:i:s'))
            ->sum('quantity');
    }

    /** Policy check: nights within min/max, horizon, guests, future dates. */
    public function validRange(Service $service, CarbonInterface $in, CarbonInterface $out, int $guests = 1): ?string
    {
        $nights = $in->copy()->startOfDay()->diffInDays($out->copy()->startOfDay());
        if ($nights < 1) {
            return 'Check-out must be after check-in.';
        }
        if ($in->copy()->startOfDay()->lt(Carbon::today())) {
            return 'Check-in cannot be in the past.';
        }
        if ($nights < (int) $service->configValue('min_nights', 1)) {
            return 'Stay is shorter than the minimum of '.$service->configValue('min_nights', 1).' night(s).';
        }
        if ($nights > (int) $service->configValue('max_nights', 30)) {
            return 'Stay is longer than the maximum of '.$service->configValue('max_nights', 30).' night(s).';
        }
        if ($guests > (int) $service->configValue('max_guests', 2)) {
            return 'Too many guests for this accommodation (max '.$service->configValue('max_guests', 2).').';
        }
        $horizon = (int) $service->configValue('horizon_days', 365);
        if ($out->copy()->startOfDay()->gt(Carbon::today()->addDays($horizon))) {
            return 'Bookings are only open '.$horizon.' days ahead.';
        }

        return null; // valid
    }

    public function nights(CarbonInterface $in, CarbonInterface $out): int
    {
        return max(0, $in->copy()->startOfDay()->diffInDays($out->copy()->startOfDay()));
    }

    /**
     * Per-day remaining units for a month — powers the widget's range picker.
     *
     * @return array<string,int> "Y-m-d" => units_left
     */
    public function calendar(Service $service, CarbonInterface $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // One query: every active booking overlapping the month.
        $bookings = Booking::where('service_id', $service->id)
            ->active()
            ->where('busy_from', '<', $end->copy()->addDay()->format('Y-m-d 00:00:00'))
            ->where('busy_until', '>', $start->format('Y-m-d 00:00:00'))
            ->get(['starts_at', 'ends_at', 'quantity']);

        $total = $service->usesResources() ? $service->activeResources()->count() : $service->capacity;

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $night = $d->format('Y-m-d');
            $held = $bookings->filter(fn ($b) => $b->starts_at->format('Y-m-d') <= $night
                                              && $b->ends_at->format('Y-m-d') > $night)
                ->sum('quantity');
            $out[$night] = max(0, $total - $held);
        }

        return $out;
    }
}
