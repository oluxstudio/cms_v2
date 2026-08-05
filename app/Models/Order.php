<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'paid', 'fulfilled', 'cancelled'];

    protected $fillable = [
        'site_id', 'customer_email', 'customer_name', 'status', 'total_cents',
        'currency', 'stripe_session_id', 'stripe_payment_intent', 'paid_at',
    ];

    protected $casts = [
        'total_cents' => 'integer',
        'paid_at'     => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function formattedTotal(): string
    {
        return \App\Support\Money::format((int) $this->total_cents, $this->currency);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'fulfilled'], true);
    }

    /** Mark paid + (optionally) sync a CRM contact for the buyer. */
    public function markPaid(?string $paymentIntent = null): void
    {
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status'                => 'paid',
            'stripe_payment_intent' => $paymentIntent,
            'paid_at'               => now(),
        ]);

        $this->syncContact();
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

        if (! $contact->wasRecentlyCreated) {
            $contact->update(['last_activity_at' => now()]);
        } else {
            $contact->logActivity('created');
        }

        $contact->logActivity('order_paid', null, [
            'order_id' => $this->id,
            'total'    => $this->formattedTotal(),
        ], null);
    }
}
