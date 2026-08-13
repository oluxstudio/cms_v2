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
];
