<?php

// events module — PUBLIC JSON API for template sites — stateless (no session/CSRF), throttle:api; paths start /sites/{siteName}/….
// Owned by the events module; keep routes for this module here.

use App\Modules\Events\Http\EventsApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:events')->group(function () {
    Route::get('/sites/{siteName}/events', [EventsApiController::class, 'index'])->name('api.events.index');
    Route::get('/sites/{siteName}/events/{slug}', [EventsApiController::class, 'show'])->name('api.events.show');
    Route::post('/sites/{siteName}/events/{slug}/order', [EventsApiController::class, 'order'])
        ->middleware(['throttle:booking-write', 'site.origin'])->name('api.events.order');
});
