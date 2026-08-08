<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SITE-LEVEL bookable resource — a staff member, a room/house, a vehicle,
 * a table, equipment… Shared across services (belongsToMany): booking the
 * resource through ANY service occupies it for all of them (busy-window
 * overlap). capacity = parallel bookings the resource itself can hold
 * (stylist 1, meeting room 12). price_cents overrides the service price.
 */
class ServiceResource extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'name', 'config', 'capacity', 'price_cents', 'is_active', 'sort'];

    protected $casts = [
        'config' => 'array',
        'capacity' => 'integer',
        'price_cents' => 'integer',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'resource_service', 'resource_id', 'service_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Active bookings (ANY service) whose busy window overlaps [$from, $until).
     * The busy window has each booking's own buffers baked in.
     */
    public function overlapCount(CarbonInterface $from, CarbonInterface $until): int
    {
        return (int) $this->bookings()
            ->active()
            ->where('busy_from', '<', $until->format('Y-m-d H:i:s'))
            ->where('busy_until', '>', $from->format('Y-m-d H:i:s'))
            ->sum('quantity');
    }

    public function freeFor(CarbonInterface $from, CarbonInterface $until, int $qty = 1): bool
    {
        return $this->overlapCount($from, $until) + $qty <= max(1, $this->capacity);
    }

    /** What this resource is called for the service's kind. */
    public static function noun(string $kind): string
    {
        return match ($kind) {
            'stay' => 'room / house',
            'trip' => 'vehicle',
            default => 'staff member',
        };
    }
}
