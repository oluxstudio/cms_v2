<?php

namespace App\Payments\Drivers;

use App\Models\Site;
use App\Payments\CheckoutRequest;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;

/** Stripe hosted Checkout, authenticated with the SITE'S own keys. */
class StripeGateway implements PaymentGateway
{
    public function available(Site $site): bool
    {
        return (bool) $site->paymentSettings?->isConfigured();
    }

    public function signatureHeaderName(): string
    {
        return 'Stripe-Signature';
    }

    public function createCheckout(Site $site, CheckoutRequest $request): CheckoutSession
    {
        $params = [
            'mode' => 'payment',
            'line_items' => array_map(fn ($l) => [
                'price_data' => [
                    'currency' => $l->currency,
                    'product_data' => ['name' => $l->name],
                    'unit_amount' => max(1, $l->unitAmountCents),
                ],
                'quantity' => max(1, $l->quantity),
            ], $request->lines),
            'success_url' => $request->successUrl, // may carry {CHECKOUT_SESSION_ID} — Stripe's own token
            'cancel_url' => $request->cancelUrl,
            'metadata' => $request->metadata,
            // Newer accounts enable Managed Payments by default, which then
            // requires a tax_code on every line item — sites sell arbitrary
            // things (bookings, products, donations), so opt out per session.
            'managed_payments' => ['enabled' => false],
        ];
        if ($request->customerEmail) {
            $params['customer_email'] = $request->customerEmail;
        }

        if ($request->collectShipping) {
            $params['shipping_address_collection'] = ['allowed_countries' => ['GB', 'IE', 'US', 'CA', 'AU', 'NZ']];
            $params['phone_number_collection'] = ['enabled' => true];
        }

        $session = $this->client($site)->checkout->sessions->create($params);

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    public function verifyWebhook(Site $site, string $payload, ?string $signature): WebhookEvent
    {
        $secret = $site->paymentSettings?->stripe_webhook_secret;
        if (blank($secret)) {
            throw new RuntimeException('No webhook secret configured for this site.');
        }

        $event = Webhook::constructEvent($payload, (string) $signature, $secret);
        $object = $event->data->object;

        return new WebhookEvent(
            kind: match ($event->type) {
                'checkout.session.completed' => WebhookEventKind::Completed,
                'checkout.session.expired' => WebhookEventKind::Expired,
                default => WebhookEventKind::Unknown,
            },
            sessionId: $object->id ?? null,
            metadata: isset($object->metadata) ? $object->metadata->toArray() : [],
            payerEmail: $object->customer_details->email ?? null,
            payerName: $object->customer_details->name ?? null,
            paymentRef: $object->payment_intent ?? null,
            isPaid: ($object->payment_status ?? null) === 'paid',
            shippingAddress: WebhookEvent::formatAddress($object->collected_information->shipping_details ?? $object->shipping_details ?? null, $object->customer_details ?? null),
            payerPhone: $object->customer_details->phone ?? null,
        );
    }

    public function checkoutDetails(Site $site, string $sessionId): ?WebhookEvent
    {
        $object = $this->client($site)->checkout->sessions->retrieve($sessionId);

        return new WebhookEvent(
            kind: WebhookEventKind::Completed,
            sessionId: (string) ($object->id ?? $sessionId),
            metadata: isset($object->metadata) ? $object->metadata->toArray() : [],
            payerEmail: $object->customer_details->email ?? null,
            payerName: $object->customer_details->name ?? null,
            paymentRef: $object->payment_intent ?? null,
            isPaid: ($object->payment_status ?? null) === 'paid',
            shippingAddress: WebhookEvent::formatAddress($object->collected_information->shipping_details ?? $object->shipping_details ?? null, $object->customer_details ?? null),
            payerPhone: $object->customer_details->phone ?? null,
        );
    }

    public function checkoutIsPaid(Site $site, string $sessionId): bool
    {
        $session = $this->client($site)->checkout->sessions->retrieve($sessionId);

        return ($session->payment_status ?? null) === 'paid';
    }

    public function refund(Site $site, string $paymentRef, ?int $amountCents = null): void
    {
        $this->client($site)->refunds->create(array_filter([
            'payment_intent' => $paymentRef,
            'amount' => $amountCents,
        ]));
    }

    private function client(Site $site): StripeClient
    {
        $secret = $site->paymentSettings?->stripe_secret;
        if (blank($secret)) {
            throw new RuntimeException('This site has not connected Stripe yet.');
        }

        return new StripeClient([
            'api_key' => $secret,
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }
}
