<?php

namespace App\Modules\Events;

use App\Models\Site;
use App\Modules\Events\Models\TicketOrder;
use App\Payments\WebhookEvent;

/**
 * Payment handler registered in config/payments.php 'fulfilment' under the
 * `ticket_order_id` checkout metadata key. Completed → the order is paid,
 * tickets emailed, owner alerted. Expired → the held seats are released.
 */
class TicketFulfilment
{
    public function __construct(private EventTickets $tickets) {}

    public function handle(Site $site, WebhookEvent $event, bool $completed): void
    {
        $order = TicketOrder::where('site_id', $site->id)->find($event->metadata['ticket_order_id'] ?? null);
        if (! $order) {
            return;
        }

        if (! $completed) {
            $this->tickets->release($order);

            return;
        }

        $order->update([
            'buyer_email' => $order->buyer_email ?: (string) $event->payerEmail,
            'buyer_phone' => $order->buyer_phone ?: $event->payerPhone,
            'checkout_session_id' => $order->checkout_session_id ?: $event->sessionId,
        ]);
        // A payment that lands after its hold was swept is still honoured.
        $this->tickets->markPaid($site, $order, $event->paymentRef);
    }
}
