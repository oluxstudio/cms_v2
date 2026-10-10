<?php

// memberships module — ADMIN pages — inside the auth group, prefix none: paths start /{siteID}/… ; apply ->middleware(['feature:memberships', 'perm:memberships.view']).
// Owned by the memberships module; keep routes for this module here.

use App\Modules\Memberships\Http\MembershipAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/{siteID}/memberships', MembershipAdminController::class)
    ->middleware(['feature:memberships', 'perm:memberships.view'])->name('site.memberships');
