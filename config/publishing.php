<?php

/*
|--------------------------------------------------------------------------
| Going live — custom-domain publishing
|--------------------------------------------------------------------------
| Live sites are served BY THIS SERVER: the client points their domain's DNS
| at the platform, and ServeLiveSite resolves the incoming Host header to a
| Site and serves its built renderer app with content straight from the API.
*/

return [

    // What clients point their DNS at — your server's public IP (A record)
    // or a hostname (CNAME). Shown in the go-live instructions and used by
    // the "Verify DNS" check. e.g. PLATFORM_DNS_TARGET=203.0.113.10
    'dns_target' => env('PLATFORM_DNS_TARGET', ''),

    // Hostnames that belong to the PLATFORM itself (admin app) and must never
    // be treated as a client site domain. APP_URL's host is always included.
    // Comma-separated extras: PLATFORM_HOSTS=cms.olux.io,olux.io
    'platform_hosts' => array_filter(array_map('trim', explode(',', (string) env('PLATFORM_HOSTS', '')))),
];
