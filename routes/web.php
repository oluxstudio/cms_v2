<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StoreFrontController;
use App\Http\Controllers\DonateController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\TemplateCommerceController;
use Livewire\Volt\Volt;

// ── Auth routes first (must be before the /{siteID} catch-all)
require __DIR__.'/auth.php';

// Marketplace Stripe Connect webhook (signature-verified, CSRF-exempt; public).
Route::post('/stripe/templates/webhook', [TemplateCommerceController::class, 'webhook'])->name('templates.webhook');

// Platform subscription billing webhook (signature-verified, CSRF-exempt).
Route::post('/stripe/subscription/webhook', function (\Illuminate\Http\Request $request) {
    try {
        app(\App\Services\PlatformBilling::class)
            ->handleWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Invalid signature.'], 400);
    }

    return response()->json(['received' => true]);
})->name('subscription.webhook');

// Team invitation acceptance (guest-reachable; the emailed token IS the
// email verification). Declared before the auth group so it beats /{siteID}.
Route::get('/invite/{token}', function (string $token) {
    return view('accept-invite', ['token' => $token]);
})->name('invite.accept');

// ── SPA fallback for template previews (public) ──────────────────────────────
// Static files under public/nuxt-preview/{key}/ are served directly; any OTHER
// path (deep links like /nuxt-preview/tekstack/about-us on hard reload) must
// serve the SPA shell so its client router can render the CMS page.
Route::get('nuxt-preview/{key}/{any?}', function (string $key) {
    abort_unless(preg_match('/^[A-Za-z0-9_-]+$/', $key), 404);
    $index = public_path("nuxt-preview/{$key}/index.html");
    if (! \Illuminate\Support\Facades\File::exists($index)) {
        // Deleted or never-built preview: fail clearly, never bounce to auth.
        abort(404, "Preview for template “{$key}” has not been built. Run: php artisan nuxt:preview-build --template={$key}");
    }

    return response()->file($index, [
        'X-Olux-Fallback' => '1',
        'Cache-Control'   => 'no-cache, must-revalidate', // never pin a stale app shell
    ]);
})->where('any', '.*')->name('nuxt-preview.fallback');


// ── Public feature pages (MUST be before the public.page catch-all) ──────────
// Store. Static segments declared before the /{slug} param so they win.
Route::post('/preview/{siteName}/store/webhook',   [StoreFrontController::class, 'webhook'])->middleware('feature:store')->name('public.store.webhook');
Route::middleware('feature:store')->group(function () {
    Route::get ('/preview/{siteName}/store',          [StoreFrontController::class, 'index'])->name('public.store');
    Route::get ('/preview/{siteName}/store/success',  [StoreFrontController::class, 'success'])->name('public.store.success');
    Route::get ('/preview/{siteName}/store/cancel',   [StoreFrontController::class, 'cancel'])->name('public.store.cancel');
    Route::post('/preview/{siteName}/store/checkout', [StoreFrontController::class, 'checkout'])->name('public.store.checkout');
    Route::get ('/preview/{siteName}/store/{slug}',   [StoreFrontController::class, 'show'])->name('public.store.show');
});

// Donations.
Route::post('/preview/{siteName}/donate/webhook',  [DonateController::class, 'webhook'])->middleware('feature:donations')->name('public.donate.webhook');
Route::middleware('feature:donations')->group(function () {
    Route::get ('/preview/{siteName}/donate',          [DonateController::class, 'index'])->name('public.donate');
    Route::get ('/preview/{siteName}/donate/success',  [DonateController::class, 'success'])->name('public.donate.success');
    Route::post('/preview/{siteName}/donate/checkout', [DonateController::class, 'checkout'])->name('public.donate.checkout');
});

// Bookings. Static segments (success) declared before the /{service} param.
Route::middleware('feature:bookings')->group(function () {
    Route::get ('/preview/{siteName}/book',           [BookingController::class, 'index'])->name('public.book');
    Route::get ('/preview/{siteName}/book/success',   [BookingController::class, 'success'])->name('public.book.success');
    Route::post('/preview/{siteName}/book',           [BookingController::class, 'store'])->name('public.book.store');
    Route::get ('/preview/{siteName}/book/{service}', [BookingController::class, 'show'])->name('public.book.show');
    // Stripe payment webhook (signature-verified; CSRF-exempt in bootstrap/app.php).
    Route::post('/preview/{siteName}/booking/webhook', [BookingController::class, 'webhook'])->name('public.book.webhook');
});

