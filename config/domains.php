<?php

/*
|--------------------------------------------------------------------------
| Domain purchase (search → buy → register → point at the platform)
|--------------------------------------------------------------------------
| Clients buy a domain from inside the CMS. The registrar driver does the
| availability check + registration + DNS; Stripe (platform account) takes
| the money. `fake` needs no account and registers instantly — for local dev
| and tests. Prices are RETAIL in pence per year (registrar cost + margin).
*/
return [

    // fake | resellerclub
    'driver' => env('DOMAIN_REGISTRAR', 'fake'),

    // Years bought at checkout.
    'years' => 1,

    // TLDs offered, in display order, with retail price/year in pence.
    'tlds' => [
        'co.uk' => ['price_cents' => 1200],
        'uk' => ['price_cents' => 1200],
        'com' => ['price_cents' => 1500],
        'org' => ['price_cents' => 1500],
        'net' => ['price_cents' => 1600],
        'salon' => ['price_cents' => 4500],
        'london' => ['price_cents' => 3500],
    ],

    // Where a bought domain's A record points. Falls back to publishing.dns_target.
    'dns_target' => env('DOMAIN_DNS_TARGET', env('PLATFORM_DNS_TARGET', '')),

    // Registrant contact defaults (the client's name/email are merged in).
    'registrant' => [
        'company' => env('DOMAIN_REGISTRANT_COMPANY', env('APP_NAME', 'Olux')),
        'address' => env('DOMAIN_REGISTRANT_ADDRESS', ''),
        'city' => env('DOMAIN_REGISTRANT_CITY', ''),
        'zip' => env('DOMAIN_REGISTRANT_ZIP', ''),
        'country' => env('DOMAIN_REGISTRANT_COUNTRY', 'GB'),
        'phone' => env('DOMAIN_REGISTRANT_PHONE', ''),
    ],

    'resellerclub' => [
        'user_id' => env('RESELLERCLUB_USER_ID'),
        'api_key' => env('RESELLERCLUB_API_KEY'),
        'customer_id' => env('RESELLERCLUB_CUSTOMER_ID'),
        'sandbox' => (bool) env('RESELLERCLUB_SANDBOX', true),
    ],
];
