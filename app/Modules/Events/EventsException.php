<?php

namespace App\Modules\Events;

use RuntimeException;

/** A visitor-facing reason an order can't be placed (sold out, off sale…) — rendered as a 422. */
class EventsException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid')
    {
        parent::__construct($message);
    }
}
