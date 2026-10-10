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

    // Module payment handlers (see App\Payments\SitePaymentFulfilment):
    // checkout metadata key => class with handle(Site, WebhookEvent, bool $completed).
    // Each module adds its own line here.
    'fulfilment' => [
        'ticket_order_id' => App\Modules\Events\TicketFulfilment::class,
        'membership_id' => App\Modules\Memberships\MembershipFulfilment::class,
    ],
];
