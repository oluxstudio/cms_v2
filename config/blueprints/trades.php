<?php

/*
 * Tradespeople blueprint — electricians, plumbers, builders and similar.
 * Trades win work on quotes and get paid on invoices, so the setup leads
 * with the estimator + invoicing and adds bookable call-out slots and a
 * quote-request form. Applied by App\Services\Blueprints\TradesBlueprint.
 */
return [

    'types' => [
        'electrician' => ['label' => 'Electrician', 'icon' => '⚡'],
        'plumber' => ['label' => 'Plumber / heating', 'icon' => '🔧'],
        'builder' => ['label' => 'Builder / handyman', 'icon' => '🧱'],
        'decorator' => ['label' => 'Painter & decorator', 'icon' => '🎨'],
        'cleaner' => ['label' => 'Cleaning', 'icon' => '🧹'],
        'removals' => ['label' => 'Removals / man & van', 'icon' => '🚚'],
        'gardener' => ['label' => 'Gardening & landscaping', 'icon' => '🌿'],
    ],

    // The clean business template (home / about / contact).
    'template' => 'verita',

    'features' => [
        'estimator' => [],
        'invoices' => [],
        // Call-outs: hour-long slots on weekdays, booked at least a day ahead.
        'bookings' => [
            'days' => 'mon,tue,wed,thu,fri',
            'open_time' => '08:00',
            'close_time' => '17:00',
            'slot_minutes' => 60,
            'lead_hours' => 24,
            'horizon_days' => 60,
        ],
    ],

    // [name, duration_min, price_cents, deposit_pct|null]
    'services' => [
        ['Site visit & free quote', 45, 0, null],
        ['Call-out & fault finding', 60, 6500, null],
        ['Emergency call-out', 60, 12000, null],
        ['Half-day booking', 240, 22000, 20],
    ],

    'staff' => ['Van 1'],

    'forms' => [
        [
            'name' => 'quote',
            'title' => 'Request a quote',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true],
                ['key' => 'postcode', 'label' => 'Postcode', 'type' => 'text', 'required' => true],
                ['key' => 'job', 'label' => 'What do you need done?', 'type' => 'textarea', 'required' => true],
                ['key' => 'when', 'label' => 'When do you need it?', 'type' => 'text', 'required' => false],
            ],
        ],
    ],
];
