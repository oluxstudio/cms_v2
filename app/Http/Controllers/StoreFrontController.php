<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Services\Stripe\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreFrontController extends Controller
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
        $site     = $this->site($siteName);
        $products = $site->products()->where('is_active', true)->orderBy('sort')->latest()->get();

        return view('public.store.index', compact('site', 'products'));
    }

    public function show(string $siteName, string $slug)
    {
        $site    = $this->site($siteName);
        $product = $site->products()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('public.store.show', compact('site', 'product'));
    }

    public function checkout(string $siteName, Request $request)
    {
        $site = $this->site($siteName);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'qty'        => ['nullable', 'integer', 'min:1', 'max:99'],
            'email'      => ['nullable', 'email'],
        ]);

        $product = $site->products()->where('id', $data['product_id'])->where('is_active', true)->firstOrFail();
        $qty     = $data['qty'] ?? 1;

        if (! $site->stripeReady()) {
            return back()->with('store_error', 'This store is not accepting payments yet.');
        }

        // Create the pending order first so the webhook can find it.
        $order = $site->orders()->create([
            'customer_email' => $data['email'] ?? null,
            'status'         => 'pending',
            'total_cents'    => $product->price_cents * $qty,
            'currency'       => $product->currency,
        ]);
        $order->items()->create([
            'product_id'  => $product->id,
            'name'        => $product->name,
            'price_cents' => $product->price_cents,
            'qty'         => $qty,
        ]);

        try {
            $session = $this->stripe->createCheckoutSession(
                site: $site,
                lineItems: [[
                    'price_data' => [
                        'currency'     => $product->currency,
                        'product_data' => ['name' => $product->name],
                        'unit_amount'  => $product->price_cents,
                    ],
                    'quantity' => $qty,
                ]],
                successUrl: url($site->name . '/store/success') . '?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: url($site->name . '/store/' . $product->slug),
                metadata: ['order_id' => $order->id, 'site_id' => $site->id],
                customerEmail: $data['email'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('Stripe checkout failed', ['site' => $site->id, 'msg' => $e->getMessage()]);
            $order->update(['status' => 'cancelled']);
            return back()->with('store_error', 'Could not start checkout. Please try again later.');
        }

        $order->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    public function success(string $siteName, Request $request)
    {
        $site  = $this->site($siteName);
        $order = $site->orders()->where('stripe_session_id', $request->query('session_id'))->first();

        return view('public.store.success', compact('site', 'order'));
    }

    public function cancel(string $siteName)
    {
        $site = $this->site($siteName);

        return view('public.store.cancel', compact('site'));
    }

    /** Stripe webhook (per-site URL identifies the site & its webhook secret). */
    public function webhook(string $siteName, Request $request)
    {
        $site = $this->site($siteName);

        try {
            $event = $this->stripe->verifyWebhook(
                $site,
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
            );
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;

            $order = $orderId
                ? $site->orders()->find($orderId)
                : $site->orders()->where('stripe_session_id', $session->id)->first();

            if ($order) {
                $order->update([
                    'customer_email' => $order->customer_email ?: ($session->customer_details->email ?? null),
                    'customer_name'  => $order->customer_name ?: ($session->customer_details->name ?? null),
                ]);
                $order->markPaid($session->payment_intent ?? null);
            }
        }

        return response('ok', 200);
    }
}
