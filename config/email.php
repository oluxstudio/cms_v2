<?php

/*
|--------------------------------------------------------------------------
| Business email (tenant mailboxes on their own domain)
|--------------------------------------------------------------------------
| Provisioned through Openprovider Business Email (their "Mailcow" API).
| Platform transactional mail (bookings, invoices…) never goes through
| tenant mailboxes — that stays on the platform mailer (Brevo).
*/
return [

    // openprovider | fake (local dev / tests — nothing leaves the app)
    'driver' => env('EMAIL_PROVIDER', 'fake'),

    'quota_gb' => 15,

    // Openprovider licence period for a new mailbox (POST /mailcow/orders `period`,
    // /orders/assign `subscription_period`).
    // TODO(openprovider): confirm the unit (months?) and allowed values — the spec gives no enum.
    'subscription_period' => env('EMAIL_SUBSCRIPTION_PERIOD', '1'),

    // Sending limits (informational; enforced by the provider).
    'sending_limits' => ['per_mailbox_day' => 300, 'per_domain_day' => 5000],

    // Addresses nobody may create (RFC 2142 role accounts + platform-sensitive names).
    'reserved' => [
        'postmaster', 'abuse', 'hostmaster', 'webmaster', 'security', 'noc', 'root',
        'mailer-daemon', 'nobody', 'admin', 'administrator', 'ssl-admin', 'autoconfig', 'autodiscover',
    ],

    // After a subscription is cancelled mailboxes are suspended (dashboard only)
    // and deleted at the provider once this window ends.
    'export_window_days' => (int) env('EMAIL_EXPORT_WINDOW_DAYS', 30),

    // DNS recheck: attempts and backoff (seconds) between checks.
    'dns_check' => ['max_attempts' => 12, 'backoff' => [120, 300, 600, 1800, 3600, 7200]],

    /*
     | Records the domain needs. TODO(openprovider): fill these in from
     | "DNS records for Openprovider Email Solution" (support article
     | 15687409627026) — the API does not expose them. `{domain}` is replaced.
     | DKIM comes from the API (GET /mailcow/dkim), DMARC starts at p=none.
     */
    'records' => [
        'mx' => [
            // ['host' => 'TODO-mx1.example', 'prio' => 10],
        ],
        'spf_include' => env('EMAIL_SPF_INCLUDE', ''),          // e.g. 'include:TODO.openprovider'
        'dmarc' => 'v=DMARC1; p=none; rua=mailto:postmaster@{domain}',
        'cname' => [
            // ['name' => 'autoconfig', 'value' => 'TODO'],
            // ['name' => 'autodiscover', 'value' => 'TODO'],
        ],
        'srv' => [
            // ['name' => '_autodiscover._tcp', 'prio' => 0, 'value' => '0 443 TODO'],
        ],
    ],

    // Brevo: only when a tenant sends booking/invoice emails FROM their own
    // domain and that domain is authenticated in Brevo. Off by default.
    'brevo' => [
        'spf_include' => 'include:spf.brevo.com',
    ],

    /*
     | Mail client settings shown after a mailbox is created.
     | TODO(openprovider): fill in from "Basic settings for configuring your
     | email account" (support article 15292750582162).
     */
    'connection' => [
        'imap' => ['host' => env('EMAIL_IMAP_HOST', ''), 'port' => 993, 'security' => 'SSL/TLS'],
        'pop3' => ['host' => env('EMAIL_POP3_HOST', ''), 'port' => 995, 'security' => 'SSL/TLS'],
        'smtp' => ['host' => env('EMAIL_SMTP_HOST', ''), 'port' => 465, 'security' => 'SSL/TLS'],
        'webmail' => env('EMAIL_WEBMAIL_URL', ''),
        'username' => 'Your full email address',
    ],

    // Per user, per hour.
    'rate_limits' => ['create' => 10, 'password_reset' => 10],

    // Add-on: extra mailboxes on top of the plan (Stripe recurring price).
    'addon' => [
        'stripe_price' => env('STRIPE_MAILBOX_PRICE'),   // a recurring Price id; unset = admins grant extras manually
        'price_cents' => (int) env('EMAIL_ADDON_PRICE_CENTS', 300),
    ],
];
