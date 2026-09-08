<?php

namespace Tests\Fakes;

use App\Models\Site;
use App\Payments\CheckoutRequest;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use App\Payments\WebhookEventKind;

/** Records checkouts and replays scripted webhook events — no Stripe involved. */
class FakePaymentGateway implements PaymentGateway
{
    public bool $isAvailable = true;

    public bool $paid = true;

    /** @var list<CheckoutRequest> */
    public array $checkouts = [];

    public ?WebhookEvent $nextEvent = null;

    public function available(Site $site): bool
    {
        return $this->isAvailable;
    }

    public function signatureHeaderName(): string
    {
        return 'X-Fake-Signature';
    }

    public function createCheckout(Site $site, CheckoutRequest $request): CheckoutSession
    {
        $this->checkouts[] = $request;

        return new CheckoutSession(uniqid('fake_sess_'), 'https://fake-pay.test/checkout');
    }

    public function verifyWebhook(Site $site, string $payload, ?string $signature): WebhookEvent
    {
        return $this->nextEvent ?? throw new \RuntimeException('No scripted webhook event.');
    }

    public function checkoutIsPaid(Site $site, string $sessionId): bool
    {
        return $this->paid;
    }

    /** Scripted session details for checkoutDetails(); falls back to a bare paid/unpaid event. */
    public ?WebhookEvent $details = null;

    public function checkoutDetails(Site $site, string $sessionId): ?WebhookEvent
    {
        return $this->details ?? new WebhookEvent(WebhookEventKind::Completed, $sessionId, [], null, null, null, $this->paid);
    }

    /** @var list<array{ref:string,amount:?int}> */
    public array $refunds = [];

    public bool $refundThrows = false;

    public function refund(Site $site, string $paymentRef, ?int $amountCents = null): void
    {
        if ($this->refundThrows) {
            throw new \RuntimeException('Fake refund failure.');
        }
        $this->refunds[] = ['ref' => $paymentRef, 'amount' => $amountCents];
    }
}
