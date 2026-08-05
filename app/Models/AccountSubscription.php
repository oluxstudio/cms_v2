<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's platform subscription (config/plans.php tier + trial state). */
class AccountSubscription extends Model
{
    protected $fillable = [
        'user_id', 'plan', 'status', 'trial_ends_at', 'started_at',
        'stripe_customer_id', 'stripe_subscription_id', 'price_overrides',
    ];

    protected $casts = ['trial_ends_at' => 'datetime', 'started_at' => 'datetime', 'price_overrides' => 'array'];

    /** Monthly price for a tier IN CENTS — the client's custom price when set. */
    public function priceFor(string $plan): int
    {
        $override = $this->price_overrides[$plan] ?? null;

        return $override !== null ? (int) $override : (int) (config("plans.tiers.{$plan}.price_cents") ?? 0);
    }

    /** Whether the platform admin gave this client a custom price for a tier. */
    public function hasOverride(string $plan): bool
    {
        return isset($this->price_overrides[$plan]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,mixed> the tier definition (falls back to trial). */
    public function tier(): array
    {
        return config("plans.tiers.{$this->plan}") ?? config('plans.tiers.trial');
    }

    public function onTrial(): bool
    {
        return $this->plan === 'trial' && $this->status === 'trialing';
    }

    public function trialExpired(): bool
    {
        return $this->onTrial() && $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    public function trialDaysLeft(): int
    {
        return $this->trial_ends_at ? max(0, (int) ceil(now()->diffInDays($this->trial_ends_at, false))) : 0;
    }

    /** Short badge label: "Free Trial · 12d left" / "Pro". */
    public function badgeLabel(): string
    {
        if ($this->onTrial()) {
            return $this->trialExpired()
                ? 'Trial expired'
                : 'Free Trial · '.$this->trialDaysLeft().'d left';
        }

        return $this->tier()['name'];
    }
}
