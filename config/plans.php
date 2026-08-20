<?php

/*
 * Account subscription tiers. The first tier is a time-boxed FREE TRIAL with
 * everything unlocked; paid tiers scale limits + premium module access.
 * Money in cents/month. `order` drives display; `highlight` marks the
 * recommended card; `accent` is a site-theme accent (lime | lavender |
 * cocoa | sky | primary) applied to the card + button; `description` is the
 * long copy shown in the plan detail view.
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
            'accent' => 'cocoa',
            'limits' => ['sites' => 1, 'premium' => true, 'storage_mb' => 100],
            'features' => ['1 site', 'All premium modules unlocked', 'Block builder + templates', 'Bookings, invoices & estimator', '14 days, no card required'],
            'description' => 'Kick the tyres with everything switched on. For 14 days you get the full platform — every premium module, the block builder, templates and one live site — with no card required. When the trial ends, pick the plan that fits; nothing you built is lost.',
        ],
        'starter' => [
            'name' => 'Starter',
            'tagline' => 'For a single simple site',
            'price_cents' => 1900,
            'order' => 2,
            'color' => '#0ea5e9',
            'accent' => 'sky',
            'limits' => ['sites' => 1, 'premium' => false, 'storage_mb' => 1024],
            'features' => ['1 site', 'Pages, posts & media', 'Forms & contacts', 'Store, bookings, invoices & estimator', 'Community support'],
            'description' => 'Everything you need to run one clean, content-driven website — pages, posts, media, forms and a built-in CRM, plus the full commerce suite: store, bookings, invoices, donations and the cost estimator. Upgrade to Pro for more sites and the block builder.',
        ],
        'pro' => [
            'name' => 'Pro',
            'tagline' => 'For growing businesses',
            'price_cents' => 4500,
            'order' => 3,
            'color' => '#6366f1',
            'accent' => 'lime',
            'highlight' => true,
            'limits' => ['sites' => 5, 'premium' => true, 'storage_mb' => 5120],
            'features' => ['5 sites', 'All premium modules', 'Block builder', 'Full commerce suite', 'Email support'],
            'description' => 'The sweet spot for a growing business. Run up to five sites with every premium module unlocked — including the block builder — on top of the full commerce suite every plan gets, backed by email support. Most customers land here.',
        ],
        'business' => [
            'name' => 'Business',
            'tagline' => 'For teams and agencies',
            'price_cents' => 7900,
            'order' => 4,
            'color' => '#10b981',
            'accent' => 'lavender',
            'limits' => ['sites' => 10, 'premium' => true, 'storage_mb' => 20240],
            'features' => ['10 sites', 'Everything in Pro', 'Team roles & assignments', 'Template marketplace publishing', 'Priority support'],
            'description' => 'Built for teams and agencies managing many sites. Ten sites, everything in Pro, plus granular team roles and assignments and the ability to publish to the template marketplace — with priority support when you need a fast answer.',
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'tagline' => 'Scale without limits',
            'price_cents' => 14900,
            'order' => 5,
            'color' => '#a855f7',
            'accent' => 'primary',
            'limits' => ['sites' => null, 'premium' => true, 'storage_mb' => null], // null = unlimited
            'features' => ['Unlimited sites', 'Everything in Business', 'White-label options', 'Custom integrations', 'Dedicated support'],
            'description' => 'No ceilings. Unlimited sites, everything in Business, white-label options and custom integrations, with a dedicated point of contact. For platforms and agencies running Olux Studio at scale.',
        ],
    ],
];
