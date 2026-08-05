<?php

/*
 * Account subscription tiers. The first tier is a time-boxed FREE TRIAL with
 * everything unlocked; paid tiers scale limits + premium module access.
 * Money in cents/month. `order` drives display; `highlight` marks the
 * recommended card on the upgrade page.
 */
return [

    'trial_days' => 14,

    'tiers' => [
        'trial' => [
            'name' => 'Free Trial',
            'tagline' => 'Every feature unlocked for 14 days',
            'price_cents' => 0,
            'order' => 1,
            'color' => '#f59e0b',
            'limits' => ['sites' => 1, 'premium' => true],
            'features' => ['1 site', 'All premium modules unlocked', 'Block builder + templates', 'Bookings, invoices & estimator', '14 days, no card required'],
        ],
        'starter' => [
            'name' => 'Starter',
            'tagline' => 'For a single simple site',
            'price_cents' => 900,
            'order' => 2,
            'color' => '#0ea5e9',
            'limits' => ['sites' => 1, 'premium' => false],
            'features' => ['1 site', 'Pages, posts & media', 'Forms & contacts', 'Community support'],
        ],
        'pro' => [
            'name' => 'Pro',
            'tagline' => 'For growing businesses',
            'price_cents' => 2900,
            'order' => 3,
            'color' => '#6366f1',
            'highlight' => true,
            'limits' => ['sites' => 3, 'premium' => true],
            'features' => ['3 sites', 'All premium modules', 'Bookings & invoices', 'Cost estimator', 'Email support'],
        ],
        'business' => [
            'name' => 'Business',
            'tagline' => 'For teams and agencies',
            'price_cents' => 5900,
            'order' => 4,
            'color' => '#10b981',
            'limits' => ['sites' => 10, 'premium' => true],
            'features' => ['10 sites', 'Everything in Pro', 'Team roles & assignments', 'Template marketplace publishing', 'Priority support'],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'tagline' => 'Scale without limits',
            'price_cents' => 14900,
            'order' => 5,
            'color' => '#a855f7',
            'limits' => ['sites' => null, 'premium' => true], // null = unlimited
            'features' => ['Unlimited sites', 'Everything in Business', 'White-label options', 'Custom integrations', 'Dedicated support'],
        ],
    ],
];
