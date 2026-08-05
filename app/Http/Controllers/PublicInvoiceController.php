<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Site;
use App\Services\Stripe\StripeGateway;
use Illuminate\Http\Request;

/**
 * The tokenized public invoice page: the customer views the invoice and pays
 * it via Stripe Checkout. The webhook marks it paid.
 */
class PublicInvoiceController extends Controller
{
    public function __construct(private StripeGateway $stripe) {}

    private function invoice(string $siteName, string $token): array
    {
        $site = Site::where('name', $siteName)->firstOrFail();
        $invoice = Invoice::where('site_id', $site->id)->where('public_token', $token)->firstOrFail();

        return [$site, $invoice];
    }

    public function show(string $siteName, string $token)
    {
        [$site, $invoice] = $this->invoice($siteName, $token);

        // Real-time tracking: first visit to the pay page = "viewed".
        if (! $invoice->viewed_at) {
            $invoice->update(['viewed_at' => now()]);
        }

        return view('public.invoice.show', compact('site', 'invoice'));
    }

    /** Email tracking pixel — first load = "opened". */
    public function pixel(string $siteName, string $token)
    {
        [, $invoice] = $this->invoice($siteName, $token);

        if (! $invoice->opened_at) {
            $invoice->update(['opened_at' => now()]);
        }

        // 1×1 transparent GIF.
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /** Tokenized PDF download — the customer's copy of the invoice. */
    public function pdf(string $siteName, string $token)
    {
        [$site, $invoice] = $this->invoice($siteName, $token);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', compact('site', 'invoice'))
            ->setPaper('a4')
            ->download("{$invoice->number}.pdf");
    }

    /** Client portal: every invoice this customer has with the site. */
    public function portal(string $siteName, string $token)
    {
        [$site, $invoice] = $this->invoice($siteName, $token);

        return view('public.invoice.portal', [
            'site'     => $site,
            'invoice'  => $invoice,
            'invoices' => $invoice->siblingInvoices(),
        ]);
    }

    /** Start Stripe Checkout for this invoice. */
    public function pay(string $siteName, string $token)
    {
        [$site, $invoice] = $this->invoice($siteName, $token);

        if ($invoice->status === 'paid') {
            return redirect()->route('public.invoice', [$siteName, $token]);
        }
        abort_unless($invoice->isPayable() && $site->stripeReady(), 404);

        $session = $this->stripe->createCheckoutSession(
            $site,
            collect($invoice->items)->map(fn ($i) => [
                'price_data' => [
                    'currency'     => $invoice->currency,
                    'product_data' => ['name' => $i['description'] ?: 'Item'],
                    'unit_amount'  => max(1, (int) $i['unit_cents']),
                ],
                'quantity' => max(1, (int) ($i['qty'] ?? 1)),
            ])->when($invoice->tax_cents > 0, fn ($items) => $items->push([
                'price_data' => [
                    'currency'     => $invoice->currency,
                    'product_data' => ['name' => 'Tax'],
                    'unit_amount'  => $invoice->tax_cents,
                ],
                'quantity' => 1,
            ]))->values()->all(),
            route('public.invoice.success', [$siteName, $token]),
            route('public.invoice', [$siteName, $token]),
            ['invoice_id' => $invoice->id, 'site_id' => $site->id],
            $invoice->customer_email,
        );

        $invoice->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    public function success(string $siteName, string $token)
    {
        [$site, $invoice] = $this->invoice($siteName, $token);

        return view('public.invoice.success', compact('site', 'invoice'));
    }

    /** Stripe webhook: checkout completed → invoice paid. */
    public function webhook(string $siteName, Request $request)
    {
        $site = Site::where('name', $siteName)->firstOrFail();

        try {
            $event = $this->stripe->verifyWebhook($site, $request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $invoice = isset($session->metadata->invoice_id)
                ? Invoice::where('site_id', $site->id)->find($session->metadata->invoice_id)
                : Invoice::where('site_id', $site->id)->where('stripe_session_id', $session->id)->first();

            if ($invoice && $invoice->status !== 'paid') {
                $invoice->markPaid();
            }
        }

        return response()->json(['received' => true]);
    }
}
