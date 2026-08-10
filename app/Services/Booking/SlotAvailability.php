<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\Service;
use App\Models\ServiceResource;
use App\Models\Site;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * kind: slot — barber/salon/mechanic time slots on a schedule.
 * The schedule comes from the SERVICE config when set, falling back to the
 * site-wide bookings feature settings. capacity = parallel chairs (bookings
 * allowed at the same start time).
 */
class SlotAvailability
{
    private const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /** @return array{days:array<int,string>,open:string,close:string,slot:int,lead:int,horizon:int} */
    public function settings(Site $site, ?Service $service = null, ?ServiceResource $resource = null): array
    {
        $c = $site->feature('bookings');
        // Overrides cascade: resource (staff schedule) > service > site.
        $c = array_filter((array) ($service?->config ?? []), fn ($v) => $v !== null && $v !== '') + (array) $c;
        $c = array_filter((array) ($resource?->config ?? []), fn ($v) => $v !== null && $v !== '') + $c;

        return [
            'days' => collect(explode(',', (string) ($c['days'] ?? 'mon,tue,wed,thu,fri')))
                ->map(fn ($d) => strtolower(trim($d)))->filter()->values()->all(),
            'open' => (string) ($c['open_time'] ?? '09:00'),
            'close' => (string) ($c['close_time'] ?? '17:00'),
            'slot' => max(5, (int) ($c['slot_minutes'] ?? 30)),
            'lead' => max(0, (int) ($c['lead_hours'] ?? 12)),
            'horizon' => max(1, (int) ($c['horizon_days'] ?? 30)),
            // Per-WEEKDAY hour overrides: ['fri' => ['open' => '10:00', 'close' => '14:00'], …]
            'day_hours' => (array) ($c['day_hours'] ?? []),
        ];
    }

    public function isOpenOn(Site $site, ?Service $service, CarbonInterface $date, ?ServiceResource $resource = null): bool
    {
        $s = $this->settings($site, $service, $resource);
        if (! in_array(self::WEEKDAYS[$date->dayOfWeek], $s['days'], true)) {
            return false;
        }

        // Admin exceptions: a whole-day block (site-wide or for this service)
        // closes the date outright.
        return ! BookingBlock::forDay($site, $date->format('Y-m-d'), $service)['dayBlocked'];
    }

    /**
     * Available start times. With NAMED RESOURCES (staff): a specific
     * resource uses its own schedule; no resource = the union — a time is
     * offered while ANY staff member is free at it.
     *
     * @return array<int,array{iso:string,label:string}>
     */
    public function slotsFor(Site $site, Service $service, CarbonInterface $date, ?ServiceResource $resource = null): array
    {
        if ($resource === null && $service->usesResources()) {
            $union = [];
            foreach ($service->activeResources()->get() as $staff) {
                foreach ($this->slotsFor($site, $service, $date, $staff) as $slot) {
                    $union[$slot['iso']] = $slot;
                }
            }
            ksort($union);

            return array_values($union);
        }

        $s = $this->settings($site, $service, $resource);
        $day = $date->copy()->startOfDay();

        if (! $this->isOpenOn($site, $service, $day, $resource)) {
            return [];
        }
        $today = Carbon::today();
        if ($day->lt($today) || $day->gt($today->copy()->addDays($s['horizon']))) {
            return [];
        }

        // Hour precedence: per-DATE exception > per-WEEKDAY override > schedule.
        $weekday = $s['day_hours'][self::WEEKDAYS[$day->dayOfWeek]] ?? null;
        $exceptions = BookingBlock::forDay($site, $day->format('Y-m-d'), $service);
        $open = $exceptions['hours']['open'] ?? $weekday['open'] ?? $s['open'];
        $closeT = $exceptions['hours']['close'] ?? $weekday['close'] ?? $s['close'];
        [$oh, $om] = array_pad(array_map('intval', explode(':', $open)), 2, 0);
        [$ch, $cm] = array_pad(array_map('intval', explode(':', $closeT)), 2, 0);
        $cursor = $day->copy()->setTime($oh, $om);
        $close = $day->copy()->setTime($ch, $cm);
        $earliest = Carbon::now()->addHours($s['lead']);

        // Busy windows for the day (buffers already baked into each window).
        // Resource mode: the resource's bookings across ALL services; pooled
        // mode: this service's bookings. Padding covers cross-midnight buffers.
        $windows = $this->busyWindows($site, $service, $resource, $day);
        $cap = $resource ? max(1, $resource->capacity) : max(1, $service->capacity);
        $bBefore = $service->bufferBefore();
        $bAfter = $service->bufferAfter();
        // Admin exceptions: individually blocked slot times on this date.
        $blocked = $exceptions['times'];

        $slots = [];
        while ($cursor->copy()->addMinutes($service->duration_min)->lte($close)) {
            $from = $cursor->copy()->subMinutes($bBefore);
            $until = $cursor->copy()->addMinutes($service->duration_min + $bAfter);
            $held = $windows->filter(fn ($w) => $w['from'] < $until && $w['until'] > $from)->sum('qty');
            if ($cursor->gte($earliest)
                && ! isset($blocked[$cursor->format('H:i')])
                && $held < $cap) {
                $slots[] = ['iso' => $cursor->format('Y-m-d H:i:s'), 'label' => $cursor->format('g:i A')];
            }
            $cursor->addMinutes($s['slot']);
        }

        return $slots;
    }

