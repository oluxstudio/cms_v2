<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Payments\CheckoutLine;
use App\Payments\CheckoutRequest;
use App\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Donations public API — lets CLIENT SITES host the donate form themselves.
 *
 *   GET  /api/sites/{site}/donate/config    → headline + suggested amounts
 *   POST /api/sites/{site}/donate/checkout  → Donation + Stripe checkout_url
 *
 * Payment completion is confirmed by the existing donate webhook/success
 * routes — this is only the JSON front door for the same flow.
 */
class DonationApiController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    private function site(string $siteName): Site
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        abort_unless($site->hasFeature('donations'), 404);

        return $site;
    }

    public function config(string $siteName): JsonResponse
    {
        $site = $this->site($siteName);
        $config = $site->feature('donations');

        return response()->json([
            'headline' => $config['headline'] ?? 'Support our work',
            'currency' => strtolower((string) ($site->currency ?? 'gbp')),
            'suggested' => collect(explode(',', (string) ($config['suggested_amounts'] ?? '5,10,25,50')))
                ->map(fn ($v) => (int) trim($v))->filter()->values()->all(),
            'available' => $this->payments->for($site)->available($site),
        ]);
    }

    public function checkout(string $siteName, Request $request): JsonResponse
    {
        $site = $this->site($siteName);
        $currency = $site->currency ?? 'gbp';

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $gateway = $this->payments->for($site);
        if (! $gateway->available($site)) {
            return response()->json(['message' => 'Donations are not available right now.'], 422);
        }

        $cents = (int) round(((float) $data['amount']) * 100);

        $donation = $site->donations()->create([
            'donor_email' => $data['email'] ?? null,
            'donor_name' => $data['name'] ?? null,
            'amount_cents' => $cents,
            'currency' => $currency,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        try {
            $session = $gateway->createCheckout($site, new CheckoutRequest(
                lines: [new CheckoutLine('Donation to '.ucwords(str_replace('-', ' ', $site->name)), $cents, $currency)],
                successUrl: url('preview/'.$site->name.'/donate/success').'?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: (string) ($request->input('return_url') ?: url('preview/'.$site->name.'/donate')),
                metadata: ['donation_id' => $donation->id, 'site_id' => $site->id],
                customerEmail: $data['email'] ?? null,
            ));
        } catch (\Throwable $e) {
            Log::error('Donation checkout failed', ['site' => $site->id, 'msg' => $e->getMessage()]);
            $donation->delete();

            return response()->json(['message' => 'Could not start the donation. Please try again later.'], 502);
        }

        $donation->update(['stripe_session_id' => $session->id]);

        return response()->json(['checkout_url' => $session->url], 201);
    }
}
