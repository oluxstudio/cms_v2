<?php

namespace App\Payments\Drivers;

use App\Models\Site;
use App\Payments\CheckoutRequest;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateway;
use App\Payments\PaymentsDisabledException;
use App\Payments\WebhookEvent;

/** The switched-off state: never available, refuses every operation. */
class OffGateway implements PaymentGateway
{
    public function available(Site $site): bool
    {
        return false;
    }

    public function signatureHeaderName(): string
    {
        return 'X-Signature';
    }

    public function checkoutDetails(Site $site, string $sessionId): ?WebhookEvent
    {
        return null;
    }

    public function refund(Site $site, string $paymentRef, ?int $amountCents = null): void
    {
        throw new PaymentsDisabledException;
    }

    public function createCheckout(Site $site, CheckoutRequest $request): CheckoutSession
    {
        throw new PaymentsDisabledException;
    }

    public function verifyWebhook(Site $site, string $payload, ?string $signature): WebhookEvent
    {
        throw new PaymentsDisabledException;
    }

    public function checkoutIsPaid(Site $site, string $sessionId): bool
    {
        throw new PaymentsDisabledException;
    }
}
