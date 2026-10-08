<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasUlids;

    protected $fillable = [
        'site_id', 'name', 'slug', 'description', 'category', 'tags', 'price_cents',
        'currency', 'image', 'is_active', 'reviews_enabled', 'inventory', 'sort',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_cents' => 'integer',
        'tags' => 'array',
        'reviews_enabled' => 'boolean',
        'inventory' => 'integer',
    ];

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn ($value, array $attrs) => Str::slug($value ?: ($attrs['name'] ?? '')),
        );
    }

    /**
     * The product picture as a usable URL. `image` holds what the asset
     * picker gave (an "@media/…" library reference, or any pasted URL); older
     * products hold a bare storage path from the retired direct upload.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            $v = trim((string) $this->image);
            if ($v === '') {
                return null;
            }

            if (str_starts_with($v, '@media/')) {
                return Media::resolveRef($this->site_id, $v) ?: null;
            }

            return Str::startsWith($v, ['/', 'http://', 'https://', 'data:']) ? $v : Storage::url($v);
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function formattedPrice(): string
    {
        return Money::format((int) $this->price_cents, $this->currency);
    }

    /** True when qty can be sold (null inventory = unlimited). */
    public function inStock(int $qty = 1): bool
    {
        return $this->inventory === null || $this->inventory >= $qty;
    }

    /**
     * Adjust stock by a delta (never below 0); unlimited stays unlimited.
     * Every change is recorded in the product's inventory history.
     */
    public function adjustStock(int $delta, string $reason = 'manual', ?string $orderId = null, ?string $userId = null): void
    {
        if ($this->inventory === null || $delta === 0) {
            return;
        }
        $after = max(0, $this->inventory + $delta);
        $this->update(['inventory' => $after]);
        $this->stockMovements()->create([
            'site_id' => $this->site_id,
            'delta' => $delta,
            'stock_after' => $after,
            'reason' => $reason,
            'order_id' => $orderId,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public function stockMovements()
    {
        return $this->hasMany(ProductStockMovement::class)->latest('created_at')->latest('id');
    }

    public function events()
    {
        return $this->hasMany(ProductEvent::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function priceMajor(): float
    {
        return round($this->price_cents / 100, 2);
    }
}
