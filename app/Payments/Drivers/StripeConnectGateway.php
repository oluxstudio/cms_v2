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

        // Platform fee on every sale (percent of the total): the site owner's
        // plan rate (Starter 1%, Growth 0.5%, Pro 0%), else the global default.
        $feePct = (float) ($site->user?->currentSubscription()->paymentFeePct() ?? config('payments.connect_fee_percent', 0));
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

    // ── recurring (subscription-mode) payments — used by Memberships ──

    /** Connected-account events about recurring billing, forwarded by SiteConnectWebhookController. */
    public const SUBSCRIPTION_EVENTS = [
        'checkout.session.completed',
        'invoice.paid',
        'invoice.payment_failed',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    /** This driver can bill on a recurring schedule (the Off / own-keys drivers cannot). */
    public function supportsSubscriptions(): bool
    {
        return true;
    }

    /**
     * Checkout params for a recurring price on the connected account. The
     * metadata goes on the session AND the subscription, so invoices and
     * subscription events carry it too. The platform fee is a percentage of
     * every invoice (application_fee_percent), the same rate as one-off sales.
     */
    public static function subscriptionCheckoutParams(
        string $name, int $amountCents, string $currency, string $interval,
        string $successUrl, string $cancelUrl, array $metadata = [], ?string $customerEmail = null, float $feePct = 0,
    ): array {
        $params = [
            'mode' => 'subscription',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => ['name' => $name],
                    'unit_amount' => max(1, $amountCents),
                    'recurring' => ['interval' => $interval === 'year' ? 'year' : 'month'],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
        ];
        if ($customerEmail) {
            $params['customer_email'] = $customerEmail;
        }
        if ($feePct > 0) {
            $params['subscription_data']['application_fee_percent'] = round($feePct, 2);
        }

        return $params;
    }

    public function createSubscriptionCheckout(
        Site $site, string $name, int $amountCents, string $currency, string $interval,
        string $successUrl, string $cancelUrl, array $metadata = [], ?string $customerEmail = null,
    ): CheckoutSession {
        $feePct = (float) ($site->user?->currentSubscription()->paymentFeePct() ?? config('payments.connect_fee_percent', 0));
        $params = self::subscriptionCheckoutParams($name, $amountCents, $currency, $interval, $successUrl, $cancelUrl, $metadata, $customerEmail, $feePct);

        $session = $this->client()->checkout->sessions->create($params, $this->onAccount($site));

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    /** @return array{paid: bool, subscription: ?string, customer: ?string, metadata: array} */
    public function subscriptionCheckoutDetails(Site $site, string $sessionId): array
    {
        $s = $this->client()->checkout->sessions->retrieve($sessionId, [], $this->onAccount($site));

        return [
            'paid' => in_array($s->payment_status ?? null, ['paid', 'no_payment_required'], true) && ($s->status ?? null) === 'complete',
            'subscription' => is_string($s->subscription ?? null) ? $s->subscription : ($s->subscription->id ?? null),
            'customer' => is_string($s->customer ?? null) ? $s->customer : ($s->customer->id ?? null),
            'metadata' => isset($s->metadata) ? $s->metadata->toArray() : [],
        ];
    }

    /** Cancel at the end of the paid period (default) or immediately. */
    public function cancelSubscription(Site $site, string $subscriptionId, bool $atPeriodEnd = true): void
    {
        $atPeriodEnd
            ? $this->client()->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true], $this->onAccount($site))
            : $this->client()->subscriptions->cancel($subscriptionId, [], $this->onAccount($site));
    }

    /** Stripe-hosted billing portal on the connected account (card, invoices, cancel). */
    public function billingPortalUrl(Site $site, string $customerId, string $returnUrl): string
    {
        $session = $this->client()->billingPortal->sessions->create(
            ['customer' => $customerId, 'return_url' => $returnUrl], $this->onAccount($site),
        );

        return (string) $session->url;
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
