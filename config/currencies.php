<?php

/*
 * Currency catalog — symbol + where it sits relative to the amount.
 * "position" => before: £12.50 · after: 12,50 €
 * The site's currency (sites.currency, default gbp) drives every money
 * string in the CMS, emails, widgets and Stripe checkout.
 */
return [
    'gbp' => ['symbol' => '£',   'position' => 'before', 'label' => 'British Pound'],
    'usd' => ['symbol' => '$',   'position' => 'before', 'label' => 'US Dollar'],
    'eur' => ['symbol' => '€',   'position' => 'after',  'label' => 'Euro'],
    'ngn' => ['symbol' => '₦',   'position' => 'before', 'label' => 'Nigerian Naira'],
    'cad' => ['symbol' => 'CA$', 'position' => 'before', 'label' => 'Canadian Dollar'],
    'aud' => ['symbol' => 'A$',  'position' => 'before', 'label' => 'Australian Dollar'],
    'jpy' => ['symbol' => '¥',   'position' => 'before', 'label' => 'Japanese Yen', 'decimals' => 0],
    'chf' => ['symbol' => 'CHF', 'position' => 'before', 'label' => 'Swiss Franc'],
    'inr' => ['symbol' => '₹',   'position' => 'before', 'label' => 'Indian Rupee'],
    'zar' => ['symbol' => 'R',   'position' => 'before', 'label' => 'South African Rand'],
    'kes' => ['symbol' => 'KSh', 'position' => 'before', 'label' => 'Kenyan Shilling'],
    'ghs' => ['symbol' => 'GH₵', 'position' => 'before', 'label' => 'Ghanaian Cedi'],
    'sek' => ['symbol' => 'kr',  'position' => 'after',  'label' => 'Swedish Krona'],
    'nok' => ['symbol' => 'kr',  'position' => 'after',  'label' => 'Norwegian Krone'],
    'dkk' => ['symbol' => 'kr',  'position' => 'after',  'label' => 'Danish Krone'],
    'pln' => ['symbol' => 'zł',  'position' => 'after',  'label' => 'Polish Złoty'],
    'brl' => ['symbol' => 'R$',  'position' => 'before', 'label' => 'Brazilian Real'],
    'mxn' => ['symbol' => 'MX$', 'position' => 'before', 'label' => 'Mexican Peso'],
    'aed' => ['symbol' => 'AED', 'position' => 'after',  'label' => 'UAE Dirham'],
];
