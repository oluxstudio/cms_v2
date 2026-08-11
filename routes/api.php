<?php

use App\Http\Controllers\Api\BlockFormController;
use App\Http\Controllers\Api\BookingAdminApiController;
use App\Http\Controllers\Api\BookingApiController;
use App\Http\Controllers\Api\CollectionApiController;
use App\Http\Controllers\Api\CommentApiController;
use App\Http\Controllers\Api\ComponentApiController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EstimatorController;
use App\Http\Controllers\Api\FormApiController;
use App\Http\Controllers\Api\FormSchemaController;
use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\InterestController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\PageApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\SiteContentController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TemplatePreviewController;
use App\Http\Controllers\Api\VisitTrackController;
use App\Models\Site;
use Illuminate\Http\Request;
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

// ── Public metadata (enum vocabularies for API / MCP clients)
Route::get('/meta', [MetaController::class, 'show'])->name('api.meta');

// ── Visitor analytics beacon (client sites ping this on every page view)
Route::post('/sites/{siteName}/track', [VisitTrackController::class, 'store'])
    ->middleware(['throttle:track', 'site.origin'])->name('api.track');

// ── Public media library
// GET /api/media                        → all public media (filter: type, search, site, per_page)
// GET /api/sites/{siteName}/media       → a single site's media
Route::get('/media', [MediaController::class, 'index'])->name('api.media.index');
Route::get('/sites/{siteName}/media', [MediaController::class, 'siteIndex'])->name('api.site.media');

// ── Site content (used by Vue.js / Nuxt templates for preview / offline generation)
// GET /api/sites/{siteName}/content     → whole site (all pages → components → nodes)
// GET /api/sites/{siteName}/page?url=/  → a single page's content tree
Route::get('/sites/{siteName}/content', [SiteContentController::class, 'show'])->name('api.site.content');
Route::get('/sites/{siteName}/page', [SiteContentController::class, 'page'])->name('api.site.page');

// ── Template preview (render a template's design BEFORE installing it to a site)
// GET /api/templates/preview/{ref}      → {ref} = SiteTemplate id | catalog slug | built-in key
Route::get('/templates/preview/{ref}', [TemplatePreviewController::class, 'show'])->name('api.template.preview');

// ── Newsletter subscriptions
Route::post('/sites/{siteName}/subscribe', [SubscriptionController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])
    ->name('api.subscribe');
Route::post('/sites/{siteName}/unsubscribe', [SubscriptionController::class, 'destroy'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])
    ->name('api.unsubscribe');

// ── Contact form
Route::post('/sites/{siteName}/contact', [ContactController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])
    ->name('api.contact');

