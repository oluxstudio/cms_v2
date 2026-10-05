<?php

/*
|--------------------------------------------------------------------------
| Site content cache
|--------------------------------------------------------------------------
| Every visitor page load asks /api/sites/{site}/content for the whole site.
| Building it hits the database dozens of times, so each site's payload is
| cached under a version number that is bumped whenever its content changes
| (model events + the Edit page). The TTL is only a safety net.
*/
return [
    'enabled' => env('CONTENT_CACHE', true),

    // A fast store (Redis). Falls back to the app's default store if it's unreachable.
    'store' => env('CONTENT_CACHE_STORE', 'redis'),

    'ttl' => (int) env('CONTENT_CACHE_TTL', 900),

    // HTTP caching for browsers / a CDN: short max-age, then revalidate by ETag.
    'max_age' => (int) env('CONTENT_CACHE_MAX_AGE', 30),
    'stale_while_revalidate' => (int) env('CONTENT_CACHE_SWR', 300),
];
