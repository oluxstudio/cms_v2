<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A blocked date (start_time NULL) or blocked slot (start_time set) —
 * availability exceptions the admin paints in the Bookings → Availability tab.
 */
class BookingBlock extends Model
{
    protected $fillable = ['site_id', 'service_id', 'date', 'start_time', 'open_time', 'close_time'];

    protected $casts = ['date' => 'date'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * ['dayBlocked' => bool, 'times' => ['14:00' => true, …]] for one date.
     * Site-wide blocks (service_id NULL) always apply; with a service given,
     * that service's own blocks apply on top.
     */
    public static function forDay(Site $site, string $date, ?Service $service = null): array
    {
        $rows = static::where('site_id', $site->id)->whereDate('date', $date)
            ->where(fn ($q) => $q->whereNull('service_id')
                ->when($service, fn ($qq) => $qq->orWhere('service_id', $service->id)))
            ->get();

        // Day-level rows (start_time NULL): with hours = custom opening hours
        // for the date; without = whole day off. Service-scoped hours beat
        // site-wide ones.
        $hoursRow = $rows->first(fn ($r) => $r->start_time === null && $r->open_time !== null && $r->service_id !== null)
            ?? $rows->first(fn ($r) => $r->start_time === null && $r->open_time !== null);

        return [
            'dayBlocked' => $rows->contains(fn ($r) => $r->start_time === null && $r->open_time === null),
            'hours'      => $hoursRow ? ['open' => substr($hoursRow->open_time, 0, 5), 'close' => substr($hoursRow->close_time, 0, 5)] : null,
            'times'      => $rows->filter(fn ($r) => $r->start_time !== null)
                ->mapWithKeys(fn ($r) => [substr($r->start_time, 0, 5) => true])->all(),
        ];
    }
}
