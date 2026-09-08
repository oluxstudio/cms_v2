<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Site;
use App\Payments\CheckoutLine;
use App\Payments\CheckoutRequest;
use App\Payments\PaymentManager;
use App\Payments\WebhookEventKind;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * The tokenized public invoice page: the customer views the invoice and pays
 * it via Stripe Checkout. The webhook marks it paid.
 */
class PublicInvoiceController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

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

        return Pdf::loadView('pdf.invoice', compact('site', 'invoice'))
            ->setPaper('a4')
            ->download("{$invoice->number}.pdf");
    }

    /** Client portal: every invoice this customer has with the site. */
    public function portal(string $siteName, string $token)
    {
        [$site, $invoice] = $this->invoice($siteName, $token);

        return view('public.invoice.portal', [
            'site' => $site,
            'invoice' => $invoice,
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
        // Friendly (not a 404): the invoice exists, the site just isn't taking
        // card payments right now.
        $gateway = $this->payments->for($site);
        if (! $invoice->isPayable() || ! $gateway->available($site)) {
            return redirect()->route('public.invoice', [$siteName, $token])
                ->with('invoice_error', 'Online payment isn\'t available for this invoice right now — contact us to pay another way.');
        }

        $lines = collect($invoice->items)->map(fn ($i) => new CheckoutLine(
            $i['description'] ?: 'Item', max(1, (int) $i['unit_cents']), $invoice->currency, max(1, (int) ($i['qty'] ?? 1)),
        ))->when($invoice->tax_cents > 0, fn ($items) => $items->push(
            new CheckoutLine('Tax', $invoice->tax_cents, $invoice->currency),
        ))->values()->all();

        try {
            $session = $gateway->createCheckout($site, new CheckoutRequest(
                lines: $lines,
                successUrl: route('public.invoice.success', [$siteName, $token]),
                cancelUrl: route('public.invoice', [$siteName, $token]),
                metadata: ['invoice_id' => $invoice->id, 'site_id' => $site->id],
                customerEmail: $invoice->customer_email,
            ));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('public.invoice', [$siteName, $token])
                ->with('invoice_error', 'Payment could not be started. Please try again shortly.');
        }

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

        $gateway = $this->payments->for($site);

        try {
            $event = $gateway->verifyWebhook($site, $request->getContent(), $request->header($gateway->signatureHeaderName()));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event->kind === WebhookEventKind::Completed) {
            $invoice = isset($event->metadata['invoice_id'])
                ? Invoice::where('site_id', $site->id)->find($event->metadata['invoice_id'])
                : Invoice::where('site_id', $site->id)->where('stripe_session_id', $event->sessionId)->first();

            if ($invoice && $invoice->status !== 'paid') {
                $invoice->markPaid();
            }
        }

        return response()->json(['received' => true]);
    }
}
