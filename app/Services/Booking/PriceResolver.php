<?php

namespace App\Services\Booking;

use App\Models\PriceRule;
use App\Models\Service;
use App\Models\ServiceResource;
use Carbon\CarbonInterface;

/**
 * Resolves the effective price for a service (+optional resource) on a date.
 * Specificity, most specific wins:
 *   resource + date-range rule  >  service + date-range rule
 *   >  resource base override   >  service base price.
 * Stays price each NIGHT individually (seasonal nights sum correctly).
 */
class PriceResolver
{
    public function priceFor(Service $service, ?ServiceResource $resource, CarbonInterface $date): int
    {
        $day = $date->format('Y-m-d');

        $rules = PriceRule::where('site_id', $service->site_id)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->where(function ($q) use ($service, $resource) {
                $q->where('service_id', $service->id);
                if ($resource) {
                    $q->orWhere('resource_id', $resource->id);
                }
            })
            ->get();

        // resource+range beats service+range.
        if ($resource && ($r = $rules->first(fn ($x) => $x->resource_id === $resource->id))) {
            return $r->price_cents;
        }
        if ($r = $rules->first(fn ($x) => $x->service_id === $service->id && $x->resource_id === null)) {
            return $r->price_cents;
        }
        if ($resource && ($resource->price_cents ?? 0) > 0) {
            return $resource->price_cents;
        }

        return (int) $service->price_cents;
    }

    /** Per-night pricing summed across [in, out) × units. */
    public function stayTotal(Service $service, ?ServiceResource $resource, CarbonInterface $in, CarbonInterface $out, int $units = 1): int
    {
        $total = 0;
        for ($d = $in->copy()->startOfDay(); $d->lt($out->copy()->startOfDay()); $d->addDay()) {
            $total += $this->priceFor($service, $resource, $d);
        }

        return $total * max(1, $units);
    }
}
