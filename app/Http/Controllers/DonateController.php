<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\Stripe\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DonateController extends Controller
{
    public function __construct(private StripeGateway $stripe)
    {
    }

    private function site(string $siteName): Site
    {
        return Site::where('name', $siteName)->firstOrFail();
    }

    public function index(string $siteName)
    {
        $site   = $this->site($siteName);
        $config = $site->feature('donations');

        $suggested = collect(explode(',', (string) ($config['suggested_amounts'] ?? '5,10,25,50')))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->values()
            ->all();

        return view('public.donate.index', [
            'site'      => $site,
            'headline'  => $config['headline'] ?? 'Support our work',
            'currency'  => $site->currency ?? 'gbp',
            'suggested' => $suggested,
        ]);
    }

    public function checkout(string $siteName, Request $request)
    {
        $site     = $this->site($siteName);
        $config   = $site->feature('donations');
        $currency = $site->currency ?? 'gbp';

        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:1', 'max:1000000'],
            'name'    => ['nullable', 'string', 'max:255'],
            'email'   => ['nullable', 'email'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $site->stripeReady()) {
            return back()->with('donate_error', 'Donations are not available right now.');
        }

        $cents = (int) round(((float) $data['amount']) * 100);

        $donation = $site->donations()->create([
            'donor_email'  => $data['email'] ?? null,
            'donor_name'   => $data['name'] ?? null,
            'amount_cents' => $cents,
            'currency'     => $currency,
            'message'      => $data['message'] ?? null,
            'status'       => 'pending',
        ]);

        try {
            $session = $this->stripe->createCheckoutSession(
                site: $site,
                lineItems: [[
                    'price_data' => [
                        'currency'     => $currency,
                        'product_data' => ['name' => 'Donation to ' . ucwords(str_replace('-', ' ', $site->name))],
                        'unit_amount'  => $cents,
                    ],
                    'quantity' => 1,
                ]],
                successUrl: url($site->name . '/donate/success') . '?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: url($site->name . '/donate'),
                metadata: ['donation_id' => $donation->id, 'site_id' => $site->id],
                customerEmail: $data['email'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('Stripe donation failed', ['site' => $site->id, 'msg' => $e->getMessage()]);
            $donation->delete();
            return back()->with('donate_error', 'Could not start donation. Please try again later.');
        }

        $donation->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    public function success(string $siteName, Request $request)
    {
        $site     = $this->site($siteName);
        $donation = $site->donations()->where('stripe_session_id', $request->query('session_id'))->first();

        return view('public.donate.success', compact('site', 'donation'));
    }

    public function webhook(string $siteName, Request $request)
    {
        $site = $this->site($siteName);

        try {
            $event = $this->stripe->verifyWebhook($site, $request->getContent(), $request->header('Stripe-Signature', ''));
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $id      = $session->metadata->donation_id ?? null;

            $donation = $id
                ? $site->donations()->find($id)
                : $site->donations()->where('stripe_session_id', $session->id)->first();

            if ($donation) {
                $donation->update([
                    'donor_email' => $donation->donor_email ?: ($session->customer_details->email ?? null),
                    'donor_name'  => $donation->donor_name ?: ($session->customer_details->name ?? null),
                ]);
                $donation->markPaid();
            }
        }

        return response('ok', 200);
    }
}
