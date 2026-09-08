<?php

namespace App\Payments;

/** One thing being paid for — provider-neutral. */
final readonly class CheckoutLine
{
    public function __construct(
        public string $name,
        public int $unitAmountCents,
        public string $currency,
        public int $quantity = 1,
    ) {}
}
