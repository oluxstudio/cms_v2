<?php

namespace App\Modules\Memberships;

use App\Models\Site;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A membership level: free (price 0) or paid monthly / yearly. */
class MembershipTier extends Model
{
    use HasUlids;

    protected $fillable = ['site_id', 'name', 'slug', 'description', 'benefits', 'price_cents', 'interval', 'currency', 'active', 'sort'];

    protected $casts = ['benefits' => 'array', 'active' => 'boolean', 'price_cents' => 'integer', 'sort' => 'integer'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'tier_id');
    }

    public function isFree(): bool
    {
        return (int) $this->price_cents === 0;
    }

    /** "Free" · "£5.00 / month" */
    public function priceLabel(): string
    {
        return $this->isFree() ? 'Free' : Money::format((int) $this->price_cents, $this->currency).' / '.$this->interval;
    }

    /** A slug unique within the site. */
    public static function uniqueSlug(string $siteId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tier';
        $slug = $base;
        $i = 2;
        while (static::where('site_id', $siteId)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Public JSON shape. */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'benefits' => array_values((array) $this->benefits),
            'price_cents' => (int) $this->price_cents,
            'price' => $this->priceLabel(),
            'interval' => $this->interval,
            'currency' => $this->currency,
            'free' => $this->isFree(),
        ];
    }
}
