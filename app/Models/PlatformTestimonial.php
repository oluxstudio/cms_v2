<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** A testimonial about Olux (landing page) — see the platform_testimonials migration. */
class PlatformTestimonial extends Model
{
    use HasUlids;

    public const STATUSES = ['pending', 'published', 'hidden'];

    protected $fillable = ['name', 'role', 'quote', 'rating', 'email', 'status', 'position', 'source', 'ip_address', 'published_at'];

    protected $casts = ['rating' => 'integer', 'position' => 'integer', 'published_at' => 'datetime'];

    /** Published, in landing order (position, then newest). */
    public function scopeLive($q)
    {
        return $q->where('status', 'published')->orderBy('position')->orderByDesc('published_at');
    }

    /** "Tunde Rahman" → "TR" for the avatar circle. */
    public function initials(): string
    {
        return Str::upper(collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '★';
    }

    /** How many to show: $requested clamped to 1–20, else the admin setting (default 5). */
    public static function landingLimit(?int $requested = null): int
    {
        return max(1, min(20, $requested ?? (int) config('landing.testimonials_count', 5)));
    }

    /**
     * The published testimonials the landing page shows, in admin order —
     * the ONE query behind both the landing carousel and GET /api/testimonials.
     */
    public static function forLanding(?int $limit = null): Collection
    {
        return static::live()->limit(self::landingLimit($limit))->get();
    }

    /** What the public may see — never the email or IP address. */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'quote' => $this->quote,
            'rating' => $this->rating,
            'initials' => $this->initials(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
