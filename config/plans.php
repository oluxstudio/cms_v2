<?php

/*
 * Account subscription tiers. The first tier is a time-boxed FREE TRIAL with
 * everything unlocked; paid tiers scale limits. Money in cents. `order`
 * drives display; `highlight` marks the recommended card; `accent` is a
 * site-theme accent (lime | lavender | cocoa | sky | primary) applied to the
 * card + button; `description` is the long copy shown in the plan detail view.
 * `annual_price_cents` is shown only (checkout is monthly); `price_prefix`
 * renders before the price ("From £149").
 *
 * Limits (null = unlimited; enforced when something NEW is created, so
 * existing usage carries on after a plan change):
 *   sites, storage_mb, mailboxes, ai_tokens_month, premium, marketplace,
 *   custom_domain        own domain allowed (Free = olux subdomain only)
 *   free_domain          null | 'co.uk' | 'any' — first domain free for year 1
 *   bookings_month       online bookings per calendar month, across sites
 *   staff_calendars      booking resources (staff/rooms) across sites
 *   deposits             may take booking deposits
 *   invoices_month       invoices created by hand per calendar month
 *   recurring_invoices   may set invoices to repeat
 *   payment_fee_pct      platform fee on online payments, on top of Stripe's
 *   badge                live site shows a small "Made with Olux" badge
 *
 * Admin edits on /admin/plans are stored in membership_plans and overlaid
 * on these defaults by App\Support\PlanCatalog.
 */
