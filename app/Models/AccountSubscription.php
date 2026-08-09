<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's platform subscription (config/plans.php tier + trial state). */
class AccountSubscription extends Model
{
    use HasUlids;

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

    /* ── Plan gate: limits from config/plans.php ───────────────────────── */

    /** Max sites this plan allows (null = unlimited). */
    public function sitesLimit(): ?int
    {
        $limits = $this->tier()['limits'] ?? [];

        // array_key_exists (not ??) — a null value means UNLIMITED (Enterprise).
        return array_key_exists('sites', $limits) ? $limits['sites'] : 1;
    }

    /** Does this plan unlock premium modules (store, bookings, invoices, …)? */
    public function allowsPremium(): bool
    {
        return (bool) ($this->tier()['limits']['premium'] ?? false);
    }

    /** May the owner create another site? (respects unlimited + expired trial) */
    public function canCreateSite(): bool
    {
        if ($this->trialExpired()) {
            return false;
        }
        $limit = $this->sitesLimit();

        return $limit === null || $this->user->sites()->count() < $limit;
    }

    /** Human sites-usage string, e.g. "1 / 1" or "3 / ∞". */
    public function sitesUsage(): string
    {
        $limit = $this->sitesLimit();

        return $this->user->sites()->count().' / '.($limit === null ? '∞' : $limit);
    }

    /* ── Storage (asset disk space) ────────────────────────────────────── */

    /** Storage cap in MB for this plan (null = unlimited). */
    public function storageLimitMb(): ?int
    {
        $limits = $this->tier()['limits'] ?? [];

        return array_key_exists('storage_mb', $limits) ? $limits['storage_mb'] : 20;
    }

    public function storageLimitBytes(): ?int
    {
        $mb = $this->storageLimitMb();

        return $mb === null ? null : $mb * 1024 * 1024;
    }

    /** Bytes used across every asset in the account's sites. */
    public function storageUsedBytes(): int
    {
        return (int) Media::whereIn('site_id', $this->user->sites()->select('id'))->sum('bytes');
    }

    /** Bytes still free (null = unlimited). */
    public function storageRemainingBytes(): ?int
    {
        $limit = $this->storageLimitBytes();

        return $limit === null ? null : max(0, $limit - $this->storageUsedBytes());
    }

    /** Can the account absorb an upload of $bytes more? */
    public function canStore(int $bytes): bool
    {
        $remaining = $this->storageRemainingBytes();

        return $remaining === null || $bytes <= $remaining;
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
