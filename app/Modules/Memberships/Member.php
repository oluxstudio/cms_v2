<?php

namespace App\Modules\Memberships;

use App\Models\Site;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A site visitor who joined a membership tier. */
class Member extends Model
{
    use HasUlids;

    public const STATUSES = ['active', 'pending', 'past_due', 'cancelled'];

    protected $fillable = [
        'site_id', 'tier_id', 'name', 'email', 'status', 'price_cents', 'interval', 'currency',
        'joined_at', 'renews_at', 'cancelled_at', 'cancel_at_period_end',
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_checkout_id', 'notes', 'last_login_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime', 'renews_at' => 'datetime', 'cancelled_at' => 'datetime', 'last_login_at' => 'datetime',
        'cancel_at_period_end' => 'boolean', 'price_cents' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'tier_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MembershipEvent::class)->orderByDesc('created_at');
    }

    public function isPaid(): bool
    {
        return (int) $this->price_cents > 0;
    }

    /** Can see members-only content (past-due keeps access during Stripe's retry window). */
    public function hasAccess(): bool
    {
        return in_array($this->status, ['active', 'past_due'], true);
    }

    /** Monthly-normalised recurring revenue, in cents. */
    public function monthlyCents(): int
    {
        if (! $this->isPaid() || $this->status !== 'active') {
            return 0;
        }

        return $this->interval === 'year' ? (int) round($this->price_cents / 12) : (int) $this->price_cents;
    }

    public function priceLabel(): string
    {
        return $this->isPaid() ? Money::format((int) $this->price_cents, $this->currency).' / '.$this->interval : 'Free';
    }

    public function log(string $type, ?string $detail = null): void
    {
        MembershipEvent::create([
            'site_id' => $this->site_id, 'member_id' => $this->id, 'type' => $type,
            'detail' => $detail !== null ? mb_substr($detail, 0, 255) : null, 'created_at' => now(),
        ]);
    }

    /** What the member's own /me endpoint returns. */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'active' => $this->hasAccess(),
            'tier' => $this->tier ? ['id' => $this->tier->id, 'name' => $this->tier->name, 'slug' => $this->tier->slug] : null,
            'price' => $this->priceLabel(),
            'joined_at' => $this->joined_at?->toIso8601String(),
            'renews_at' => $this->renews_at?->toIso8601String(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
        ];
    }
}
