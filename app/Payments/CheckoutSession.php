<?php

namespace App\Payments;

/** A started hosted checkout: where to send the customer, and its provider id. */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
