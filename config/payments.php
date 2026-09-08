<?php

use App\Payments\Drivers\StripeConnectGateway;
use App\Payments\Drivers\StripeGateway;

/*
| Per-site customer payment gateways. Sites pick a provider (and flip the
| "Accept payments" switch) in Marketplace → payment settings; the manager
| resolves the driver per site. Add a provider = add a driver class here.
*/
return [
    'default' => 'stripe_connect',

    'drivers' => [
        // "Connect your account" — Stripe hosted onboarding, no API keys.
        'stripe_connect' => StripeConnectGateway::class,
        // Advanced: the owner pastes their own Stripe API keys.
        'stripe' => StripeGateway::class,
    ],

    // Optional platform fee (percent) on Connect-driver sales.
    // 0 = the site owner keeps everything.
    'connect_fee_percent' => (float) env('SITE_PAYMENTS_FEE_PERCENT', 0),
];
