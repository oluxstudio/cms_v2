<?php

/*
 * Module tiers — every CMS module is classified basic | premium.
 * This drives the PRO badges in the navigation and the marketplace,
 * and is the single place to re-tier a module in the future.
 *
 * Built-in modules (always present):
 */
return [

    'tiers' => [
        // Build & design
        'build'       => 'premium',  // the block builder — the flagship
        'pages'       => 'basic',
        'templates'   => 'basic',

        // Content
        'media'       => 'basic',
        'collections' => 'basic',
        'posts'       => 'basic',

        // Audience
        'forms'       => 'basic',
        'contacts'    => 'basic',

        // Site
        'analytics'   => 'basic',
        'team'        => 'basic',

        // Commerce feature-modules (config/features.php keys).
        // All premium today — flip any of these to 'basic' at will.
        'store'       => 'premium',
        'invoices'    => 'premium',
        'donations'   => 'premium',
        'bookings'    => 'premium',
        'estimator'   => 'premium',

    ],

];
