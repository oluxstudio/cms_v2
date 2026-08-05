<?php

/*
|--------------------------------------------------------------------------
| Installable Site Features (the "App Store")
|--------------------------------------------------------------------------
| Single source of truth for every feature a site owner can enable/disable.
| Each entry declares: display metadata, whether it needs Stripe payments,
| admin nav items (added only when enabled), and a generic settings schema
| that the Marketplace renders into a form.
|
| Settings field types: text | number | select | textarea | toggle
*/

return [

    'store' => [
        'key'            => 'store',
        'tier'           => 'premium',
        'intents'        => ['sell', 'shop', 'store', 'product', 'products', 'ecommerce', 'buy', 'checkout', 'cart', 'merch'],
        'frontend_block' => null,
        'name'           => 'Online Store',
        'description'    => 'Sell products and accept card payments via Stripe Checkout.',
        'icon'           => 'shop',
        'needs_payments' => true,
        'nav'            => [
            ['label' => 'Store',  'seg' => 'store'],
            ['label' => 'Orders', 'seg' => 'orders'],
        ],
        'settings' => [
            'currency'      => ['type' => 'select', 'label' => 'Currency', 'options' => ['usd', 'eur', 'gbp', 'cad', 'aud'], 'default' => 'usd'],
            'product_limit' => ['type' => 'number', 'label' => 'Max products', 'default' => 50],
        ],
    ],

    'invoices' => [
        'key'            => 'invoices',
        'tier'           => 'premium',
        'intents'        => ['invoice', 'invoices', 'invoicing', 'bill', 'billing', 'payment', 'payments', 'receivable', 'get paid'],
        'frontend_block' => null,
        'name'           => 'Invoices & Payments',
        'description'    => 'Bill customers, email invoices with a hosted Stripe pay link, and track collected / outstanding / overdue money with charts.',
        'icon'           => 'receipt',
        'needs_payments' => true,
        'nav'            => [
            ['label' => 'Invoices', 'seg' => 'invoices'],
        ],
        'settings' => [
            'currency'     => ['type' => 'select', 'label' => 'Currency', 'options' => ['usd', 'eur', 'gbp', 'cad', 'aud'], 'default' => 'usd'],
            'due_days'     => ['type' => 'number', 'label' => 'Default payment terms (days)', 'default' => 14],
            'tax_percent'  => ['type' => 'number', 'label' => 'Default tax %', 'default' => 0],
        ],
    ],

    'donations' => [
        'hidden'         => true, // delisted — neither CMS nor CRM core; existing sites keep it
        'key'            => 'donations',
        'tier'           => 'premium',
        'intents'        => ['donate', 'donation', 'donations', 'give', 'giving', 'fundraise', 'fundraising', 'support', 'contribution', 'tip'],
        'frontend_block' => null,
        'name'           => 'Donations',
        'description'    => 'Accept one-off donations with suggested amounts via Stripe.',
        'icon'           => 'heart',
        'needs_payments' => true,
        'nav'            => [
            ['label' => 'Donations', 'seg' => 'donations'],
        ],
        'settings' => [
            'currency'          => ['type' => 'select', 'label' => 'Currency', 'options' => ['usd', 'eur', 'gbp', 'cad', 'aud'], 'default' => 'usd'],
            'suggested_amounts' => ['type' => 'text', 'label' => 'Suggested amounts (comma-separated)', 'default' => '5, 10, 25, 50'],
            'headline'          => ['type' => 'text', 'label' => 'Donate page headline', 'default' => 'Support our work'],
        ],
    ],

    'estimator' => [
        'key'            => 'estimator',
        'tier'           => 'premium',
        'intents'        => ['estimate', 'estimator', 'quote', 'quotation', 'pricing calculator', 'cost calculator', 'cleaner', 'cleaning', 'landscaper', 'landscaping', 'laundry', 'carpenter', 'carpentry', 'mover', 'moving', 'removal', 'builder', 'building', 'plumber', 'plumbing', 'electrician', 'electrical'],
        'frontend_block' => 'estimator',
        'name'           => 'Cost Estimator',
        'description'    => 'Instant cost + completion-time estimates for trade services — cleaning, landscaping, laundry, carpentry, moving, building, plumbing and electrical. Captures each estimate as a lead and emails both parties.',
        'icon'           => 'calculator',
        'needs_payments' => false,
        'nav'            => [
            ['label' => 'Estimates', 'seg' => 'estimates'],
        ],
        'settings' => [
            'currency'        => ['type' => 'text',   'label' => 'Currency code (gbp, usd, eur…)', 'default' => 'gbp'],
            'rate_multiplier' => ['type' => 'number', 'label' => 'Rate scale (%) — 100 = standard rates, 150 = +50%', 'default' => 100],
            'trades'          => ['type' => 'text',   'label' => 'Offered trades (comma-separated keys; blank = all): cleaner,landscaper,laundry,carpenter,mover,builder,plumber,electrician', 'default' => ''],
        ],
    ],

    'bookings' => [
        'key'            => 'bookings',
        'tier'           => 'premium',
        'intents'        => ['book', 'booking', 'bookings', 'appointment', 'appointments', 'schedule', 'scheduling', 'reservation', 'reserve', 'calendar', 'slot', 'consultation', 'hotel', 'room', 'stay', 'accommodation', 'seat', 'seats', 'departure', 'trip', 'transport', 'bus', 'ride'],
        'frontend_block' => 'booking',
        'name'           => 'Bookings & Reservations',
        'description'    => 'One booking engine, three kinds: appointment slots (salon/mechanic), stays (rooms/houses, per-night) and trips (transport departures with seats). Optional Stripe payment per service.',
        'icon'           => 'calendar',
        'needs_payments' => false,
        'nav'            => [
            ['label' => 'Bookings', 'seg' => 'bookings'],
        ],
        'settings' => [
            'days'         => ['type' => 'text',   'label' => 'Open days (comma-separated: mon,tue,…)', 'default' => 'mon,tue,wed,thu,fri'],
            'open_time'    => ['type' => 'text',   'label' => 'Opening time (HH:MM, 24h)',             'default' => '09:00'],
            'close_time'   => ['type' => 'text',   'label' => 'Closing time (HH:MM, 24h)',             'default' => '17:00'],
            'slot_minutes' => ['type' => 'number', 'label' => 'Slot length (minutes)',                 'default' => 30],
            'lead_hours'   => ['type' => 'number', 'label' => 'Minimum notice before a booking (hours)', 'default' => 12],
            'horizon_days' => ['type' => 'number', 'label' => 'How many days ahead can be booked',     'default' => 30],
        ],
    ],


];
