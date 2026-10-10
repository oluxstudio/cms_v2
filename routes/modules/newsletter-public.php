<?php

// newsletter module — PUBLIC web routes (signed links, success pages, webhooks) — loaded BEFORE the /preview/{siteName}/{pageUrl} catch-all; no auth.
// Owned by the newsletter module; keep routes for this module here.

use App\Modules\Newsletter\Http\Controllers\NewsletterPublicController;
use Illuminate\Support\Facades\Route;

// Links inside newsletter emails. Tokens are per-subscriber / per-message random
// strings; the click redirect is additionally URL-signed (no open redirect).
// POST /newsletter/unsubscribe/* is CSRF-exempt (bootstrap/app.php) for RFC 8058 one-click.
Route::prefix('newsletter')->middleware('throttle:60,1')->group(function () {
    Route::get('/confirm/{token}', [NewsletterPublicController::class, 'confirm'])->name('newsletter.confirm');
    Route::get('/unsubscribe/{token}', [NewsletterPublicController::class, 'showUnsubscribe'])->name('newsletter.unsubscribe');
    Route::post('/unsubscribe/{token}', [NewsletterPublicController::class, 'unsubscribe'])->name('newsletter.unsubscribe.post');
});
Route::get('/newsletter/o/{send}.gif', [NewsletterPublicController::class, 'open'])->name('newsletter.open');
Route::get('/newsletter/c/{send}', [NewsletterPublicController::class, 'click'])->middleware('signed')->name('newsletter.click');
