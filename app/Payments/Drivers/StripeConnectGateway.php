<?php

namespace App\Payments\Drivers;

use App\Models\Site;
use App\Payments\CheckoutRequest;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;
use RuntimeException;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe Connect driver: the owner never touches API keys — they click
 * "Connect", complete Stripe's hosted onboarding (business + bank details),
 * and charges run DIRECTLY on their connected account using the PLATFORM's
 * credentials. Optionally takes a platform fee (payments.connect_fee_percent).
 *
 * Webhooks arrive on ONE platform Connect endpoint (/stripe/sites/webhook)
 * carrying the connected account id — SiteConnectWebhookController resolves
 * the site and fulfils; the per-flow webhook URLs are not used here.
 */
class StripeConnectGateway implements PaymentGateway
{
    public static function platformConfigured(): bool
    {
        return filled(config('services.stripe_platform.secret'));
    }

    public function available(Site $site): bool
    {
        $s = $site->paymentSettings;

        return self::platformConfigured()
            && filled($s?->connect_account_id)
            && (bool) $s?->connect_charges_enabled;
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
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'metadata' => $request->metadata,
            'managed_payments' => ['enabled' => false],
        ];
        if ($request->customerEmail) {
            $params['customer_email'] = $request->customerEmail;
        }
        if ($request->collectShipping) {
            $params['shipping_address_collection'] = ['allowed_countries' => ['GB', 'IE', 'US', 'CA', 'AU', 'NZ']];
            $params['phone_number_collection'] = ['enabled' => true];
        }

        // Optional platform fee on every sale (percent of the total).
        $feePct = (float) config('payments.connect_fee_percent', 0);
        if ($feePct > 0) {
            $total = array_sum(array_map(fn ($l) => $l->unitAmountCents * max(1, $l->quantity), $request->lines));
            $params['payment_intent_data'] = ['application_fee_amount' => (int) round($total * $feePct / 100)];
        }

        $session = $this->client()->checkout->sessions->create($params, $this->onAccount($site));

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    public function verifyWebhook(Site $site, string $payload, ?string $signature): WebhookEvent
    {
        $event = self::constructPlatformEvent($payload, $signature);
        if (($event->account ?? null) !== $site->paymentSettings?->connect_account_id) {
            throw new RuntimeException('Webhook is for a different connected account.');
        }

        return self::toWebhookEvent($event);
    }

    public function checkoutDetails(Site $site, string $sessionId): ?WebhookEvent
    {
        $object = $this->client()->checkout->sessions->retrieve($sessionId, [], $this->onAccount($site));

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
        $session = $this->client()->checkout->sessions->retrieve($sessionId, [], $this->onAccount($site));

        return ($session->payment_status ?? null) === 'paid';
    }

    // ── platform-level helpers (shared with SiteConnectWebhookController) ──

    public static function constructPlatformEvent(string $payload, ?string $signature): Event
    {
        $secret = config('services.stripe_platform.connect_webhook_secret');
        if (blank($secret)) {
            throw new RuntimeException('Connect webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, (string) $signature, $secret);
    }

    public static function toWebhookEvent(Event $event): WebhookEvent
    {
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

    private function onAccount(Site $site): array
    {
        $acct = $site->paymentSettings?->connect_account_id;
        if (blank($acct)) {
            throw new RuntimeException('This site has not connected a Stripe account yet.');
        }

        return ['stripe_account' => $acct];
    }

    public function refund(Site $site, string $paymentRef, ?int $amountCents = null): void
    {
        $this->client()->refunds->create(array_filter([
            'payment_intent' => $paymentRef,
            'amount' => $amountCents,
        ]), ['stripe_account' => $site->paymentSettings->stripe_account_id]);
    }

    private function client(): StripeClient
    {
        if (! self::platformConfigured()) {
            throw new RuntimeException('Platform Stripe keys are not configured.');
        }

        return new StripeClient([
            'api_key' => config('services.stripe_platform.secret'),
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }
}
