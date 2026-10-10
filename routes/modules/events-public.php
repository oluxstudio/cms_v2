<?php

// events module — PUBLIC web routes (signed links, success pages, webhooks) — loaded BEFORE the /preview/{siteName}/{pageUrl} catch-all; no auth.
// Owned by the events module; keep routes for this module here.

use App\Modules\Events\Http\EventsPublicController;
use Illuminate\Support\Facades\Route;

// Paid-ticket webhooks arrive on the platform Connect endpoint (/stripe/sites/webhook)
// and reach App\Modules\Events\TicketFulfilment via config/payments.php 'fulfilment'.
Route::middleware('feature:events')->group(function () {
    Route::get('/preview/{siteName}/tickets/{code}', [EventsPublicController::class, 'ticket'])->name('public.events.ticket');
    Route::get('/preview/{siteName}/events/success/{order}', [EventsPublicController::class, 'success'])->name('public.events.success');
});