// Invoices: tokenized public pay page + Stripe webhook.
Route::post('/preview/{siteName}/invoice/webhook', [\App\Http\Controllers\PublicInvoiceController::class, 'webhook'])->middleware('feature:invoices')->name('public.invoice.webhook');
Route::middleware('feature:invoices')->group(function () {
    Route::get ('/preview/{siteName}/invoice/{token}',         [\App\Http\Controllers\PublicInvoiceController::class, 'show'])->name('public.invoice');
    Route::post('/preview/{siteName}/invoice/{token}/pay',     [\App\Http\Controllers\PublicInvoiceController::class, 'pay'])->name('public.invoice.pay');
    Route::get ('/preview/{siteName}/invoice/{token}/success', [\App\Http\Controllers\PublicInvoiceController::class, 'success'])->name('public.invoice.success');
    Route::get ('/preview/{siteName}/invoice/{token}/open.gif', [\App\Http\Controllers\PublicInvoiceController::class, 'pixel'])->name('public.invoice.pixel');
    Route::get ('/preview/{siteName}/invoice/{token}/pdf',      [\App\Http\Controllers\PublicInvoiceController::class, 'pdf'])->name('public.invoice.pdf');
    Route::get ('/preview/{siteName}/billing/{token}',          [\App\Http\Controllers\PublicInvoiceController::class, 'portal'])->name('public.invoice.portal');
});

// Live X / Twitter feed.
Route::get('/preview/{siteName}/feed', [FeedController::class, 'index'])->middleware('feature:twitter')->name('public.feed');

// ── Public page rendering (catch-all — keep last in the public group)
Route::get('/preview/{siteName}/{pageUrl}', [PublicPageController::class, 'show'])
     ->where('pageUrl', '.*')
     ->name('public.page');

