<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    public const KINDS = ['slot', 'stay', 'trip'];

    protected $fillable = [
        'site_id', 'name', 'slug', 'kind', 'booking_type_id', 'config', 'description', 'duration_min',
        'price_cents', 'requires_payment', 'deposit_cents', 'deposit_pct', 'capacity', 'currency', 'is_active', 'sort',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'duration_min'     => 'integer',
        'price_cents'      => 'integer',
        'config'           => 'array',
        'requires_payment' => 'boolean',
        'capacity'         => 'integer',
        'deposit_cents'    => 'integer',
        'deposit_pct'      => 'integer',
    ];

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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(ServiceDeparture::class);
    }

    /**
     * Named SITE-LEVEL resources attached to this service (staff / rooms /
     * vehicles, shared across services) — empty = anonymous capacity.
     */
    public function resources(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ServiceResource::class, 'resource_service', 'service_id', 'resource_id');
    }

    public function activeResources()
    {
        return $this->resources()->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    public function usesResources(): bool
    {
        return $this->activeResources()->exists();
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    /** Buffer minutes around slot bookings (prep / cleanup). */
    public function bufferBefore(): int
    {
        return max(0, (int) $this->configValue('buffer_before', 0));
    }

    public function bufferAfter(): int
    {
        return max(0, (int) $this->configValue('buffer_after', 0));
    }

    /** Per-kind config value (services.config JSON), with a default. */
    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function bookingType(): BelongsTo
    {
        return $this->belongsTo(BookingType::class);
    }

    /** Custom type name, falling back to the engine's label. */
    public function typeLabel(): string
    {
        return $this->bookingType?->name
            ?? ['slot' => 'Appointment', 'stay' => 'Stay', 'trip' => 'Trip'][$this->kind] ?? ucfirst($this->kind);
    }

    public function typeIcon(): string
    {
        return $this->bookingType?->icon
            ?? ['slot' => '📅', 'stay' => '🛏', 'trip' => '🚌'][$this->kind] ?? '📅';
    }

    /**
     * Owner-defined CUSTOM booking-form fields (on top of the basic
     * name/email/phone/message): [{key, label, type: text|textarea, required}].
     */
    public function formFields(): array
    {
        return array_values(array_filter(
            (array) $this->configValue('form_fields', []),
            fn ($f) => is_array($f) && ($f['key'] ?? '') !== '' && ($f['label'] ?? '') !== '',
        ));
    }

    /** Does this service's type expose a parameter field? No type = all. */
    public function typeEnabled(string $key): bool
    {
        return $this->bookingType?->fieldEnabled($key) ?? true;
    }

    /** What this service calls its resources (staff / room / boat…). */
    public function resourceNoun(): string
    {
        return $this->bookingType?->resource_noun ?: ServiceResource::noun($this->kind);
    }

    /**
     * The amount to charge ONLINE for a booking of $totalCents:
     * the deposit (fixed or %) when set, else the full total. Never > total.
     */
    public function depositCentsFor(int $totalCents): int
    {
        $deposit = match (true) {
            ($this->deposit_cents ?? 0) > 0 => $this->deposit_cents,
            ($this->deposit_pct ?? 0) > 0   => (int) round($totalCents * $this->deposit_pct / 100),
            default                         => $totalCents,
        };

        return max(0, min($deposit, $totalCents));
    }

    public function hasDeposit(): bool
    {
        return ($this->deposit_cents ?? 0) > 0 || ($this->deposit_pct ?? 0) > 0;
    }

    public function isFree(): bool
    {
        return $this->price_cents <= 0;
    }

    public function formattedPrice(): string
    {
        return \App\Support\Money::format((int) $this->price_cents, $this->currency, free: true);
    }
}
