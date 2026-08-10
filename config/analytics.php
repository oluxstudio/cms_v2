<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GeoIP database
    |--------------------------------------------------------------------------
    | Path to a MaxMind GeoLite2-City .mmdb file. Download it free from
    | https://www.maxmind.com/en/geolite2/signup and place it here (or point
    | GEOIP_DB_PATH at it). When the file is absent, visits are still recorded
    | but geo columns stay null (the world map / country charts are just empty).
    */
    'geoip_db' => env('GEOIP_DB_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),

    /*
    |--------------------------------------------------------------------------
    | Beacon target baked into exported sites
    |--------------------------------------------------------------------------
    | The absolute base URL an exported static site pings to record visits.
    | Defaults to the app URL; override for a dedicated ingest host/CDN edge.
    */
    'track_base' => env('ANALYTICS_TRACK_BASE', env('APP_URL', 'http://localhost:8000')),

    /*
    |--------------------------------------------------------------------------
    | Search engine + social referrer hosts (for traffic-source derivation)
    |--------------------------------------------------------------------------
    | Referrers matching these host substrings are classed organic / social;
    | anything else with a referrer is "referral", and none is "direct".
    */
    'search_hosts' => ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'yandex.', 'baidu.', 'ecosia.', 'brave.'],

    'social_hosts' => ['facebook.', 'fb.', 'instagram.', 't.co', 'twitter.', 'x.com', 'linkedin.', 'lnkd.in', 'pinterest.', 'reddit.', 'youtube.', 'youtu.be', 'tiktok.', 'whatsapp.', 'telegram.', 't.me'],

];
