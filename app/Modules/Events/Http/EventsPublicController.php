<?php

namespace App\Modules\Events\Http;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Events\Models\Ticket;
use App\Modules\Events\Models\TicketOrder;
use App\Modules\Events\TicketFulfilment;
use App\Payments\PaymentManager;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use Illuminate\Http\Request;

/** Hosted ticket page (signed link) and the after-checkout success page. */
class EventsPublicController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    private function site(Request $request, string $siteName): Site
    {
        return $request->attributes->get('resolvedSite') ?? Site::where('name', $siteName)->firstOrFail();
    }

    public function ticket(Request $request, string $siteName, string $code)
    {
        abort_unless($request->hasValidSignature(), 403);
        $site = $this->site($request, $siteName);
        $ticket = Ticket::where('code', $code)->with(['order', 'event', 'ticketType'])->firstOrFail();
        abort_unless($ticket->order && $ticket->order->site_id === $site->id, 404);

        return view('events-ticket', ['site' => $site, 'ticket' => $ticket, 'order' => $ticket->order, 'event' => $ticket->event]);
    }

    public function success(Request $request, string $siteName, string $order)
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['session_id']), 403);
        $site = $this->site($request, $siteName);
        $model = TicketOrder::where('site_id', $site->id)->findOrFail($order);

        // Webhooks don't reach local/dev hosts (or the advanced own-keys driver):
        // confirm with the gateway directly so the order never sticks on pending.
        $sessionId = (string) ($model->checkout_session_id ?: $request->query('session_id'));
        if ($model->status === 'pending' && $sessionId !== '' && ! str_contains($sessionId, '{')) {
            try {
                $gateway = $this->payments->for($site);
                $details = $gateway->checkoutDetails($site, $sessionId);
                $paid = $details ? $details->isPaid : $gateway->checkoutIsPaid($site, $sessionId);
                if ($paid) {
                    app(TicketFulfilment::class)->handle($site, new WebhookEvent(
                        WebhookEventKind::Completed, $sessionId, ['ticket_order_id' => $model->id],
                        $details?->payerEmail, $details?->payerName, $details?->paymentRef, true,
                    ), true);
                    $model->refresh();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('events-success', [
            'site' => $site,
            'order' => $model,
            'event' => $model->event,
            'tickets' => $model->status === 'paid' ? $model->tickets()->with('ticketType')->get() : collect(),
        ]);
    }
}
