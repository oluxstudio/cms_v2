<?php

// network module — ADMIN pages — inside the auth group, prefix none: paths start /{siteID}/…
// Core (no feature: gate) — plan gating happens inside the pages (trial = browse only).

use App\Modules\Network\Http\NetworkAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/{siteID}/network', [NetworkAdminController::class, 'index'])
    ->middleware('perm:network.view')->name('site.network');
Route::get('/{siteID}/earnings', [NetworkAdminController::class, 'earnings'])
    ->middleware('perm:earnings.view')->name('site.earnings');
