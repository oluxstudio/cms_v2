<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An admin-defined booking TYPE — a named preset ("Dentist visit", "Boat
 * rental") on one of the three availability engines. It contributes the
 * label, icon, resource noun and default parameters; all availability and
 * payment logic keys off the engine (services.kind) unchanged.
 */
class BookingType extends Model
{
    use HasUlids;

    public const ENGINES = ['slot', 'stay', 'trip'];

    protected $fillable = ['site_id', 'name', 'slug', 'icon', 'engine', 'resource_noun', 'defaults', 'fields', 'is_active', 'sort'];

    protected $casts = ['defaults' => 'array', 'fields' => 'array', 'is_active' => 'boolean', 'sort' => 'integer'];

    /**
     * The tickable parameter fields per engine — the type builder offers
     * these; a type stores the enabled subset in `fields`.
     */
    public static function fieldCatalog(string $engine): array
    {
        return match ($engine) {
            'stay' => [
                'price' => 'Price per night',
                'deposit' => 'Deposit',
                'nights' => 'Min / max nights',
                'guests' => 'Max guests',
                'resources' => 'Named rooms / houses',
                'seasonal' => 'Seasonal pricing',
            ],
            'trip' => [
                'price' => 'Seat price',
                'deposit' => 'Deposit',
                'resources' => 'Named vehicles',
            ],
            default => [
                'duration' => 'Duration',
                'buffers' => 'Buffer before / after',
                'price' => 'Price',
                'deposit' => 'Deposit',
                'capacity' => 'Parallel capacity',
                'resources' => 'Named staff',
                'schedule' => 'Schedule override',
                'seasonal' => 'Seasonal pricing',
            ],
        };
    }

    /** Does this type expose the field? No stored list = engine standard set (all). */
    public function fieldEnabled(string $key): bool
    {
        $fields = $this->fields;

        return empty($fields) || in_array($key, $fields, true);
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn ($value, array $attrs) => Str::slug($value ?: ($attrs['name'] ?? '')),
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function defaultValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->defaults, $key, $default);
    }

    /** The three built-in engine cards the wizard always offers. */
    public static function builtins(): array
    {
        return [
            ['engine' => 'slot', 'name' => 'Appointment', 'icon' => '📅', 'hint' => 'Salon, barber, dentist, mechanic — time slots on a schedule.'],
            ['engine' => 'stay', 'name' => 'Stay',        'icon' => '🛏', 'hint' => 'Hotel rooms, houses — per-night, check-in to check-out.'],
            ['engine' => 'trip', 'name' => 'Trip',        'icon' => '🚌', 'hint' => 'Bus, car, transport — departures with seats.'],
        ];
    }
}
