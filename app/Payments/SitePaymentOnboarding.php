<?php

namespace App\Payments;

use App\Models\Site;
use App\Models\SitePaymentSettings;
use App\Payments\Drivers\StripeConnectGateway;
use Stripe\StripeClient;

/**
 * "Connect your account" onboarding for a SITE: creates the site's Express
 * account (owner just fills Stripe's hosted form — no API keys), and syncs
 * whether charges are enabled on return.
 */
class SitePaymentOnboarding
{
    /**
     * Stripe's client emits an E_USER_WARNING nudging platforms toward the
     * Accounts v2 API; Laravel's dev handler turns warnings into exceptions.
     * Run the call with that one warning ignored — everything else still throws.
     */
    private function quietly(callable $fn)
    {
        set_error_handler(fn ($no, $str) => str_contains($str, 'Accounts v2'), E_USER_WARNING);
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    public function available(): bool
    {
        return StripeConnectGateway::platformConfigured();
    }

    public function onboardingLink(Site $site, string $returnUrl, string $refreshUrl): string
    {
        $client = $this->client();
        $settings = $site->paymentSettings ?: SitePaymentSettings::create(['site_id' => $site->id]);

        if (! $settings->connect_account_id) {
            $account = $this->quietly(fn () => $client->accounts->create([
                'type' => 'express',
                'email' => $site->user?->email,
                'capabilities' => ['card_payments' => ['requested' => true], 'transfers' => ['requested' => true]],
                'business_profile' => ['name' => $site->getAttr('business_name') ?: $site->name],
                'metadata' => ['site_id' => $site->id],
            ]));
            $settings->update(['connect_account_id' => $account->id, 'provider' => 'stripe_connect']);
        }

        return $client->accountLinks->create([
            'account' => $settings->connect_account_id,
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
            'type' => 'account_onboarding',
        ])->url;
    }

    /** Return leg: pull charges_enabled; switch payments on once chargeable. */
    public function syncAccount(Site $site): void
    {
        $settings = $site->paymentSettings;
        if (! $settings?->connect_account_id || ! $this->available()) {
            return;
        }
        $account = $this->quietly(fn () => $this->client()->accounts->retrieve($settings->connect_account_id));
        $chargeable = (bool) ($account->charges_enabled ?? false);
        $settings->update([
            'connect_charges_enabled' => $chargeable,
            'provider' => 'stripe_connect',
            // First successful onboarding flips the switch on — the owner can
            // still turn it off from the payments drawer.
            'enabled' => $settings->enabled || $chargeable,
        ]);
    }

    private function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => config('services.stripe_platform.secret'),
            'stripe_version' => config('services.stripe.api_version'),
        ]);
    }
}
