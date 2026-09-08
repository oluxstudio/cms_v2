<?php

namespace App\Payments;

use RuntimeException;

class PaymentsDisabledException extends RuntimeException
{
    public function __construct(string $message = 'This site is not accepting payments.')
    {
        parent::__construct($message);
    }
}
