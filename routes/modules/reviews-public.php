<?php

// reviews module — PUBLIC web routes (signed links, success pages, webhooks) — loaded BEFORE the /preview/{siteName}/{pageUrl} catch-all; no auth.
// Owned by the reviews module; keep routes for this module here.

use App\Modules\Reviews\Http\ReviewRequestController;
use Illuminate\Support\Facades\Route;

// The hosted page a review-request email links to. Tokens are 48 alphanumerics,
// so the constraint keeps /review/{admin-segment} free for a site named "review".
Route::get('/review/{token}', [ReviewRequestController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40,64}')->name('reviews.request.show');
Route::post('/review/{token}', [ReviewRequestController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:leads')->name('reviews.request.store');
