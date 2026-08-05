<?php

namespace App\Http\Controllers;

use App\Services\StripeConnect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplateCommerceController extends Controller
{
    public function __construct(private StripeConnect $connect) {}

    /** Stripe platform webhook (signature-verified, CSRF-exempt). */
    public function webhook(Request $request)
    {
        if (! $this->connect->configured()) {
            return response('ok', 200);
        }

        try {
            $event = $this->connect->verifyWebhook($request->getContent(), $request->header('Stripe-Signature', ''));
        } catch (\Throwable $e) {
            return response('invalid signature', 400);
        }

        $this->connect->handleEvent($event->type, $event->data->object ?? []);

        return response('ok', 200);
    }

    /** Start Stripe Connect onboarding for the current user (creator payouts). */
    public function connect(Request $request)
    {
        if (! $this->connect->configured()) {
            return redirect()->route('my.templates')->with('error', 'Payments are not configured.');
        }

        $url = $this->connect->onboardingLink(
            Auth::user(),
            route('creator.connect.return'),
            route('creator.connect'),
        );

        return redirect()->away($url);
    }

    /** Return from Stripe onboarding — refresh the account's charges-enabled status. */
    public function connectReturn(Request $request)
    {
        $this->connect->syncAccount(Auth::user());

        return redirect()->route('my.templates');
    }

    /** Buyer returns after a successful checkout (entitlement is granted by webhook). */
    public function checkoutSuccess(Request $request)
    {
        $site = $request->query('site');

        return redirect($site ? url($site.'/marketplace') : route('templates'))
            ->with('status', 'Purchase complete — you can now install the template.');
    }

    public function checkoutCancel(Request $request)
    {
        $site = $request->query('site');

        return redirect($site ? url($site.'/marketplace') : route('templates'));
    }
}
