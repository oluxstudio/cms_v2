<?php

namespace App\Payments;

use App\Models\Site;
use App\Payments\Drivers\OffGateway;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the payment gateway for a site: the OFF gateway until the owner
 * flips "Accept payments", then the driver named by the site's provider
 * (config/payments.php — stripe today, others when they land).
 */
class PaymentManager
{
    private ?PaymentGateway $fake = null;

    public function for(Site $site): PaymentGateway
    {
        if ($this->fake) {
            return $this->fake;
        }

        $settings = $site->paymentSettings;
        if (! $settings?->enabled) {
            return app(OffGateway::class);
        }

        $provider = $settings->provider ?: config('payments.default', 'stripe');
        $class = config("payments.drivers.{$provider}");
        if (! $class) {
            Log::warning('Unknown payment provider — payments treated as off.', ['site' => $site->id, 'provider' => $provider]);

            return app(OffGateway::class);
        }

        return app($class);
    }

    /** Tests: force every for() call to return this gateway. */
    public function fake(?PaymentGateway $gateway): void
    {
        $this->fake = $gateway;
    }
}
