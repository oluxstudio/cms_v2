<?php

namespace App\Http\Controllers;

use App\Models\SitePaymentSettings;
use App\Payments\Drivers\StripeConnectGateway;
use App\Payments\SitePaymentFulfilment;
use Illuminate\Http\Request;

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
            $fulfilment->apply($site, StripeConnectGateway::toWebhookEvent($event));
        }

        return response()->json(['received' => true]);
    }
}
