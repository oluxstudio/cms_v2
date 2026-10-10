<?php

// reviews module — PUBLIC JSON API for template sites — stateless (no session/CSRF), throttle:api; paths start /sites/{siteName}/….
// Owned by the reviews module; keep routes for this module here.

use App\Modules\Reviews\Http\ReviewApiController;
use Illuminate\Support\Facades\Route;

// GET  /api/sites/{siteName}/reviews  → published reviews (?featured=1, ?sort=newest|highest, ?per_page, ?page),
//                                       aggregate {average,count,distribution} + schema.org JSON-LD
// POST /api/sites/{siteName}/reviews  → a visitor's review (pending unless it meets the auto-publish stars)
Route::get('/sites/{siteName}/reviews', [ReviewApiController::class, 'index'])
    ->middleware('feature:reviews')->name('api.reviews.index');
Route::post('/sites/{siteName}/reviews', [ReviewApiController::class, 'store'])
    ->middleware(['feature:reviews', 'throttle:leads', 'site.origin', 'honeypot'])->name('api.reviews.store');
