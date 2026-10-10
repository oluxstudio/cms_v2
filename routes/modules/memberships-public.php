<?php

// memberships module — PUBLIC web routes (signed links, success pages, webhooks) — loaded BEFORE the /preview/{siteName}/{pageUrl} catch-all; no auth.
// Owned by the memberships module; keep routes for this module here.
// Recurring Stripe events arrive on the platform Connect webhook (/stripe/sites/webhook).

use App\Modules\Memberships\Http\MembershipPublicController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:memberships')->group(function () {
    Route::get('/preview/{siteName}/membership', [MembershipPublicController::class, 'manage'])->name('memberships.public.manage');
    Route::get('/preview/{siteName}/membership/auth/{token}', [MembershipPublicController::class, 'auth'])->name('memberships.public.auth');
    Route::get('/preview/{siteName}/membership/welcome', [MembershipPublicController::class, 'success'])->name('memberships.public.success');
    Route::post('/preview/{siteName}/membership/login', [MembershipPublicController::class, 'login'])->middleware('throttle:booking-write')->name('memberships.public.login');
    Route::post('/preview/{siteName}/membership/cancel', [MembershipPublicController::class, 'cancel'])->name('memberships.public.cancel');
    Route::post('/preview/{siteName}/membership/portal', [MembershipPublicController::class, 'portal'])->name('memberships.public.portal');
    Route::post('/preview/{siteName}/membership/signout', [MembershipPublicController::class, 'signout'])->name('memberships.public.signout');
});
