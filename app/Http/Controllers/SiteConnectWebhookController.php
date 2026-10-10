<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Modules\Memberships\MembershipBilling;
use App\Payments\Drivers\StripeConnectGateway;
use App\Payments\SitePaymentFulfilment;
use Illuminate\Http\Request;
use Stripe\Event;
use Stripe\StripeObject;

/**
 * The single platform Connect webhook: Stripe sends every connected-account
 * event here with the account id attached; we resolve which site it belongs
 * to and fulfil whatever was paid for.
 */
class SiteConnectWebhookController extends Controller
{
    public function __invoke(Request $request, SitePaymentFulfilment $fulfilment)
    {
        try {
            $event = StripeConnectGateway::constructPlatformEvent(
                $request->getContent(), $request->header('Stripe-Signature'),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $site = SitePaymentSettings::where('connect_account_id', (string) ($event->account ?? ''))
            ->first()?->site;
        if ($site) {
            $this->applyRecurring($site, $event);
            $fulfilment->apply($site, StripeConnectGateway::toWebhookEvent($event));
        }

        return response()->json(['received' => true]);
    }

    /**
     * Recurring billing (Memberships): subscription checkouts, renewals,
     * failed payments and cancellations. Never blocks the one-off flows.
     */
    private function applyRecurring(Site $site, Event $event): void
    {
        if (! in_array($event->type, StripeConnectGateway::SUBSCRIPTION_EVENTS, true)
            || ! class_exists(MembershipBilling::class)) {
            return;
        }
        try {
            $object = $event->data->object;
            app(MembershipBilling::class)->applyStripeEvent($site, (string) $event->type, $object instanceof StripeObject ? $object->toArray() : (array) $object);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
