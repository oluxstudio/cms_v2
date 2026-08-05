<?php

use App\Http\Controllers\Api\BookingApiController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\FormSchemaController;
use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SiteContentController;
use App\Http\Controllers\Api\TemplatePreviewController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by bootstrap/app.php under the "api" middleware
| group, which means:
|   - No session / no CSRF (stateless by design)
|   - Automatic /api prefix — paths here start with /sites/...
|   - throttle:api rate-limiting applied automatically
|
| Public URL: POST /api/sites/{siteName}/form/{formName}  etc.
|
*/

// ── Public media library
// GET /api/media                        → all public media (filter: type, search, site, per_page)
// GET /api/sites/{siteName}/media       → a single site's media
Route::get('/media',                  [MediaController::class, 'index'])->name('api.media.index');
Route::get('/sites/{siteName}/media', [MediaController::class, 'siteIndex'])->name('api.site.media');

// ── Site content (used by Vue.js / Nuxt templates for preview / offline generation)
// GET /api/sites/{siteName}/content     → whole site (all pages → components → nodes)
// GET /api/sites/{siteName}/page?url=/  → a single page's content tree
Route::get('/sites/{siteName}/content', [SiteContentController::class, 'show'])->name('api.site.content');
Route::get('/sites/{siteName}/page',    [SiteContentController::class, 'page'])->name('api.site.page');

// ── Template preview (render a template's design BEFORE installing it to a site)
// GET /api/templates/preview/{ref}      → {ref} = SiteTemplate id | catalog slug | built-in key
Route::get('/templates/preview/{ref}', [TemplatePreviewController::class, 'show'])->name('api.template.preview');

// ── Newsletter subscriptions
Route::post('/sites/{siteName}/subscribe',   [SubscriptionController::class, 'store'])
     ->name('api.subscribe');
Route::post('/sites/{siteName}/unsubscribe', [SubscriptionController::class, 'destroy'])
     ->name('api.unsubscribe');

// ── Contact form
Route::post('/sites/{siteName}/contact', [ContactController::class, 'store'])
     ->name('api.contact');

// ── Generic custom forms
// GET  → fetch field schema + validation rules (used by frontend before rendering/submitting)
// POST → submit data; validated server-side against the same rules
Route::get( '/sites/{siteName}/form/{formName}', [FormSchemaController::class,    'show'])->name('api.form.schema');
Route::post('/sites/{siteName}/form/{formName}', [FormSubmissionController::class,'store'])->name('api.form');

// ── BlockKit form blocks — the block tree IS the schema
Route::post('/sites/{siteName}/block-form/{blockId}', [\App\Http\Controllers\Api\BlockFormController::class, 'store'])->name('api.block-form');

// ── Declarative modules (powers the in-site ModuleWidget: list + submit)
Route::get( '/sites/{siteName}/modules/{key}/schema',     [ModuleController::class, 'schema'])->name('api.module.schema');
Route::get( '/sites/{siteName}/modules/{key}/items',      [ModuleController::class, 'items'])->name('api.module.items');
Route::get( '/sites/{siteName}/modules/{key}/items/{id}', [ModuleController::class, 'item'])->name('api.module.item');
Route::post('/sites/{siteName}/modules/{key}/items',      [ModuleController::class, 'store'])->name('api.module.store');

// ── Blog posts (rendered by the template-folder site apps; view/like feed the tiles)
Route::get( '/sites/{siteName}/posts',              [\App\Http\Controllers\Api\PostApiController::class, 'index'])->name('api.posts.index');
Route::get( '/sites/{siteName}/posts/{slug}',       [\App\Http\Controllers\Api\PostApiController::class, 'show'])->name('api.posts.show');
Route::post('/sites/{siteName}/posts/{slug}/view',  [\App\Http\Controllers\Api\PostApiController::class, 'view'])->name('api.posts.view');
Route::post('/sites/{siteName}/posts/{slug}/like',  [\App\Http\Controllers\Api\PostApiController::class, 'like'])->name('api.posts.like');

// ── Estimator (instant trade cost/time quotes + lead capture)
Route::get( '/sites/{siteName}/estimator/config',  [\App\Http\Controllers\Api\EstimatorController::class, 'config'])->name('api.estimator.config');
Route::post('/sites/{siteName}/estimator',         [\App\Http\Controllers\Api\EstimatorController::class, 'estimate'])->name('api.estimator.quote');
Route::post('/sites/{siteName}/estimator/request', [\App\Http\Controllers\Api\EstimatorController::class, 'store'])->name('api.estimator.request');

// ── Client-site lead APIs ────────────────────────────────────────────────────
// Get a quote (same engine as /estimator — friendlier path for client sites).
Route::post('/sites/{siteName}/quote', [\App\Http\Controllers\Api\EstimatorController::class, 'estimate'])->name('api.quote');
// Submit a quote request (saves the lead + emails + dashboard notification).
Route::post('/sites/{siteName}/quote/request', [\App\Http\Controllers\Api\EstimatorController::class, 'store'])->name('api.quote.request');
// Submit plain interest ("I'm interested") — Contact + notification + owner email.
Route::post('/sites/{siteName}/interest', [\App\Http\Controllers\Api\InterestController::class, 'store'])->name('api.interest');

// ── Bookings (powers the in-site calendar widget)
// Booking engine — one API, three archetypes (service.kind: slot|stay|trip).
Route::get( '/sites/{siteName}/booking/config',       [BookingApiController::class, 'config'])->name('api.booking.config');
Route::get( '/sites/{siteName}/booking/availability', [BookingApiController::class, 'availability'])->name('api.booking.availability');
Route::post('/sites/{siteName}/booking',              [BookingApiController::class, 'store'])->name('api.booking.store');
Route::get( '/sites/{siteName}/booking/{reference}',  [BookingApiController::class, 'show'])
    ->where('reference', '[A-Za-z0-9]{6,14}')->name('api.booking.show');

// Token-authenticated management (Authorization: Bearer <api token>).
Route::middleware('auth.token')->group(function () {
    Route::get(  '/sites/{siteName}/bookings',      [\App\Http\Controllers\Api\BookingAdminApiController::class, 'index'])->name('api.bookings.index');
    Route::patch('/sites/{siteName}/bookings/{id}', [\App\Http\Controllers\Api\BookingAdminApiController::class, 'update'])->name('api.bookings.update');
});
