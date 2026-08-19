<?php

return [

    /*
    |--------------------------------------------------------------------------
    | page.json schema version
    |--------------------------------------------------------------------------
    | Bumped ONLY on a breaking change to the page.json contract. Baked into
    | every generated document as `schemaVersion`; the hydration script checks
    | it for compatibility before touching the DOM.
    */
    'schema_version' => 2,

    /*
    |--------------------------------------------------------------------------
    | Storage disk for generated page.json
    |--------------------------------------------------------------------------
    | Local `public` in dev; the R2-compatible `s3` disk in production (set
    | SITE_CONNECT_DISK=s3). Paths are always tenant-prefixed (see path_template).
    */
    'disk' => env('SITE_CONNECT_DISK', 'public'),

    // {site} = site id (tenant prefix), {slug} = page slug. Kept tenant-first
    // so every artefact is isolated by construction.
    'path_template' => 'tenants/{site}/pages/{slug}.json',

    /*
    |--------------------------------------------------------------------------
    | Crawl limits per tier (Stage 3 uses these; declared here as the contract)
    |--------------------------------------------------------------------------
    */
    'crawl' => [
        'max_pages' => [
            'free' => 10,
            'starter' => 25,
            'pro' => 50,
        ],
        'max_page_bytes' => 2 * 1024 * 1024, // 2 MB per fetched page
        'max_depth' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingest abuse limits (the token is public in client HTML — cap everything)
    |--------------------------------------------------------------------------
    */
    'ingest' => [
        'max_html_bytes' => 2 * 1024 * 1024,  // page snapshot
        'max_css_bytes' => 1024 * 1024,       // collected same-origin CSS
        'max_links' => 200,
        // Retention: how many ingestion rows to keep.
        'keep_per_url' => 3,                  // latest N per (site, source_url)
        'keep_per_site' => 250,               // latest M per site overall
        // Per-token rate limits on POST /connect/ingest.
        'per_minute' => 15,
        'per_day' => 300,
        // Skip re-dispatching a crawl if one seeded within this window.
        'crawl_cooldown_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content history: versions kept per content model (min 3)
    |--------------------------------------------------------------------------
    */
    'versions_keep' => 5,

    /*
    |--------------------------------------------------------------------------
    | SSRF guard: allow fetching from private/reserved IPs
    |--------------------------------------------------------------------------
    | Defaults to true only in the local environment (client sites run on
    | localhost there). NEVER enable in production unless you fully trust
    | every connected site's asset URLs.
    */
    'allow_private_hosts' => env('SITE_CONNECT_ALLOW_PRIVATE_HOSTS', env('APP_ENV') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Token abilities Site Connect issues (never mutation abilities)
    |--------------------------------------------------------------------------
    */
    'abilities' => ['connect:ingest', 'content:read'],
];
