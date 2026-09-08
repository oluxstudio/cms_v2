<?php

return [
    // Length of the numeric email-verification code.
    'code_length' => 6,

    // How long a pending sign-up (and its code) stays valid.
    'ttl_minutes' => 15,

    // Wrong-code attempts allowed before the pending sign-up is discarded.
    'max_attempts' => 5,

    // Minimum seconds between "resend code" requests for one pending sign-up.
    'resend_cooldown_seconds' => 60,

    // Hard cap on codes issued per email address per hour (abuse guard).
    'max_codes_per_hour' => 5,

    // Refuse sign-ups from throwaway mailboxes (domain match, subdomains included).
    'blocked_domains' => [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', '10minutemail.com',
        '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'yopmail.com', 'trashmail.com', 'getnada.com',
        'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mohmal.com', 'emailondeck.com',
    ],

    // Check the email domain can actually receive mail (MX or A record) before sending a code.
    'check_mx' => (bool) env('SIGNUP_CHECK_MX', true),
];
