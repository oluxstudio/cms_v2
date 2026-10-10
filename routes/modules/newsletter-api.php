<?php

// newsletter module — PUBLIC JSON API for template sites — stateless (no session/CSRF), throttle:api; paths start /sites/{siteName}/….
// Owned by the newsletter module; keep routes for this module here.

use App\Modules\Newsletter\Http\Controllers\NewsletterApiController;
use Illuminate\Support\Facades\Route;

// GET /api/sites/{siteName}/newsletter → { enabled, headline, double_opt_in, subscribe_url, unsubscribe_url }
// (signups keep using POST /api/sites/{siteName}/subscribe and /unsubscribe in routes/api.php)
Route::get('/sites/{siteName}/newsletter', [NewsletterApiController::class, 'show'])->name('api.newsletter.show');