    /** The next N open dates (for a date picker), as Y-m-d strings. */
    public function upcomingOpenDates(Site $site, ?Service $service = null, int $limit = 14): array
    {
        $s = $this->settings($site, $service);
        $out = [];
        $d = Carbon::today();
        $end = Carbon::today()->addDays($s['horizon']);
        while ($d->lte($end) && count($out) < $limit) {
            if ($this->isOpenOn($site, $service, $d)) {
                $out[] = $d->format('Y-m-d');
            }
            $d->addDay();
        }

        return $out;
    }

    /** Validate a requested start is a real, in-policy, free slot. */
    public function isBookable(Site $site, Service $service, CarbonInterface $start, ?ServiceResource $resource = null): bool
    {
        foreach ($this->slotsFor($site, $service, $start, $resource) as $slot) {
            if ($slot['iso'] === $start->format('Y-m-d H:i:s')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bookings at the exact start, counted under lock — call inside a
     * transaction; the caller locks the service row as the mutex.
     */
    /**
     * The PADDED window a booking would occupy — service buffers included.
     *
     * @return array{0:Carbon,1:Carbon} [busy_from, busy_until]
     */
    public function busyWindowFor(Service $service, CarbonInterface $start): array
    {
        return [
            $start->copy()->subMinutes($service->bufferBefore()),
            $start->copy()->addMinutes($service->duration_min + $service->bufferAfter()),
        ];
    }

    /**
     * Conflict check under lock: is the padded window still free?
     * Resource mode counts the RESOURCE's bookings across ALL services
     * (cross-service conflicts); pooled mode counts this service's bookings.
     */
    public function windowFree(Site $site, Service $service, CarbonInterface $start, ?ServiceResource $resource, int $qty = 1): bool
    {
        [$from, $until] = $this->busyWindowFor($service, $start);
        $cap = $resource ? max(1, $resource->capacity) : max(1, $service->capacity);

        $held = (int) Booking::query()
            ->active()
            ->when($resource,
                fn ($q) => $q->where('resource_id', $resource->id),
                fn ($q) => $q->where('service_id', $service->id))
            ->where('busy_from', '<', $until->format('Y-m-d H:i:s'))
            ->where('busy_until', '>', $from->format('Y-m-d H:i:s'))
            ->sum('quantity');

        return $held + $qty <= $cap;
    }

    /**
     * Busy windows touching a day, as [{from, until, qty}] — resource mode is
     * cross-service; pooled mode is per-service.
     */
    private function busyWindows(Site $site, Service $service, ?ServiceResource $resource, CarbonInterface $day)
    {
        return Booking::query()
            ->active()
            ->where('site_id', $site->id)
            ->when($resource,
                fn ($q) => $q->where('resource_id', $resource->id),
                fn ($q) => $q->where('service_id', $service->id))
            ->where('busy_from', '<', $day->copy()->endOfDay()->addHours(6))
            ->where('busy_until', '>', $day->copy()->startOfDay()->subHours(6))
            ->get(['busy_from', 'busy_until', 'quantity'])
            ->map(fn ($b) => ['from' => $b->busy_from, 'until' => $b->busy_until, 'qty' => (int) $b->quantity]);
    }
}
