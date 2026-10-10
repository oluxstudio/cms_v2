<?php

// memberships module — PUBLIC JSON API for template sites — stateless (no session/CSRF), throttle:api; paths start /sites/{siteName}/….
// Owned by the memberships module; keep routes for this module here.

use App\Modules\Memberships\Http\MembershipApiController;
use Illuminate\Support\Facades\Route;

Route::get('/sites/{siteName}/memberships/tiers', [MembershipApiController::class, 'tiers'])->name('api.memberships.tiers');
Route::post('/sites/{siteName}/memberships/join', [MembershipApiController::class, 'join'])->middleware(['throttle:booking-write', 'site.origin'])->name('api.memberships.join');
Route::post('/sites/{siteName}/memberships/login', [MembershipApiController::class, 'login'])->middleware(['throttle:booking-write', 'site.origin'])->name('api.memberships.login');
Route::get('/sites/{siteName}/memberships/me', [MembershipApiController::class, 'me'])->name('api.memberships.me');
Route::post('/sites/{siteName}/memberships/logout', [MembershipApiController::class, 'logout'])->name('api.memberships.logout');
Route::get('/sites/{siteName}/memberships/access', [MembershipApiController::class, 'access'])->name('api.memberships.access');
