<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'site_id', 'name', 'slug', 'description', 'price_cents',
        'currency', 'image', 'is_active', 'inventory', 'sort',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'price_cents' => 'integer',
        'inventory'   => 'integer',
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

    public function formattedPrice(): string
    {
        return \App\Support\Money::format((int) $this->price_cents, $this->currency);
    }

    public function priceMajor(): float
    {
        return round($this->price_cents / 100, 2);
    }
}
