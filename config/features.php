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

    'bookings' => [
        'key' => 'bookings',
        'tier' => 'basic',
        'intents' => ['book', 'booking', 'bookings', 'appointment', 'appointments', 'schedule', 'scheduling', 'reservation', 'reserve', 'calendar', 'slot', 'consultation', 'hotel', 'room', 'stay', 'accommodation', 'seat', 'seats', 'departure', 'trip', 'transport', 'bus', 'ride'],
        'frontend_block' => 'booking',
        'name' => 'Bookings & Reservations',
        'description' => 'One booking engine, three kinds: appointment slots (salon/mechanic), stays (rooms/houses, per-night) and trips (transport departures with seats). Optional Stripe payment per service.',
        'icon' => 'calendar',
        'needs_payments' => false,
        'nav' => [
            ['label' => 'Bookings', 'seg' => 'bookings'],
        ],
        'settings' => [
            'days' => ['type' => 'text',   'label' => 'Open days (comma-separated: mon,tue,…)', 'default' => 'mon,tue,wed,thu,fri'],
            'open_time' => ['type' => 'text',   'label' => 'Opening time (HH:MM, 24h)',             'default' => '09:00'],
            'close_time' => ['type' => 'text',   'label' => 'Closing time (HH:MM, 24h)',             'default' => '17:00'],
            'slot_minutes' => ['type' => 'number', 'label' => 'Slot length (minutes)',                 'default' => 30],
            'lead_hours' => ['type' => 'number', 'label' => 'Minimum notice before a booking (hours)', 'default' => 12],
            'horizon_days' => ['type' => 'number', 'label' => 'How many days ahead can be booked',     'default' => 30],
            'remind_visitor' => ['type' => 'toggle', 'label' => 'Email customers a reminder ~24h before their booking', 'default' => true],
            'review_requests' => ['type' => 'toggle', 'label' => 'Email a review request after the appointment (needs a review link in Site settings)', 'default' => true],
            'rebook_weeks' => ['type' => 'number', 'label' => 'Rebooking prompt: email customers N weeks after their last visit (0 = off)', 'default' => 5],
        ],
    ],

    'store' => [
        'key' => 'store',
        'tier' => 'basic',
        'intents' => ['sell', 'shop', 'store', 'product', 'products', 'ecommerce', 'buy', 'checkout', 'cart', 'merch'],
        'frontend_block' => null,
        'name' => 'Online Store',
        'description' => 'Sell products and accept card payments via Stripe Checkout.',
        'icon' => 'shop',
        'needs_payments' => true,
        'nav' => [
            ['label' => 'Store',  'seg' => 'store'],
            ['label' => 'Orders', 'seg' => 'orders', 'icon' => 'box'],
        ],
        'settings' => [
            'currency' => ['type' => 'select', 'label' => 'Currency', 'options' => ['usd', 'eur', 'gbp', 'cad', 'aud'], 'default' => 'usd'],
            'product_limit' => ['type' => 'number', 'label' => 'Max products', 'default' => 50],
            'vat_percent' => ['type' => 'number', 'label' => 'VAT % (prices are VAT-inclusive; 0 = not shown)', 'default' => 0],
        ],
    ],

    'invoices' => [
        'key' => 'invoices',
        'tier' => 'basic',
        'intents' => ['invoice', 'invoices', 'invoicing', 'bill', 'billing', 'payment', 'payments', 'receivable', 'get paid'],
        'frontend_block' => null,
        'name' => 'Invoices & Payments',
        'description' => 'Bill customers, email invoices with a hosted Stripe pay link, and track collected / outstanding / overdue money with charts.',
        'icon' => 'receipt',
        'needs_payments' => true,
        'nav' => [
            ['label' => 'Invoices', 'seg' => 'invoices'],
        ],
        'settings' => [
            'currency' => ['type' => 'select', 'label' => 'Currency', 'options' => ['usd', 'eur', 'gbp', 'cad', 'aud'], 'default' => 'usd'],
            'due_days' => ['type' => 'number', 'label' => 'Default payment terms (days)', 'default' => 14],
            'tax_percent' => ['type' => 'number', 'label' => 'Default tax %', 'default' => 0],
        ],
    ],

    'donations' => [
        'key' => 'donations',
        'tier' => 'basic',
        'intents' => ['donate', 'donation', 'donations', 'give', 'giving', 'fundraise', 'fundraising', 'support', 'contribution', 'tip'],
        'frontend_block' => null,
        'name' => 'Donations',
        'description' => 'Accept one-off donations with suggested amounts via Stripe.',
        'icon' => 'heart',
        'needs_payments' => true,
        'nav' => [
            ['label' => 'Donations', 'seg' => 'donations'],
        ],
        'settings' => [
            'currency' => ['type' => 'select', 'label' => 'Currency', 'options' => ['usd', 'eur', 'gbp', 'cad', 'aud'], 'default' => 'usd'],
            'suggested_amounts' => ['type' => 'text', 'label' => 'Suggested amounts (comma-separated)', 'default' => '5, 10, 25, 50'],
            'headline' => ['type' => 'text', 'label' => 'Donate page headline', 'default' => 'Support our work'],
        ],
    ],

    'estimator' => [
        'key' => 'estimator',
        'tier' => 'basic',
        'intents' => ['estimate', 'estimator', 'quote', 'quotation', 'pricing calculator', 'cost calculator', 'cleaner', 'cleaning', 'landscaper', 'landscaping', 'laundry', 'carpenter', 'carpentry', 'mover', 'moving', 'removal', 'builder', 'building', 'plumber', 'plumbing', 'electrician', 'electrical'],
        'frontend_block' => 'estimator',
        'name' => 'Cost Estimator',
        'description' => 'Instant cost + completion-time estimates for trade services — cleaning, landscaping, laundry, carpentry, moving, building, plumbing and electrical. Captures each estimate as a lead and emails both parties.',
        'icon' => 'calculator',
        'needs_payments' => false,
        'nav' => [
            ['label' => 'Estimates', 'seg' => 'estimates'],
        ],
        'settings' => [
            'currency' => ['type' => 'text',   'label' => 'Currency code (gbp, usd, eur…)', 'default' => 'gbp'],
            'rate_multiplier' => ['type' => 'number', 'label' => 'Rate scale (%) — 100 = standard rates, 150 = +50%', 'default' => 100],
            'trades' => ['type' => 'text',   'label' => 'Offered trades (comma-separated keys; blank = all): cleaner,landscaper,laundry,carpenter,mover,builder,plumber,electrician', 'default' => ''],
        ],
    ],

    'polls' => [
        'key' => 'polls',
        'name' => 'Polls',
        'icon' => 'bar-chart',
        'tagline' => 'Ask visitors a question, watch votes stream in live.',
        'description' => 'Quick polls for your website: one question, up to 12 options, one deduplicated vote per visitor. Results update live in the admin.',
        'needs_payments' => false,
        'nav' => [
            ['label' => 'Polls', 'seg' => 'polls', 'group' => 'Audience'],
        ],
        'settings' => [],
    ],


    // ── Audience & engagement modules (Oct 2026) ─────────────────────────
    'newsletter' => [
        'key' => 'newsletter',
        'tier' => 'basic',
        'intents' => ['newsletter', 'mailing list', 'email list', 'subscribe', 'subscribers', 'campaign', 'email marketing', 'updates'],
        'frontend_block' => null,
        'name' => 'Newsletter',
        'description' => 'Grow an email list from your site and send campaigns to your subscribers — with unsubscribe links and open/click stats.',
        'icon' => 'envelope',
        'needs_payments' => false,
        'nav' => [
            ['label' => 'Newsletter', 'seg' => 'newsletter'],
        ],
        'settings' => [
            'from_name' => ['type' => 'text', 'label' => 'From name', 'default' => ''],
            'reply_to' => ['type' => 'text', 'label' => 'Reply-to email', 'default' => ''],
            'double_opt_in' => ['type' => 'toggle', 'label' => 'Confirm new subscribers by email (double opt-in)', 'default' => true],
            'signup_headline' => ['type' => 'text', 'label' => 'Signup box headline', 'default' => 'Get our news in your inbox'],
        ],
    ],

    'events' => [
        'key' => 'events',
        'tier' => 'basic',
        'intents' => ['event', 'events', 'tickets', 'ticketing', 'rsvp', 'workshop', 'conference', 'concert', 'class', 'register'],
        'frontend_block' => null,
        'name' => 'Events & Tickets',
        'description' => 'Publish events with free RSVPs or paid tickets, capacity limits, attendee lists, ticket emails and door check-in.',
        'icon' => 'calendar',
        'needs_payments' => false, // free RSVP works without Stripe; paid tickets need it
        'nav' => [
            ['label' => 'Events', 'seg' => 'events'],
        ],
        'settings' => [
            'currency' => ['type' => 'select', 'label' => 'Ticket currency', 'options' => ['gbp', 'usd', 'eur', 'cad', 'aud'], 'default' => 'gbp'],
            'reminder_hours' => ['type' => 'number', 'label' => 'Send attendees a reminder this many hours before (0 = off)', 'default' => 24],
        ],
    ],

    'memberships' => [
        'key' => 'memberships',
        'tier' => 'basic',
        'intents' => ['membership', 'memberships', 'members', 'member area', 'subscription', 'subscriptions', 'join', 'club', 'patron', 'supporter'],
        'frontend_block' => null,
        'name' => 'Memberships',
        'description' => 'Membership tiers — free or paid monthly / yearly through your Stripe — with members-only content and a member list.',
        'icon' => 'team',
        'needs_payments' => false, // free tiers work without Stripe; paid tiers need it
        'nav' => [
            ['label' => 'Members', 'seg' => 'memberships'],
        ],
        'settings' => [
            'currency' => ['type' => 'select', 'label' => 'Currency', 'options' => ['gbp', 'usd', 'eur', 'cad', 'aud'], 'default' => 'gbp'],
            'welcome_message' => ['type' => 'textarea', 'label' => 'Welcome email message', 'default' => 'Welcome — thanks for joining!'],
        ],
    ],

    'reviews' => [
        'key' => 'reviews',
        'tier' => 'basic',
        'intents' => ['review', 'reviews', 'testimonial', 'testimonials', 'rating', 'ratings', 'feedback', 'stars'],
        'frontend_block' => null,
        'name' => 'Reviews & Testimonials',
        'description' => 'Collect star reviews from customers by link or on your site, approve them, and show them with Google-friendly review markup.',
        'icon' => 'star',
        'needs_payments' => false,
        'nav' => [
            ['label' => 'Reviews', 'seg' => 'reviews'],
        ],
        'settings' => [
            'auto_publish_min_stars' => ['type' => 'select', 'label' => 'Publish automatically at or above', 'options' => ['never', '5', '4', '3'], 'default' => 'never'],
            'request_message' => ['type' => 'textarea', 'label' => 'Review request email message', 'default' => 'Thanks for choosing us — would you leave a quick review?'],
        ],
    ],

];
