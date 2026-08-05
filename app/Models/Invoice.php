<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invoice: line items + tax, billed to a customer, payable online via a
 * tokenized public page (Stripe) or marked paid manually.
 *
 * status: draft | sent | paid | overdue | cancelled
 * ("overdue" is derived: sent + past due date + unpaid — refreshOverdue()).
 */
class Invoice extends Model
{
    public const RECUR_INTERVALS = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'site_id', 'number', 'public_token', 'customer_name', 'customer_email',
        'items', 'subtotal_cents', 'tax_bp', 'tax_cents', 'total_cents',
        'currency', 'status', 'due_date', 'notes', 'sent_at', 'paid_at',
        'stripe_session_id', 'opened_at', 'viewed_at', 'reminded_at',
        'reminders_sent', 'recur_interval', 'recur_next_on', 'parent_invoice_id',
    ];

    protected $casts = [
        'items'          => 'array',
        'subtotal_cents' => 'integer',
        'tax_bp'         => 'integer',
        'tax_cents'      => 'integer',
        'total_cents'    => 'integer',
        'due_date'       => 'date',
        'sent_at'        => 'datetime',
        'paid_at'        => 'datetime',
        'opened_at'      => 'datetime',
        'viewed_at'      => 'datetime',
        'reminded_at'    => 'datetime',
        'reminders_sent' => 'integer',
        'recur_next_on'  => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            $invoice->public_token ??= Str::random(40);
        });
    }

    /** Next per-site number: INV-0001, INV-0002, … */
    public static function nextNumber(Site $site): string
    {
        $n = (int) static::where('site_id', $site->id)->count() + 1;
        while (static::where('site_id', $site->id)->where('number', sprintf('INV-%04d', $n))->exists()) {
            $n++;
        }

        return sprintf('INV-%04d', $n);
    }

    /** Recompute money columns from the items + tax rate. */
    public function recalc(): void
    {
        $subtotal = collect($this->items ?? [])
            ->sum(fn ($i) => max(0, (int) ($i['qty'] ?? 1)) * max(0, (int) ($i['unit_cents'] ?? 0)));
        $tax = (int) round($subtotal * $this->tax_bp / 10000);
        $this->forceFill([
            'subtotal_cents' => $subtotal,
            'tax_cents'      => $tax,
            'total_cents'    => $subtotal + $tax,
        ]);
    }

    public function markPaid(): void
    {
        $this->update(['status' => 'paid', 'paid_at' => now()]);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['sent', 'overdue'], true) && $this->total_cents > 0;
    }

    /** Sweep: sent invoices past their due date become overdue. */
    public static function refreshOverdue(Site $site): void
    {
        static::where('site_id', $site->id)
            ->where('status', 'sent')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    public function scopeCollectible(Builder $q): Builder
    {
        return $q->whereIn('status', ['sent', 'overdue']);
    }

    public function isRecurring(): bool
    {
        return in_array($this->recur_interval, self::RECUR_INTERVALS, true);
    }

    /** Move the recurrence pointer one interval forward. */
    public function advanceRecurrence(): void
    {
        if (! $this->isRecurring()) {
            return;
        }
        $base = $this->recur_next_on ?? now();
        $this->update(['recur_next_on' => match ($this->recur_interval) {
            'weekly'    => $base->copy()->addWeek(),
            'quarterly' => $base->copy()->addMonths(3),
            'yearly'    => $base->copy()->addYear(),
            default     => $base->copy()->addMonth(),
        }]);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    /** Ordered lifecycle events for the admin timeline. */
    public function timeline(): array
    {
        return [
            ['label' => 'Created',  'at' => $this->created_at],
            ['label' => 'Sent',     'at' => $this->sent_at],
            ['label' => 'Opened',   'at' => $this->opened_at],
            ['label' => 'Viewed',   'at' => $this->viewed_at],
            ['label' => 'Paid',     'at' => $this->paid_at],
        ];
    }

    /**
     * THE automation sweep — overdue refresh, recurring generation, reminder
     * emails. Ran hourly by `invoices:sweep` (scheduler container) and lazily
     * from the admin page mount so dev environments behave without cron.
     */
    public static function sweep(Site $site): void
    {
        static::refreshOverdue($site);
        static::generateRecurring($site);
        static::sendReminders($site);
    }

    /** Recurring templates whose date has arrived spawn + send a fresh copy. */
    protected static function generateRecurring(Site $site): void
    {
        $dueDays = (int) ($site->feature('invoices')['due_days'] ?? 14);

        $templates = static::where('site_id', $site->id)
            ->whereIn('recur_interval', self::RECUR_INTERVALS)
            ->whereNotNull('recur_next_on')
            ->whereDate('recur_next_on', '<=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($templates as $tpl) {
            $child = static::create([
                'site_id'           => $site->id,
                'number'            => static::nextNumber($site),
                'customer_name'     => $tpl->customer_name,
                'customer_email'    => $tpl->customer_email,
                'items'             => $tpl->items,
                'tax_bp'            => $tpl->tax_bp,
                'currency'          => $tpl->currency,
                'status'            => 'sent',
                'due_date'          => now()->addDays($dueDays)->toDateString(),
                'notes'             => $tpl->notes,
                'sent_at'           => now(),
                'parent_invoice_id' => $tpl->id,
            ]);
            $child->recalc();
            $child->save();

            try {
                \Illuminate\Support\Facades\Mail::to($child->customer_email)
                    ->send(new \App\Mail\InvoiceSent($child, $site));
            } catch (\Throwable $e) {
                report($e);
            }

            $tpl->advanceRecurrence();
        }
    }

    /**
     * Polite nudges: once when the due date is near, then every 3 days while
     * overdue (max 3 reminders total).
     */
    protected static function sendReminders(Site $site): void
    {
        $lead = (int) ($site->feature('invoices')['remind_before_days'] ?? 3);

        $due = static::where('site_id', $site->id)->collectible()
            ->whereNotNull('due_date')
            ->where(function (Builder $q) use ($lead) {
                // Approaching due date, never reminded…
                $q->where(fn (Builder $w) => $w
                    ->where('reminders_sent', 0)
                    ->whereDate('due_date', '<=', now()->addDays($lead)->toDateString()));
                // …or overdue and last nudge ≥ 3 days ago.
                $q->orWhere(fn (Builder $w) => $w
                    ->where('status', 'overdue')
                    ->where('reminders_sent', '<', 3)
                    ->where(fn (Builder $v) => $v
                        ->whereNull('reminded_at')
                        ->orWhere('reminded_at', '<=', now()->subDays(3))));
            })
            ->get();

        foreach ($due as $invoice) {
            try {
                \Illuminate\Support\Facades\Mail::to($invoice->customer_email)
                    ->send(new \App\Mail\InvoiceReminder($invoice, $site));
                $invoice->update([
                    'reminded_at'    => now(),
                    'reminders_sent' => $invoice->reminders_sent + 1,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** All invoices this customer has with the site (the client portal list). */
    public function siblingInvoices()
    {
        return static::where('site_id', $this->site_id)
            ->where('customer_email', $this->customer_email)
            ->where('status', '!=', 'draft')
            ->orderByDesc('created_at')
            ->get();
    }

    public function portalUrl(): string
    {
        return url("preview/{$this->site->name}/billing/{$this->public_token}");
    }

    public function formattedTotal(): string
    {
        return \App\Support\Money::format((int) $this->total_cents, $this->currency);
    }

    public function payUrl(): string
    {
        return url("preview/{$this->site->name}/invoice/{$this->public_token}");
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
