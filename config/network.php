<?php

/*
 * Olux Local Referral Network — tenants pass leads to each other and earn a
 * fixed fee per converted lead. Fees are collected on the RECEIVING business's
 * next Olux bill; the referrer is paid the fee minus Olux's cut (Stripe Connect
 * transfer, or Olux credit).
 */
return [
    // Olux's share of every referral fee (percent).
    'olux_cut_pct' => (float) env('NETWORK_OLUX_CUT_PCT', 3),

    // A referral converts when the referred contact pays a booking / invoice /
    // order on the receiving site within this many days of being shared.
    'conversion_window_days' => 60,

    // The receiver may dispute a conversion within this many days.
    'dispute_days' => 7,

    // Consent link lifetime (days) — after it, the referral expires unshared.
    'consent_days' => 14,

    // Fee bounds a receiving business may set (pence).
    'fee_min_cents' => 100,
    'fee_max_cents' => 50000,

    // Plans that may send/receive referrals (trial/free can browse only).
    'plans' => ['starter', 'growth', 'pro', 'enterprise'],

    // Bump when the network agreement text changes — members re-accept.
    'terms_version' => '2026-10-draft',
];
