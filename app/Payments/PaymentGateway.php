<?php

namespace App\Payments;

use App\Models\Site;

/**
 * The per-site payment seam. Everything a checkout flow needs, with no
 * provider types leaking out: controllers speak DTOs, drivers speak Stripe
 * (or whatever comes next). `successUrl` may contain the literal token
 * {CHECKOUT_SESSION_ID} — each driver substitutes its own placeholder
 * (Stripe passes it through verbatim, since it IS Stripe's token).
 */
interface PaymentGateway
{
    /** Can this site take a payment right now (keys present, switch on)? */
    public function available(Site $site): bool;

    public function createCheckout(Site $site, CheckoutRequest $request): CheckoutSession;

    /** Verify an incoming webhook and normalise it. Throws on bad signature. */
    public function verifyWebhook(Site $site, string $payload, ?string $signature): WebhookEvent;

    /** Server-side payment truth for success-return pages (no webhook needed). */
    public function checkoutIsPaid(Site $site, string $sessionId): bool;

    /** Retrieve a checkout session's current state (paid?, payer, shipping) — null when unsupported. */
    public function checkoutDetails(Site $site, string $sessionId): ?WebhookEvent;

    /** The HTTP header carrying the webhook signature for this provider. */
    public function signatureHeaderName(): string;

    /**
     * Refund a captured payment (full refund when $amountCents is null).
     *
     * @throws \Throwable when the provider rejects the refund
     */
    public function refund(Site $site, string $paymentRef, ?int $amountCents = null): void;
}
