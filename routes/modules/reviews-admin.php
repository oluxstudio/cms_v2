<?php

// reviews module — ADMIN pages — inside the auth group, prefix none: paths start /{siteID}/… ; apply ->middleware(['feature:reviews', 'perm:reviews.view']).
// Owned by the reviews module; keep routes for this module here.

use App\Modules\Reviews\Http\ReviewsAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/{siteID}/reviews', [ReviewsAdminController::class, 'index'])
    ->middleware(['feature:reviews', 'perm:reviews.view'])->name('site.reviews');
