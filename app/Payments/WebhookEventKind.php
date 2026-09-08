<?php

namespace App\Payments;

enum WebhookEventKind
{
    case Completed;
    case Expired;
    case Unknown;
}
