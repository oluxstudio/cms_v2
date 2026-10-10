<?php

// events module — ADMIN pages — inside the auth group, prefix none: paths start /{siteID}/… ; apply ->middleware(['feature:events', 'perm:events.view']).
// Owned by the events module; keep routes for this module here.

use App\Modules\Events\Http\EventsAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/{siteID}/events', EventsAdminController::class)
    ->middleware(['feature:events', 'perm:events.view'])->name('site.events');
