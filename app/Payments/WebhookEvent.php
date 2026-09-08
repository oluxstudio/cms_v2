<?php

namespace App\Payments;

/** A verified provider webhook, normalised for the flow controllers. */
final readonly class WebhookEvent
{
    public function __construct(
        public WebhookEventKind $kind,
        public ?string $sessionId = null,
        public array $metadata = [],
        public ?string $payerEmail = null,
        public ?string $payerName = null,
        public ?string $paymentRef = null,
        public bool $isPaid = false,
        public ?string $shippingAddress = null,
        public ?string $payerPhone = null,
    ) {}

    /** Format a Stripe shipping_details/customer_details object into a multiline address. */
    public static function formatAddress(mixed $shipping, mixed $customer = null): ?string
    {
        $src = ($shipping->address ?? null) ? $shipping : ((($customer->address ?? null) ? $customer : null));
        if (! $src) {
            return null;
        }
        $a = $src->address;
        $lines = array_filter(array_map('trim', array_map('strval', [
            $src->name ?? '',
            $a->line1 ?? '',
            $a->line2 ?? '',
            trim(($a->city ?? '').' '.($a->postal_code ?? '')),
            $a->state ?? '',
            $a->country ?? '',
        ])), fn ($l) => $l !== '');

        return $lines ? implode("\n", $lines) : null;
    }
}