// ── Auth-protected app routes
Route::middleware('auth')->group(function () {
    Route::get('/',          [SiteController::class, 'index'])->name('home');

    // Account subscription — the 5-tier plan page (trial → paid upgrades).
    Route::view('/account/subscription', 'subscription')->name('account.subscription');
    // Stripe Checkout return: verify the session server-side, activate, go back.
    // Platform admin — client accounts + individual pricing (super admins only).
    Route::get('/admin/accounts', function () {
        abort_unless(auth()->user()?->isSuper(), 403);

        return view('platform-accounts');
    })->name('admin.accounts');

    Route::get('/account/subscription/success', function (\Illuminate\Http\Request $request) {
        $back = null;
        try {
            $back = app(\App\Services\PlatformBilling::class)
                ->activateFromSession($request->user(), (string) $request->query('session_id'));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect($back ?: route('account.subscription'));
    })->name('account.subscription.success');

    // ── BlockKit: the jigsaw block tree — ONE mutation API, two clients
    //    (Vue editor via these routes; AI assistant via tool dispatch → same service).
    Route::prefix('blockkit/pages/{page}')->name('blockkit.')->group(function () {
        Route::get ('/tree',       [\App\Http\Controllers\BlockKitController::class, 'tree'])->name('tree');
        Route::post('/insert',     [\App\Http\Controllers\BlockKitController::class, 'insert'])->name('insert');
        Route::post('/update',     [\App\Http\Controllers\BlockKitController::class, 'update'])->name('update');
        Route::post('/move',       [\App\Http\Controllers\BlockKitController::class, 'move'])->name('move');
        Route::post('/delete',     [\App\Http\Controllers\BlockKitController::class, 'delete'])->name('delete');
        Route::post('/duplicate',  [\App\Http\Controllers\BlockKitController::class, 'duplicate'])->name('duplicate');
    });
    Volt::route('/settings', 'user-settings')->name('settings');
    Route::get('/templates', [SiteController::class, 'templates'])->name('templates');
    Route::get('/my-templates', fn () => view('my-templates'))->name('my.templates');
    // Creator payouts (Stripe Connect onboarding) + buyer checkout returns.
    Route::get('/creator/connect',          [TemplateCommerceController::class, 'connect'])->name('creator.connect');
    Route::get('/creator/connect/return',   [TemplateCommerceController::class, 'connectReturn'])->name('creator.connect.return');
    Route::get('/templates/checkout/success', [TemplateCommerceController::class, 'checkoutSuccess'])->name('templates.checkout.success');
    Route::get('/templates/checkout/cancel',  [TemplateCommerceController::class, 'checkoutCancel'])->name('templates.checkout.cancel');

    Route::get('/greeting', function () {
        return view('welcome');
    });

    // Site routes — /{siteID} is a catch-all, keep last
    // Note: NOT named 'dashboard' to avoid conflicting with Laravel's guest-redirect logic
    // Each page is gated by the role permission it maps to (config/permissions.php).
    Route::post('/{siteID}/generate',                [SiteController::class, 'generate'])->middleware('perm:builder.manage')->name('site.generate');
    Route::get('/{siteID}/blocks', function ($siteID) {
        $site = \App\Models\Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->allows(\Illuminate\Support\Facades\Auth::user(), 'builder.manage'), 403);

        return view('block-editor-page', ['site' => $site]);
    })->name('blocks');
    Route::get('/{siteID}',                          [SiteController::class, 'dashboard'])->name('site.dashboard');
    Route::get('/{siteID}/dashboard',                [SiteController::class, 'dashboard']);
    Route::get('/{siteID}/pages',                    [SiteController::class, 'pages'])->middleware('perm:pages.view')->name('pages');
    Route::get('/{siteID}/collections',              [SiteController::class, 'collections'])->middleware('perm:collections.view')->name('collections');
    Route::get('/{siteID}/media',                    [SiteController::class, 'media'])->middleware('perm:media.view')->name('media');
    // The Media page is presented as "Assets" — keep both URLs working.
    Route::redirect('/{siteID}/assets', '/{siteID}/media');
    Route::get('/{siteID}/analytics',                [SiteController::class, 'analytics'])->middleware('perm:analytics.view')->name('analytics');
    // Templates now live in the Marketplace — old links land there.
    Route::redirect('/{siteID}/templates', '/{siteID}/marketplace')->name('site.templates');
    Route::get('/{siteID}/marketplace',              [SiteController::class, 'marketplace'])->middleware('perm:addons.manage')->name('site.marketplace');
    Route::get('/{siteID}/publish',                  [SiteController::class, 'publish'])->middleware('perm:publish.manage')->name('site.publish');
    Route::get('/{siteID}/team',                     [SiteController::class, 'team'])->middleware('perm:team.manage')->name('site.team');
    Route::get('/{siteID}/contacts',                 [SiteController::class, 'contacts'])->middleware('perm:contacts.view')->name('site.contacts');
    Route::get('/{siteID}/alerts',                   [SiteController::class, 'alerts'])->middleware('perm:analytics.view')->name('site.alerts');
    Route::get('/{siteID}/messages',                 [SiteController::class, 'messagesPage'])->middleware('perm:contacts.view')->name('site.messages');
    Route::get('/{siteID}/todos',                    [SiteController::class, 'todosPage'])->name('site.todos');
    Route::get('/{siteID}/store',                    [SiteController::class, 'store'])->middleware(['feature:store', 'perm:store.view'])->name('site.store');
    Route::get('/{siteID}/orders',                   [SiteController::class, 'orders'])->middleware(['feature:store', 'perm:orders.view'])->name('site.orders');
    Route::get('/{siteID}/bookings',                 [SiteController::class, 'bookings'])->middleware(['feature:bookings', 'perm:bookings.view'])->name('site.bookings');
    Route::get('/{siteID}/estimates',                [SiteController::class, 'estimates'])->middleware(['feature:estimator', 'perm:estimates.view'])->name('site.estimates');
    Route::get('/{siteID}/posts',                    [SiteController::class, 'posts'])->middleware('perm:posts.view')->name('site.posts');
    Route::get('/{siteID}/donations',                [SiteController::class, 'donations'])->middleware(['feature:donations', 'perm:donations.view'])->name('site.donations');
    Route::get('/{siteID}/invoices',                 [SiteController::class, 'invoices'])->middleware(['feature:invoices', 'perm:invoices.view'])->name('site.invoices');
    Route::get('/{siteID}/invoices/{invoice}',       [SiteController::class, 'invoiceShow'])->middleware(['feature:invoices', 'perm:invoices.view'])->whereNumber('invoice')->name('site.invoice.show');
    Route::get('/{siteID}/invoices/{invoice}/pdf',   [SiteController::class, 'invoicePdf'])->middleware(['feature:invoices', 'perm:invoices.view'])->whereNumber('invoice')->name('site.invoice.pdf');
    Route::get('/{siteID}/submissions',              [SiteController::class, 'submissions'])->middleware('perm:forms.view')->name('site.submissions');
    Route::get('/{siteID}/forms',                    [SiteController::class, 'forms'])->middleware('perm:forms.view')->name('site.forms');
});
