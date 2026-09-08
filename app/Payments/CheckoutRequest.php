<?php

namespace App\Payments;

final readonly class CheckoutRequest
{
    /** @param  list<CheckoutLine>  $lines */
    public function __construct(
        public array $lines,
        public string $successUrl,
        public string $cancelUrl,
        public array $metadata = [],
        public ?string $customerEmail = null,
        public bool $collectShipping = false,
    ) {}
}