// ── Generic custom forms
// GET  → fetch field schema + validation rules (used by frontend before rendering/submitting)
// POST → submit data; validated server-side against the same rules
Route::get('/sites/{siteName}/form/{formName}', [FormSchemaController::class,    'show'])->name('api.form.schema');
Route::post('/sites/{siteName}/form/{formName}', [FormSubmissionController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.form');

// ── BlockKit form blocks — the block tree IS the schema
Route::post('/sites/{siteName}/block-form/{blockId}', [BlockFormController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.block-form');

// ── Declarative modules (powers the in-site ModuleWidget: list + submit)
Route::get('/sites/{siteName}/modules/{key}/schema', [ModuleController::class, 'schema'])->name('api.module.schema');
Route::get('/sites/{siteName}/modules/{key}/items', [ModuleController::class, 'items'])->name('api.module.items');
Route::get('/sites/{siteName}/modules/{key}/items/{id}', [ModuleController::class, 'item'])->name('api.module.item');
Route::post('/sites/{siteName}/modules/{key}/items', [ModuleController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.module.store');

// ── Blog posts (rendered by the template-folder site apps; view/like feed the tiles)
Route::get('/sites/{siteName}/posts', [PostApiController::class, 'index'])->name('api.posts.index');
Route::get('/sites/{siteName}/posts/{slug}', [PostApiController::class, 'show'])->name('api.posts.show');
Route::post('/sites/{siteName}/posts/{slug}/view', [PostApiController::class, 'view'])->middleware('throttle:engagement')->name('api.posts.view');
Route::post('/sites/{siteName}/posts/{slug}/like', [PostApiController::class, 'like'])->middleware('throttle:engagement')->name('api.posts.like');

// ── Post comments (public: read approved + submit for moderation)
Route::get('/sites/{siteName}/posts/{slug}/comments', [CommentApiController::class, 'index'])->name('api.comments.index');
Route::post('/sites/{siteName}/posts/{slug}/comments', [CommentApiController::class, 'store'])
    ->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.comments.store');

// ── Estimator (instant trade cost/time quotes + lead capture)
Route::get('/sites/{siteName}/estimator/config', [EstimatorController::class, 'config'])->name('api.estimator.config');
Route::post('/sites/{siteName}/estimator', [EstimatorController::class, 'estimate'])->name('api.estimator.quote');
Route::post('/sites/{siteName}/estimator/request', [EstimatorController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.estimator.request');

// ── Client-site lead APIs ────────────────────────────────────────────────────
// Get a quote (same engine as /estimator — friendlier path for client sites).
Route::post('/sites/{siteName}/quote', [EstimatorController::class, 'estimate'])->name('api.quote');
// Submit a quote request (saves the lead + emails + dashboard notification).
Route::post('/sites/{siteName}/quote/request', [EstimatorController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.quote.request');
// Submit plain interest ("I'm interested") — Contact + notification + owner email.
Route::post('/sites/{siteName}/interest', [InterestController::class, 'store'])->middleware(['throttle:leads', 'site.origin', 'honeypot'])->name('api.interest');

// ── Bookings (powers the in-site calendar widget)
// Booking engine — one API, three archetypes (service.kind: slot|stay|trip).
Route::get('/sites/{siteName}/booking/config', [BookingApiController::class, 'config'])->name('api.booking.config');
Route::get('/sites/{siteName}/booking/availability', [BookingApiController::class, 'availability'])->name('api.booking.availability');
Route::post('/sites/{siteName}/booking', [BookingApiController::class, 'store'])->middleware(['throttle:booking-write', 'site.origin'])->name('api.booking.store');
Route::get('/sites/{siteName}/booking/{reference}', [BookingApiController::class, 'show'])
    ->where('reference', '[A-Za-z0-9]{6,14}')->name('api.booking.show');

// ── Components (classic content components + their nodes) ───────────────────
Route::get('/sites/{siteName}/components', [ComponentApiController::class, 'index'])->name('api.components.index');
Route::get('/sites/{siteName}/components/{id}', [ComponentApiController::class, 'show'])->name('api.components.show');

// ── Pages (page records + EAV attributes; content lives on /content, /page) ──
Route::get('/sites/{siteName}/pages', [PageApiController::class, 'index'])->name('api.pages.index');
Route::get('/sites/{siteName}/pages/{id}', [PageApiController::class, 'show'])->name('api.pages.show');

// ── Collections (public collections + their published items) ────────────────
Route::get('/sites/{siteName}/collections', [CollectionApiController::class, 'index'])->name('api.collections.index');
Route::get('/sites/{siteName}/collections/{id}', [CollectionApiController::class, 'show'])->name('api.collections.show');

// ── Forms directory (active forms + schemas; single-form schema/submit above)
Route::get('/sites/{siteName}/forms', [FormApiController::class, 'index'])->name('api.forms.index');

// Token-authenticated management (Authorization: Bearer <api token>).
Route::middleware(['auth.token', 'throttle:token-api'])->group(function () {
    // Token introspection — who am I, what may I touch? (used by MCP/clients)
    Route::get('/me', function (Request $request) {
        $token = $request->attributes->get('api_token');
        $user = $request->attributes->get('api_token_user');

        return response()->json([
            'user' => $user?->name,
            'token' => $token->name,
            'site' => $token->site?->name,           // null = any site the user can access
            'abilities' => $token->abilities,        // null = all the user's permissions
            'expires_at' => $token->expires_at?->toIso8601String(),
            'sites' => $token->site
                ? [$token->site->name]
                : Site::all()->filter(fn ($s) => $s->accessibleBy($user))->pluck('name')->values(),
        ]);
    })->name('api.me');
    Route::get('/sites/{siteName}/bookings', [BookingAdminApiController::class, 'index'])->name('api.bookings.index');
    Route::patch('/sites/{siteName}/bookings/{id}', [BookingAdminApiController::class, 'update'])->name('api.bookings.update');

    // Components CRUD (writes).
    Route::post('/sites/{siteName}/components', [ComponentApiController::class, 'store'])->name('api.components.store');
    Route::patch('/sites/{siteName}/components/{id}', [ComponentApiController::class, 'update'])->name('api.components.update');
    Route::delete('/sites/{siteName}/components/{id}', [ComponentApiController::class, 'destroy'])->name('api.components.destroy');

    // Comment moderation (posts.manage).
    Route::get('/sites/{siteName}/posts/{slug}/comments/moderate', [CommentApiController::class, 'moderate'])->name('api.comments.moderate');
    Route::patch('/sites/{siteName}/comments/{id}', [CommentApiController::class, 'update'])->name('api.comments.update');
    Route::delete('/sites/{siteName}/comments/{id}', [CommentApiController::class, 'destroy'])->name('api.comments.destroy');

    // Posts CRUD (writes; public reads live above).
    Route::post('/sites/{siteName}/posts', [PostApiController::class, 'store'])->name('api.posts.store');
    Route::patch('/sites/{siteName}/posts/{slug}', [PostApiController::class, 'update'])->name('api.posts.update');
    Route::delete('/sites/{siteName}/posts/{slug}', [PostApiController::class, 'destroy'])->name('api.posts.destroy');

    // Pages CRUD (writes).
    Route::post('/sites/{siteName}/pages', [PageApiController::class, 'store'])->name('api.pages.store');
    Route::patch('/sites/{siteName}/pages/{id}', [PageApiController::class, 'update'])->name('api.pages.update');
    Route::delete('/sites/{siteName}/pages/{id}', [PageApiController::class, 'destroy'])->name('api.pages.destroy');

    // Assets (media) CRUD (writes; public reads live above).
    Route::post('/sites/{siteName}/media', [MediaController::class, 'store'])->name('api.media.store');
    Route::patch('/sites/{siteName}/media/{id}', [MediaController::class, 'update'])->name('api.media.update');
    Route::delete('/sites/{siteName}/media/{id}', [MediaController::class, 'destroy'])->name('api.media.destroy');

    // Forms CRUD (writes; schema + submit endpoints above are public).
    Route::post('/sites/{siteName}/forms', [FormApiController::class, 'store'])->name('api.forms.store');
    Route::patch('/sites/{siteName}/forms/{formName}', [FormApiController::class, 'update'])->name('api.forms.update');
    Route::delete('/sites/{siteName}/forms/{formName}', [FormApiController::class, 'destroy'])->name('api.forms.destroy');

    // Collections + items CRUD (writes).
    Route::post('/sites/{siteName}/collections', [CollectionApiController::class, 'store'])->name('api.collections.store');
    Route::patch('/sites/{siteName}/collections/{id}', [CollectionApiController::class, 'update'])->name('api.collections.update');
    Route::delete('/sites/{siteName}/collections/{id}', [CollectionApiController::class, 'destroy'])->name('api.collections.destroy');
    Route::post('/sites/{siteName}/collections/{id}/items', [CollectionApiController::class, 'storeItem'])->name('api.collections.items.store');
    Route::patch('/sites/{siteName}/collections/{id}/items/{itemId}', [CollectionApiController::class, 'updateItem'])->name('api.collections.items.update');
    Route::delete('/sites/{siteName}/collections/{id}/items/{itemId}', [CollectionApiController::class, 'destroyItem'])->name('api.collections.items.destroy');
});

/*
|--------------------------------------------------------------------------
| Token-context API — /api/site/...
|--------------------------------------------------------------------------
| The client only sends its key (Authorization: Bearer <CMS_SITE_KEY>); the
| site is resolved from the key by the token.site middleware and injected as
| the {siteName} route parameter, so these reuse the SAME controllers as the
| /api/sites/{siteName}/... routes above — no site name needed in the URL.
*/
Route::prefix('site')->middleware(['auth.token', 'token.site', 'throttle:token-api'])->group(function () {

    // Which site does this key act on? (introspection)
    Route::get('/', function (Request $request) {
        $token = $request->attributes->get('api_token');
        $site = $token->site ?? Site::where('name', $request->route('siteName'))->first();

        return response()->json([
            'site' => $site?->name,
            'user' => $token->user?->name,
            'token' => $token->name,
            'abilities' => $token->abilities,   // null = all the user's permissions
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]);
    })->name('api.site.self');

    // ── Reads (site from key) ──────────────────────────────────────────
    Route::get('/content', [SiteContentController::class, 'show']);
    Route::get('/page', [SiteContentController::class, 'page']);
    Route::get('/media', [MediaController::class, 'siteIndex']);
    Route::get('/components', [ComponentApiController::class, 'index']);
    Route::get('/components/{id}', [ComponentApiController::class, 'show']);
    Route::get('/collections', [CollectionApiController::class, 'index']);
    Route::get('/collections/{id}', [CollectionApiController::class, 'show']);
    Route::get('/forms', [FormApiController::class, 'index']);
    Route::get('/pages', [PageApiController::class, 'index']);
    Route::get('/pages/{id}', [PageApiController::class, 'show']);
    Route::get('/posts', [PostApiController::class, 'index']);
    Route::get('/posts/{slug}', [PostApiController::class, 'show']);
    Route::get('/posts/{slug}/comments', [CommentApiController::class, 'index']);

    // ── Writes (same abilities as the /api/sites/... equivalents) ───────
    Route::post('/components', [ComponentApiController::class, 'store']);
    Route::patch('/components/{id}', [ComponentApiController::class, 'update']);
    Route::delete('/components/{id}', [ComponentApiController::class, 'destroy']);

    Route::post('/collections', [CollectionApiController::class, 'store']);
    Route::patch('/collections/{id}', [CollectionApiController::class, 'update']);
    Route::delete('/collections/{id}', [CollectionApiController::class, 'destroy']);
    Route::post('/collections/{id}/items', [CollectionApiController::class, 'storeItem']);
    Route::patch('/collections/{id}/items/{itemId}', [CollectionApiController::class, 'updateItem']);
    Route::delete('/collections/{id}/items/{itemId}', [CollectionApiController::class, 'destroyItem']);

    Route::post('/forms', [FormApiController::class, 'store']);
    Route::patch('/forms/{formName}', [FormApiController::class, 'update']);
    Route::delete('/forms/{formName}', [FormApiController::class, 'destroy']);

    Route::post('/pages', [PageApiController::class, 'store']);
    Route::patch('/pages/{id}', [PageApiController::class, 'update']);
    Route::delete('/pages/{id}', [PageApiController::class, 'destroy']);

    Route::post('/posts', [PostApiController::class, 'store']);
    Route::patch('/posts/{slug}', [PostApiController::class, 'update']);
    Route::delete('/posts/{slug}', [PostApiController::class, 'destroy']);

    Route::post('/media', [MediaController::class, 'store']);
    Route::patch('/media/{id}', [MediaController::class, 'update']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);

    Route::get('/posts/{slug}/comments/moderate', [CommentApiController::class, 'moderate']);
    Route::patch('/comments/{id}', [CommentApiController::class, 'update']);
    Route::delete('/comments/{id}', [CommentApiController::class, 'destroy']);

    Route::get('/bookings', [BookingAdminApiController::class, 'index']);
    Route::patch('/bookings/{id}', [BookingAdminApiController::class, 'update']);
});
