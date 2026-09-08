<?php

namespace App\Services\Stripe;

/**
 * @deprecated The gateway moved behind the App\Payments\PaymentGateway seam.
 *             Use App\Payments\PaymentManager::for($site) instead.
 */
class StripeGateway extends \App\Payments\Drivers\StripeGateway {}
