<?php

namespace App\Payments;

use App\Models\Invoice;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\BookingNotifications;
use App\Services\TaskLogger;
use App\Support\Money;

/**
 * Applies a verified payment webhook to whichever record it belongs to —
 * order, donation, invoice or booking (identified by metadata). Used by the
 * consolidated Connect webhook; mirrors the per-flow webhook handlers.
 */
class SitePaymentFulfilment
{
    public function apply(Site $site, WebhookEvent $event): void
    {
        if (! in_array($event->kind, [WebhookEventKind::Completed, WebhookEventKind::Expired], true)) {
            return;
        }
        $completed = $event->kind === WebhookEventKind::Completed;
        $m = $event->metadata;

        if ($id = $m['order_id'] ?? null) {
            $order = $site->orders()->find($id);
            if ($order && $completed) {
                $order->update([
                    'customer_email' => $order->customer_email ?: $event->payerEmail,
                    'customer_name' => $order->customer_name ?: $event->payerName,
                    'customer_phone' => $order->customer_phone ?: $event->payerPhone,
                    'shipping_address' => $order->shipping_address ?: $event->shippingAddress,
                ]);
                $order->markPaid($event->paymentRef);
                app(TaskLogger::class)->alert($site,
                    'New order '.$order->formattedTotal().' — '.$order->items()->count().' item(s)',
                    'order', 'success', 'From '.($order->customer_name ?: $order->customer_email ?: 'a customer'),
                    null, 'all', url($site->name.'/orders'));
            }

            return;
        }

        if ($id = $m['donation_id'] ?? null) {
            $donation = $site->donations()->find($id);
            if ($donation && $completed) {
                $donation->update([
                    'donor_email' => $donation->donor_email ?: $event->payerEmail,
                    'donor_name' => $donation->donor_name ?: $event->payerName,
                ]);
                $donation->markPaid();
                try {
                    app(TaskLogger::class)->alert($site,
                        'New donation — '.Money::format((int) $donation->amount_cents, $donation->currency ?? 'gbp'),
                        'donation', 'success',
                        'From '.($donation->donor_name ?: $donation->donor_email ?: 'an anonymous donor'),
                        null, 'all', url($site->name.'/donations'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return;
        }

        if ($id = $m['invoice_id'] ?? null) {
            $invoice = Invoice::where('site_id', $site->id)->find($id);
            if ($invoice && $completed && $invoice->status !== 'paid') {
                $invoice->markPaid();
                try {
                    app(TaskLogger::class)->alert($site,
                        'Invoice '.$invoice->number.' paid — '.Money::format((int) $invoice->total_cents, $invoice->currency),
                        'invoice', 'success', 'From '.($invoice->customer_name ?: $invoice->customer_email),
                        null, 'all', url($site->name.'/invoices'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return;
        }

        if ($id = $m['booking_id'] ?? null) {
            $booking = $site->bookings()->find($id);
            if (! $booking || $booking->status !== 'awaiting_payment') {
                return;
            }
            if ($completed) {
                $booking->update(['paid_cents' => (int) ($m['charge_cents'] ?? $booking->total_cents)]);
                $booking->markConfirmed();
                try {
                    ActivityLogger::bookingEvent($booking->fresh('service'), 'confirmed');
                } catch (\Throwable $e) {
                    report($e);
                }
                app(BookingNotifications::class)->send($booking->fresh('service'), $site, confirmed: true);
            } else {
                $booking->markCancelled(); // hold released
            }
        }
    }
}
