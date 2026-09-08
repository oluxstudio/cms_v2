<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Site;

/**
 * Generated action items: scans a site's operational data and raises ONE
 * deduped Alert per thing that needs a human — an overdue invoice, a quote
 * nobody replied to, a booking still waiting for confirmation. Runs from the
 * hourly invoices:sweep loop; the dashboard's "Needs your attention" card
 * lists the unread ones.
 */
class OpsAlerts
{
    /** Alert types this service owns (the dashboard filters on these). */
    public const TYPES = ['invoice_overdue', 'estimate_stale', 'booking_pending'];

    public static function sweep(Site $site): void
    {
        $logger = app(TaskLogger::class);

        if ($site->hasFeature('invoices')) {
            $overdue = Invoice::where('site_id', $site->id)->where('status', 'overdue')->get();
            foreach ($overdue as $invoice) {
                $logger->alert($site,
                    "Invoice {$invoice->number} is overdue",
                    'invoice_overdue', 'warning',
                    ($invoice->customer_name ?: $invoice->customer_email).' owes '.number_format($invoice->total_cents / 100, 2).' — due '.$invoice->due_date?->toFormattedDateString().'.',
                    link: '/'.$site->name.'/invoices',
                    meta: ['invoice_id' => $invoice->id],
                    dedupeKey: 'invoice_overdue:'.$invoice->id,
                );
            }
        }

        if ($site->hasFeature('estimator')) {
            $stale = Estimate::where('site_id', $site->id)->where('status', 'new')
                ->where('created_at', '<=', now()->subDays(3))->get();
            foreach ($stale as $estimate) {
                $logger->alert($site,
                    "Quote {$estimate->reference} is awaiting a reply",
                    'estimate_stale', 'warning',
                    ($estimate->customer_name ?: 'A visitor')." asked {$estimate->created_at->diffForHumans()} and hasn't been contacted.",
                    link: '/'.$site->name.'/estimates',
                    meta: ['estimate_id' => $estimate->id],
                    dedupeKey: 'estimate_stale:'.$estimate->id,
                );
            }
        }

        if ($site->hasFeature('bookings')) {
            $pending = Booking::where('site_id', $site->id)->where('status', 'pending')
                ->where('starts_at', '>', now())->get();
            foreach ($pending as $booking) {
                $logger->alert($site,
                    'A booking is waiting for confirmation',
                    'booking_pending', 'info',
                    ($booking->customer_name ?: $booking->customer_email).' — '.$booking->starts_at->format('D j M, H:i').'.',
                    link: '/'.$site->name.'/bookings',
                    meta: ['booking_id' => $booking->id],
                    dedupeKey: 'booking_pending:'.$booking->id,
                );
            }
        }
    }
}