return [

    'trial_days' => 14,

    'tiers' => [
        // Trial = the signup state (everything unlocked for 14 days). For paid
        // services it counts as Free: no mailboxes for unpaid accounts.
        'trial' => [
            'name' => 'Free Trial',
            'tagline' => 'Every feature unlocked for 14 days',
            'price_cents' => 0,
            'domain_included' => false,
            'order' => 0,
            'color' => '#f59e0b',
            'accent' => 'cocoa',
            'limits' => [
                'newsletter_sends_month' => 500,
                'sites' => 1, 'premium' => true, 'storage_mb' => 1024, 'marketplace' => false, 'ai_tokens_month' => null,
                'mailboxes' => 0, 'custom_domain' => false, 'free_domain' => null, 'bookings_month' => null,
                'staff_calendars' => null, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => true,
                'payment_fee_pct' => 1, 'badge' => false,
            ],
            'features' => ['1 site on a free subdomain', 'Every feature unlocked', 'Bookings, invoices & CRM', '1 GB asset storage', '14 days, no card required'],
            'description' => 'Try everything with it all switched on. For 14 days you get the full platform — bookings, invoices, the CRM, messaging, the AI assistant, the block builder and templates — on one site with a free subdomain and 1 GB of storage, no card required. When the trial ends, pick the plan that fits; nothing you built is lost.',
        ],
        'free' => [
            'name' => 'Free',
            'tagline' => 'Trying it out',
            'price_cents' => 0,
            'domain_included' => false,
            'order' => 1,
            'color' => '#64748b',
            'accent' => 'cocoa',
            'limits' => [
                'newsletter_sends_month' => 250,
                'sites' => 1, 'premium' => false, 'storage_mb' => 1024, 'marketplace' => false, 'ai_tokens_month' => 50000,
                'mailboxes' => 0, 'custom_domain' => false, 'free_domain' => null, 'bookings_month' => 20,
                'staff_calendars' => 1, 'deposits' => false, 'invoices_month' => 0, 'recurring_invoices' => false,
                'payment_fee_pct' => 1, 'badge' => true,
            ],
            'features' => ['Olux subdomain, with Olux badge', '20 bookings a month', 'Contacts', '1 GB storage', 'Basic local SEO', 'Help docs'],
            'description' => 'Try Olux at no cost: one site on a free olux address, 20 online bookings a month, a contact list and 1 GB of storage. Upgrade to Starter for your own domain and no Olux badge.',
        ],
        'starter' => [
            'name' => 'Starter',
            'tagline' => 'Sole traders who need a site',
            'price_cents' => 1900,
            'annual_price_cents' => 19000,
            'domain_included' => false,
            'order' => 2,
            'color' => '#0ea5e9',
            'accent' => 'sky',
            'limits' => [
                'newsletter_sends_month' => 1000,
                'sites' => 1, 'premium' => false, 'storage_mb' => 5120, 'marketplace' => false, 'ai_tokens_month' => null,
                'mailboxes' => 0, 'custom_domain' => true, 'free_domain' => null, 'bookings_month' => null,
                'staff_calendars' => 1, 'deposits' => false, 'invoices_month' => 10, 'recurring_invoices' => false,
                'payment_fee_pct' => 1, 'badge' => false,
            ],
            'features' => ['Your own domain, no badge', 'Unlimited bookings, 1 calendar', 'Contacts and notes', '10 invoices a month', 'AI answers questions on your site', '5 GB storage', 'Email support'],
            'description' => 'Everything a sole trader needs to look professional online: one site on your own domain with no Olux badge, unlimited bookings on one calendar, contacts with notes, ten invoices a month, 5 GB of storage and an AI assistant that answers questions on your site. Upgrade to Growth for business mailboxes, a team booking calendar and more sites.',
        ],
        'growth' => [
            'name' => 'Growth',
            'tagline' => 'Small team taking bookings',
            'price_cents' => 4500,
            'annual_price_cents' => 45000,
            'domain_included' => true,
            'order' => 3,
            'color' => '#6366f1',
            'accent' => 'lime',
            'highlight' => true,
            'limits' => [
                'newsletter_sends_month' => 10000,
                'sites' => 3, 'premium' => true, 'storage_mb' => 20480, 'marketplace' => false, 'ai_tokens_month' => null,
                'mailboxes' => 5, 'mailbox_storage_gb' => 10, 'custom_domain' => true, 'free_domain' => 'co.uk', 'bookings_month' => null,
                'staff_calendars' => 3, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => false,
                'payment_fee_pct' => 0.5, 'badge' => false,
            ],
            'features' => ['3 sites', 'Free .co.uk for year 1', '5 business mailboxes (10 GB each)', 'Up to 3 staff calendars, deposits', 'Full unified contact record', 'Unlimited invoices', 'SMS credit pack', '20 GB storage'],
            'description' => 'Where most salons and clinics land. Up to three sites, a free .co.uk domain for the first year, five business mailboxes with 10 GB each, up to three staff booking calendars with deposits, the full unified contact record, unlimited invoices, an SMS credit pack and 20 GB of storage. Upgrade to Pro for more staff, WhatsApp Business and no platform payment fee.',
        ],
        'pro' => [
            'name' => 'Pro',
            'tagline' => 'Busy multi-staff business',
            'price_cents' => 7900,
            'annual_price_cents' => 79000,
            'domain_included' => true,
            'order' => 4,
            'color' => '#10b981',
            'accent' => 'lavender',
            'limits' => [
                'newsletter_sends_month' => 50000,
                'sites' => 10, 'premium' => true, 'storage_mb' => 51200, 'marketplace' => true, 'ai_tokens_month' => null,
                'mailboxes' => 10, 'mailbox_storage_gb' => 25, 'custom_domain' => true, 'free_domain' => 'any', 'bookings_month' => null,
                'staff_calendars' => 10, 'extra_staff_cents' => 500, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => true,
                'payment_fee_pct' => 0, 'badge' => false,
            ],
            'features' => ['10 sites', 'Free domain for year 1', '10 business mailboxes (25 GB each)', 'Up to 10 staff (then £5 a month each), deposits & no-show fees', 'CRM with segments', 'Unlimited, recurring invoices', 'SMS & WhatsApp Business', 'No platform payment fee', '50 GB storage'],
            'description' => 'For busy multi-staff businesses. Up to ten sites, a free domain for the first year, ten business mailboxes with 25 GB each, up to ten staff calendars (then £5 a month per extra staff member) with deposits and no-show fees, the CRM with segments, unlimited recurring invoices, SMS and WhatsApp Business, 50 GB of storage and no platform fee on online payments.',
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'tagline' => 'Multi-site or done-for-you',
            'price_cents' => 14900,
            'annual_price_cents' => 149000,
            'price_prefix' => 'From',
            'domain_included' => true,
            'order' => 5,
            'color' => '#a855f7',
            'accent' => 'primary',
            // null = unlimited; mailboxes null = set per account by platform admins.
            'limits' => [
                'newsletter_sends_month' => null,
                'sites' => null, 'premium' => true, 'storage_mb' => null, 'marketplace' => true, 'ai_tokens_month' => null,
                'mailboxes' => null, 'custom_domain' => true, 'free_domain' => 'any', 'bookings_month' => null,
                'staff_calendars' => null, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => true,
                'payment_fee_pct' => 0, 'badge' => false,
            ],
            'features' => ['Multi-site, to suit', 'Domain included', 'Mailboxes and storage to suit you', 'Multi-location booking', 'Unlimited, recurring invoices', 'Custom SMS volume and custom AI', 'Negotiated payment fee', 'Account manager'],
            'description' => 'For multi-site businesses, or when you want us to run it for you. As many sites as you need, domain included, mailboxes and storage to suit your team, multi-location booking, unlimited recurring invoices, custom SMS volume and AI, a negotiated payment fee and a dedicated account manager.',
        ],
    ],

    /*
     * The side-by-side comparison (subscription + how-it-works pages).
     * Row label => [tier key => text]. Text only — limits above are the rules.
     */
    'compare' => [
        'Monthly' => ['trial' => 'Free for 14 days', 'starter' => '£19', 'growth' => '£45', 'pro' => '£79', 'enterprise' => 'From £149'],
        'Yearly (two months free)' => ['trial' => '—', 'starter' => '£190', 'growth' => '£450', 'pro' => '£790', 'enterprise' => 'From £1,490'],
        'Best for' => ['trial' => 'Trying everything', 'starter' => 'Sole traders who need a site', 'growth' => 'Small team taking bookings', 'pro' => 'Busy multi-staff business', 'enterprise' => 'Multi-site or done-for-you'],
        'Sites' => ['trial' => '1', 'starter' => '1', 'growth' => '3', 'pro' => '10', 'enterprise' => 'Multi-site, to suit'],
        'Asset storage' => ['trial' => '1 GB', 'starter' => '5 GB', 'growth' => '20 GB', 'pro' => '50 GB', 'enterprise' => 'To suit'],
        'Business mailboxes' => ['trial' => '—', 'starter' => '—', 'growth' => '5', 'pro' => '10', 'enterprise' => 'To suit'],
        'Storage per mailbox' => ['trial' => '—', 'starter' => '—', 'growth' => '10 GB', 'pro' => '25 GB', 'enterprise' => 'To suit'],
        'Domain' => ['trial' => 'Free subdomain', 'starter' => 'Your own domain, no badge', 'growth' => 'Free .co.uk for year 1', 'pro' => 'Free domain for year 1', 'enterprise' => 'Domain included'],
        'Bookings' => ['trial' => 'Unlocked', 'starter' => 'Unlimited, 1 calendar', 'growth' => 'Up to 3 staff calendars, deposits', 'pro' => 'Up to 10 staff (then £5 per extra staff member a month), deposits, no-show fees', 'enterprise' => 'Multi-location booking'],
        'Invoices' => ['trial' => 'Unlocked', 'starter' => '10 a month', 'growth' => 'Unlimited', 'pro' => 'Unlimited, recurring', 'enterprise' => 'Unlimited, recurring'],
        'CRM' => ['trial' => 'Unlocked', 'starter' => 'Contacts and notes', 'growth' => 'Full unified contact record', 'pro' => 'CRM with segments', 'enterprise' => 'CRM with segments'],
        'Messaging' => ['trial' => 'Unlocked', 'starter' => '—', 'growth' => 'SMS credit pack', 'pro' => 'SMS and WhatsApp Business', 'enterprise' => 'Custom SMS volume'],
        'AI assistant' => ['trial' => 'Unlocked', 'starter' => 'Answers questions on your site', 'growth' => 'To confirm', 'pro' => 'To confirm', 'enterprise' => 'Custom AI'],
        'Platform payment fee' => ['trial' => '—', 'starter' => 'Applies', 'growth' => 'Applies', 'pro' => 'None', 'enterprise' => 'Negotiated'],
        'Support' => ['trial' => '—', 'starter' => 'Email', 'growth' => 'To confirm', 'pro' => 'To confirm', 'enterprise' => 'Account manager'],
    ],
];
