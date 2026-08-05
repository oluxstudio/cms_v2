<?php

namespace App\Services\Stripe;

use App\Models\Site;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway
{
    /** A Stripe client authenticated with the SITE'S own secret key. */
    public function clientForSite(Site $site): StripeClient
    {
        $secret = $site->paymentSettings?->stripe_secret;

        if (blank($secret)) {
            throw new RuntimeException('This site has not connected Stripe yet.');
        }

        return new StripeClient([
            'api_key' => $secret,
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }

    /**
     * Create a hosted Checkout Session for the given site.
     *
     * @param  array  $lineItems  Stripe line_items (price_data shape)
     */
    public function createCheckoutSession(
        Site $site,
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        ?string $customerEmail = null,
    ): CheckoutSession {
        $params = [
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            // Newer accounts enable Managed Payments by default, which then
            // requires a tax_code on every line item — sites sell arbitrary
            // things (bookings, products, donations), so opt out per session.
            'managed_payments' => ['enabled' => false],
        ];

        if ($customerEmail) {
            $params['customer_email'] = $customerEmail;
        }

        return $this->clientForSite($site)->checkout->sessions->create($params);
    }

    /**
     * Verify and construct a webhook event using the site's webhook secret.
     */
    /**
     * Fetch a Checkout Session — server-side payment truth for the success-return
     * path (used when no webhook is configured, e.g. local dev).
     */
    public function retrieveCheckoutSession(Site $site, string $sessionId): Session
    {
        return $this->clientForSite($site)->checkout->sessions->retrieve($sessionId);
    }

    public function verifyWebhook(Site $site, string $payload, string $sigHeader): Event
    {
        $secret = $site->paymentSettings?->stripe_webhook_secret;

        if (blank($secret)) {
            throw new RuntimeException('No webhook secret configured for this site.');
        }

        return Webhook::constructEvent($payload, $sigHeader, $secret);
    }
}
