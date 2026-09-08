<?php

namespace App\Models;

use App\Mail\NewOrderNotification;
use App\Mail\OrderConfirmed;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasUlids;

    /**
     * Lifecycle: pending → paid → shipped → delivered, with detours for
     * return_requested → returned (goods back, restocks) and refunded
     * (money back), or cancelled.
     */
    public const STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'return_requested', 'returned', 'refunded', 'cancelled'];

    protected $fillable = [
        'site_id', 'order_number', 'customer_email', 'customer_name', 'customer_phone', 'shipping_address',
        'fulfilment', 'delivery_notes', 'marketing_consent',
        'status', 'total_cents', 'vat_bp', 'vat_cents', 'currency', 'stripe_session_id', 'stripe_payment_intent',
        'public_token', 'courier_email', 'courier_token', 'courier_invited_at',
        'paid_at', 'shipped_at', 'delivered_at', 'returned_at', 'refunded_at',
    ];

    protected $casts = [
        'total_cents' => 'integer',
        'vat_bp' => 'integer',
        'vat_cents' => 'integer',
        'marketing_consent' => 'boolean',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
        'refunded_at' => 'datetime',
        'courier_invited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->public_token ??= Str::random(40);
            $order->order_number ??= self::nextNumber($order->site_id);
        });
    }

    /** Human-readable per-site sequential number: OLX-1001, OLX-1002… (Invoice idiom). */
    public static function nextNumber(string $siteId): string
    {
        $n = (int) static::where('site_id', $siteId)->count() + 1001;
        while (static::where('site_id', $siteId)->where('order_number', 'OLX-'.$n)->exists()) {
            $n++;
        }

        return 'OLX-'.$n;
    }

    /** The number shown to humans — legacy rows fall back to the ULID tail. */
    public function displayNumber(): string
    {
        return $this->order_number ?: '#'.substr((string) $this->id, -8);
    }

    /** "Includes VAT (20%) · £4.00", or null when no VAT was snapshotted. */
    public function vatLabel(): ?string
    {
        if ((int) $this->vat_cents <= 0) {
            return null;
        }

        return 'Includes VAT ('.rtrim(rtrim(number_format($this->vat_bp / 100, 2), '0'), '.').'%) · '
            .Money::format((int) $this->vat_cents, $this->currency);
    }

    /** Public, tokened order-status page (linked from the confirmation email). */
    public function statusUrl(): string
    {
        return url('preview/'.$this->site->name.'/order/'.$this->public_token);
    }

    /** The invited courier's tokened delivery page. */
    public function courierUrl(): ?string
    {
        return $this->courier_token ? url('preview/'.$this->site->name.'/deliver/'.$this->courier_token) : null;
    }

    /** Legacy rows may still say "fulfilled" — read it as delivered. */
    public function displayStatus(): string
    {
        return $this->status === 'fulfilled' ? 'delivered' : (string) $this->status;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Full step history, oldest first. */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** Append a step to the order's history. */
    public function recordEvent(string $status, ?string $userId = null, ?string $note = null): void
    {
        $this->events()->create([
            'site_id' => $this->site_id,
            'status' => $status,
            'note' => $note,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public function formattedTotal(): string
    {
        return Money::format((int) $this->total_cents, $this->currency);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'shipped', 'delivered', 'fulfilled'], true);
    }

    /** Mark paid, decrement stock (once), sync a CRM contact for the buyer. */
    public function markPaid(?string $paymentIntent = null): void
    {
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status' => 'paid',
            'stripe_payment_intent' => $paymentIntent,
            'paid_at' => now(),
        ]);

        $this->recordEvent('paid');
        $this->applyStock(-1);
        $this->syncContact();

        // Confirmation emails: buyer (with the tracking link) + site owner.
        try {
            if ($site = $this->site) {
                if (filled($this->customer_email)) {
                    Mail::to($this->customer_email)->send(new OrderConfirmed($this, $site));
                }
                if ($owner = $site->user?->email) {
                    Mail::to($owner)->send(new NewOrderNotification($this, $site));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Walk the lifecycle: stamps the matching timestamp, decrements stock on
     * first entry into a paid state, restocks when a paid order is cancelled.
     */
    public function transitionTo(string $status, ?string $userId = null, ?string $note = null): void
    {
        if (! in_array($status, self::STATUSES, true) || $this->status === $status) {
            return;
        }
        $wasPaid = $this->isPaid();
        $wasReturned = $this->returned_at !== null;
        // Stock came off the shelf if the order was ever paid — even when the
        // current status is a detour (return_requested / returned / refunded).
        $hadPayment = $wasPaid || $this->paid_at !== null;

        $this->update(array_filter([
            'status' => $status,
            'paid_at' => in_array($status, ['paid', 'shipped', 'delivered'], true) ? ($this->paid_at ?? now()) : null,
            'shipped_at' => $status === 'shipped' ? ($this->shipped_at ?? now())
                : ($status === 'delivered' ? $this->shipped_at : null),
            'delivered_at' => $status === 'delivered' ? ($this->delivered_at ?? now()) : null,
            'returned_at' => $status === 'returned' ? ($this->returned_at ?? now()) : null,
            'refunded_at' => $status === 'refunded' ? ($this->refunded_at ?? now()) : null,
        ], fn ($v, $k) => $k === 'status' || $v !== null, ARRAY_FILTER_USE_BOTH));

        $this->recordEvent($status, $userId, $note);

        if (! $wasPaid && $this->isPaid()) {
            $this->applyStock(-1);
            $this->syncContact();
        } elseif ($hadPayment && $status === 'cancelled' && ! $wasReturned) {
            $this->applyStock(+1); // paid stock comes back on cancellation
        } elseif ($hadPayment && $status === 'returned' && ! $wasReturned) {
            $this->applyStock(+1, 'return_restock'); // goods are back on the shelf
        }
    }

    /** Adjust each line's product inventory by direction × qty (null = unlimited). */
    protected function applyStock(int $direction, ?string $reason = null): void
    {
        foreach ($this->items()->with('product')->get() as $item) {
            $item->product?->adjustStock(
                $direction * (int) $item->qty,
                $reason ?? ($direction < 0 ? 'sale' : 'cancel_restock'),
                $this->id,
            );
        }
    }

    /** Upsert a CRM contact for the buyer and log the purchase on their timeline. */
    protected function syncContact(): void
    {
        if (blank($this->customer_email)) {
            return;
        }

        $contact = Contact::firstOrCreate(
            ['site_id' => $this->site_id, 'email' => $this->customer_email],
            ['name' => $this->customer_name ?: $this->customer_email, 'status' => 'won', 'last_activity_at' => now()],
        );

        // Backfill the phone and record marketing consent (UK GDPR: only when
        // the buyer actively ticked the box — never overwritten to false).
        $updates = ['last_activity_at' => now()];
        if (blank($contact->phone) && filled($this->customer_phone)) {
            $updates['phone'] = $this->customer_phone;
        }
        if ($this->marketing_consent) {
            $updates['data'] = array_merge($contact->data ?? [], [
                'marketing_consent' => true,
                'consented_at' => now()->toDateTimeString(),
            ]);
        }
        $contact->update($updates);
        if ($contact->wasRecentlyCreated) {
            $contact->logActivity('created');
        }

        $contact->logActivity('order_paid', null, [
            'order_id' => $this->id,
            'total' => $this->formattedTotal(),
        ], null);
    }
}
