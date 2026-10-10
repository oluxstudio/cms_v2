<?php

namespace App\Models;

use App\Support\PlanCatalog;
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
        'mailbox_limit_override', 'extra_mailboxes', 'mailbox_addon_item_id', 'grandfathered_mailboxes',
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

    /**
     * The tier whose RULES apply right now (falls back to trial). An ended
     * trial or a cancelled/expired paid plan gets the Free plan's limits —
     * own domain, bookings, invoices, mailboxes… — until a plan is chosen.
     * Nothing is deleted; new usage is simply held to Free.
     *
     * @return array<string,mixed>
     */
    public function tier(): array
    {
        if ($this->lapsed() && ($free = PlanCatalog::lapsedTier())) {
            return $free;
        }

        return config("plans.tiers.{$this->plan}") ?? config('plans.tiers.trial');
    }

    /** Trial over, or the paid plan was cancelled / expired — no plan is paying. */
    public function lapsed(): bool
    {
        return $this->trialExpired() || in_array($this->status, ['expired', 'cancelled'], true);
    }

    /* ── Plan gate: limits from config/plans.php ───────────────────────── */

    /** Max sites this plan allows (null = unlimited). */
    /**
     * Mailboxes a tier includes: an int, or null = "set per account" (Enterprise).
     * Trial counts as Free — no paid mailboxes for unpaid accounts.
     */
    public static function planMailboxes(string $plan): ?int
    {
        if ($plan === 'trial') {
            return 0;
        }
        $limits = config("plans.tiers.{$plan}.limits") ?? [];

        return array_key_exists('mailboxes', $limits) ? ($limits['mailboxes'] === null ? null : (int) $limits['mailboxes']) : 0;
    }

    /** Business email mailboxes this account may have: plan (or admin override) + purchased add-ons. */
    public function mailboxLimit(): int
    {
        if (in_array($this->status, ['expired', 'cancelled'], true)) {
            return 0;
        }

        return $this->mailboxLimitOn($this->plan);
    }

    /** The limit this account would have on $plan (keeps its admin override and add-ons). */
    public function mailboxLimitOn(string $plan): int
    {
        if ($plan === 'trial') {
            return 0; // unpaid accounts get no paid mailboxes
        }
        $base = $this->mailbox_limit_override ?? self::planMailboxes($plan) ?? 0;
        // Mailboxes an account already had when its plan's allowance shrank stay usable.
        $base = max((int) $base, (int) $this->grandfathered_mailboxes);

        return $base + (int) $this->extra_mailboxes;
    }

    /** Mailboxes that count toward the limit (everything not failed or on its way out). */
    public function mailboxesUsed(): int
    {
        return Mailbox::where('account_id', $this->user_id)->whereNotIn('status', ['failed', 'deleting'])->count();
    }

    /** Would this account's current mailboxes still fit on $plan? */
    public function mailboxesFit(string $plan): bool
    {
        return $this->mailboxesUsed() <= $this->mailboxLimitOn($plan);
    }

    /** May this plan use its own domain (Free is subdomain-only)? */
    public function allowsCustomDomain(): bool
    {
        return (bool) ($this->tier()['limits']['custom_domain'] ?? true);
    }

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

    /** May this account publish (and sell) templates on the marketplace? Business+. */
    public function allowsMarketplacePublishing(): bool
    {
        return (bool) ($this->tier()['limits']['marketplace'] ?? false);
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

    /* ── Plan limits added with the 2026 line-up (null = unlimited) ───── */

    /** A plan limit by key; $default when the plan doesn't define it. */
    public function limit(string $key, mixed $default = null): mixed
    {
        $limits = $this->tier()['limits'] ?? [];

        return array_key_exists($key, $limits) ? $limits[$key] : $default;
    }

    /** Ids of the sites this account owns (limits are counted across all of them). */
    private function siteIds()
    {
        return Site::where('user_id', $this->user_id)->select('id');
    }

    /** Online bookings made this calendar month (cancelled ones don't count). */
    public function bookingsThisMonth(): int
    {
        return Booking::whereIn('site_id', $this->siteIds())->where('created_at', '>=', now()->startOfMonth())
            ->where('status', '!=', 'cancelled')->count();
    }

    public function canTakeBooking(): bool
    {
        $cap = $this->limit('bookings_month');

        return $cap === null || $this->bookingsThisMonth() < (int) $cap;
    }

    /** Booking calendars (staff, rooms, …) across the account's sites. */
    public function staffCalendarsUsed(): int
    {
        return ServiceResource::whereIn('site_id', $this->siteIds())->count();
    }

    public function canAddStaffCalendar(): bool
    {
        $cap = $this->limit('staff_calendars');

        return $cap === null || $this->staffCalendarsUsed() < (int) $cap;
    }

    /** Invoices created this calendar month (repeats of a recurring invoice don't count). */
    public function invoicesThisMonth(): int
    {
        return Invoice::whereIn('site_id', $this->siteIds())->whereNull('parent_invoice_id')
            ->where('created_at', '>=', now()->startOfMonth())->count();
    }

    public function canCreateInvoice(): bool
    {
        $cap = $this->limit('invoices_month');

        return $cap === null || $this->invoicesThisMonth() < (int) $cap;
    }

    public function allowsDeposits(): bool
    {
        return (bool) $this->limit('deposits', true);
    }

    public function allowsRecurringInvoices(): bool
    {
        return (bool) $this->limit('recurring_invoices', true);
    }

    /** Platform fee on online payments, as a percentage (null = use the global default). */
    public function paymentFeePct(): ?float
    {
        $pct = $this->limit('payment_fee_pct');

        return $pct === null ? null : (float) $pct;
    }

    /** Does the live site carry the small "Made with Olux" badge? */
    public function showsBadge(): bool
    {
        return (bool) $this->limit('badge', false);
    }

    /** Does $plan (default: the current one) include a free first-year domain on $domain? */
    public static function planIncludesDomain(string $plan, string $domain): bool
    {
        $rule = config("plans.tiers.{$plan}.limits.free_domain");

        return match ($rule) {
            'any' => true,
            null, '', false => false,
            default => str_ends_with(strtolower($domain), '.'.ltrim((string) $rule, '.')),
        };
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
