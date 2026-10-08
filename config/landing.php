<?php

/*
 * Marketing landing page settings. Admin › Testimonials edits
 * testimonials_count live (App\Support\ConfigOverlay).
 */
return [
    // How many published testimonials the landing carousel shows.
    'testimonials_count' => (int) env('LANDING_TESTIMONIALS', 5),
];
