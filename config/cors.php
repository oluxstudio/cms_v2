<?php

/*
|--------------------------------------------------------------------------
| CORS — explicit, auditable
|--------------------------------------------------------------------------
| The /api surface is intentionally readable from any origin: site content
| is public by design and client sites live on arbitrary custom domains.
| Cross-site WRITE abuse is stopped elsewhere — the `site.origin`
| middleware (per-site origin allowlist), tiered rate limiters and the
| honeypot. Credentials stay false: auth is Bearer headers, never cookies,
| so no cross-origin request can ride a session.
*/

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
