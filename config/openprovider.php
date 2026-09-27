<?php

/*
|--------------------------------------------------------------------------
| Openprovider reseller API (Phase 1: auth + domain availability)
|--------------------------------------------------------------------------
| Credentials for the Openprovider REST API. The default URL is the SANDBOX
| environment; point OPENPROVIDER_URL at https://api.openprovider.eu/v1 for
| production. Note the current API version is /v1 — the older /v1beta paths
| were discontinued in September 2026.
*/
return [

    'url' => env('OPENPROVIDER_URL', 'https://api.sandbox.openprovider.nl/v1'),

    'username' => env('OPENPROVIDER_USER'),
    'password' => env('OPENPROVIDER_PASS'),

    // Seconds the login token is cached for (Openprovider tokens last ~24h).
    'token_ttl' => (int) env('OPENPROVIDER_TOKEN_TTL', 43200),

    // HTTP timeout in seconds.
    'timeout' => (int) env('OPENPROVIDER_TIMEOUT', 20),
];
