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
                'sites' => 1, 'premium' => true, 'storage_mb' => 100, 'marketplace' => false, 'ai_tokens_month' => null,
                'mailboxes' => 0, 'custom_domain' => true, 'free_domain' => null, 'bookings_month' => null,
                'staff_calendars' => null, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => true,
                'payment_fee_pct' => 1, 'badge' => false,
            ],
            'features' => ['1 site', 'Every feature unlocked', 'Bookings, invoices & CRM', 'Block builder + templates', '14 days, no card required'],
            'description' => 'Kick the tyres with everything switched on. For 14 days you get the full platform — bookings, invoices, the CRM, the block builder, templates and one live site — with no card required. When the trial ends, pick the plan that fits; nothing you built is lost.',
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
                'sites' => 1, 'premium' => false, 'storage_mb' => 10240, 'marketplace' => false, 'ai_tokens_month' => null,
                'mailboxes' => 0, 'custom_domain' => true, 'free_domain' => null, 'bookings_month' => null,
                'staff_calendars' => 1, 'deposits' => false, 'invoices_month' => 10, 'recurring_invoices' => false,
                'payment_fee_pct' => 1, 'badge' => false,
            ],
            'features' => ['Your own domain, no badge', 'Unlimited bookings, 1 calendar', 'Contacts and notes', '10 invoices a month', 'AI answers questions on your site', 'Email support'],
            'description' => 'Everything a sole trader needs to look professional online: your own domain with no Olux badge, unlimited bookings on one calendar, contacts with notes, ten invoices a month, 10 GB of storage and an AI assistant that answers questions on your site. Upgrade to Growth for business mailboxes and a team booking calendar.',
        ],
        'growth' => [
            'name' => 'Growth',
            'tagline' => 'Small team taking bookings',
            'price_cents' => 3900,
            'annual_price_cents' => 39000,
            'domain_included' => true,
            'order' => 3,
            'color' => '#6366f1',
            'accent' => 'lime',
            'highlight' => true,
            'limits' => [
                'sites' => 5, 'premium' => true, 'storage_mb' => 25600, 'marketplace' => false, 'ai_tokens_month' => null,
                'mailboxes' => 5, 'custom_domain' => true, 'free_domain' => 'co.uk', 'bookings_month' => null,
                'staff_calendars' => 3, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => false,
                'payment_fee_pct' => 0.5, 'badge' => false,
            ],
            'features' => ['Free .co.uk for year 1', '5 business mailboxes', 'Up to 3 staff calendars, deposits', 'Full unified contact record', 'Unlimited invoices', 'SMS credit pack included'],
            'description' => 'Where most salons and clinics land. A free .co.uk domain for the first year, five business mailboxes, up to three staff booking calendars with deposits, the full unified contact record, unlimited invoices, an SMS credit pack, full local SEO tools and an AI assistant that answers questions and captures leads. Upgrade to Pro for more staff, WhatsApp and AI bookings.',
        ],
        'pro' => [
            'name' => 'Pro',
            'tagline' => 'Busy multi-staff business',
            'price_cents' => 6900,
            'annual_price_cents' => 69000,
            'domain_included' => true,
            'order' => 4,
            'color' => '#10b981',
            'accent' => 'lavender',
            'limits' => [
                'sites' => 10, 'premium' => true, 'storage_mb' => 51200, 'marketplace' => true, 'ai_tokens_month' => null,
                'mailboxes' => 10, 'custom_domain' => true, 'free_domain' => 'any', 'bookings_month' => null,
                'staff_calendars' => null, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => true,
                'payment_fee_pct' => 0, 'badge' => false,
            ],
            'features' => ['Free domain for year 1', '10 business mailboxes', 'Unlimited staff, deposits & no-show fees', 'CRM with segments', 'Recurring invoices', 'SMS & WhatsApp Business', 'No platform payment fee'],
            'description' => 'For busy multi-staff businesses. A free domain for the first year, ten business mailboxes, unlimited staff calendars with deposits and no-show fees, the CRM with segments, recurring invoices, SMS and WhatsApp Business, an AI assistant that takes bookings and creates leads, no platform fee on online payments and priority support.',
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
                'sites' => null, 'premium' => true, 'storage_mb' => null, 'marketplace' => true, 'ai_tokens_month' => null,
                'mailboxes' => null, 'custom_domain' => true, 'free_domain' => 'any', 'bookings_month' => null,
                'staff_calendars' => null, 'deposits' => true, 'invoices_month' => null, 'recurring_invoices' => true,
                'payment_fee_pct' => 0, 'badge' => false,
            ],
            'features' => ['Domain included', 'Mailboxes and storage to suit you', 'Multi-location booking', 'Custom SMS volume and AI', 'Negotiated payment fee', 'Account manager'],
            'description' => 'For multi-site businesses, or when you want us to run it for you. Domain included, mailboxes and storage to suit your team, multi-location booking, custom SMS volume and AI, a negotiated payment fee and a dedicated account manager.',
        ],
    ],

    /*
     * The side-by-side comparison (subscription + how-it-works pages).
     * Row label => [tier key => text]. Text only — limits above are the rules.
     */
    'compare' => [
        'Who it\'s for' => ['free' => 'Trying it out', 'starter' => 'Sole traders who need a site', 'growth' => 'Small team taking bookings', 'pro' => 'Busy multi-staff business', 'enterprise' => 'Multi-site or done-for-you'],
        'Website' => ['free' => 'Olux subdomain, Olux badge', 'starter' => 'Custom domain, no badge', 'growth' => 'Custom domain, no badge', 'pro' => 'Custom domain, no badge', 'enterprise' => 'Custom domain, no badge'],
        'Domain' => ['free' => 'Bring your own only', 'starter' => 'Bring your own', 'growth' => 'Free .co.uk for year 1', 'pro' => 'Free domain for year 1', 'enterprise' => 'Included'],
        'Mailboxes' => ['free' => 'None', 'starter' => 'Forwarding only', 'growth' => '5', 'pro' => '10', 'enterprise' => 'Custom'],
        'Storage pool' => ['free' => '1 GB', 'starter' => '10 GB', 'growth' => '25 GB', 'pro' => '50 GB', 'enterprise' => 'Custom'],
        'Booking' => ['free' => '20 bookings/month', 'starter' => 'Unlimited, 1 calendar', 'growth' => 'Up to 3 staff calendars, deposits', 'pro' => 'Unlimited staff, deposits and no-show fees', 'enterprise' => 'Multi-location'],
        'CRM' => ['free' => 'Contacts only', 'starter' => 'Contacts and notes', 'growth' => 'Full unified contact record', 'pro' => 'Full, plus segments', 'enterprise' => 'Full, plus segments'],
        'Invoicing' => ['free' => 'No', 'starter' => '10 invoices/month', 'growth' => 'Unlimited', 'pro' => 'Unlimited, recurring', 'enterprise' => 'Unlimited, recurring'],
        'SMS / WhatsApp' => ['free' => 'No', 'starter' => 'No', 'growth' => 'SMS credit pack included', 'pro' => 'SMS and WhatsApp Business', 'enterprise' => 'Custom volume'],
        'AI assistant' => ['free' => 'No', 'starter' => 'Answers questions on site', 'growth' => 'Answers and captures leads', 'pro' => 'Takes bookings, creates leads', 'enterprise' => 'Custom'],
        'Local SEO tools' => ['free' => 'Basic', 'starter' => 'Basic', 'growth' => 'Full (schema, sitemap, GBP)', 'pro' => 'Full', 'enterprise' => 'Full'],
        'Online payment fee (on top of Stripe)' => ['free' => 'n/a', 'starter' => '1%', 'growth' => '0.5%', 'pro' => '0%', 'enterprise' => 'Negotiated'],
        'Support' => ['free' => 'Help docs', 'starter' => 'Email', 'growth' => 'Email + chat', 'pro' => 'Priority', 'enterprise' => 'Account manager'],
        'Reason to upgrade' => ['free' => 'Own domain, no badge', 'starter' => 'Mailboxes + team calendar', 'growth' => 'More staff, WhatsApp, AI bookings', 'pro' => 'Multi-location / done-for-you', 'enterprise' => '—'],
    ],
];
