<?php

use App\Http\Controllers\BlockKitController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ConnectPreviewController;
use App\Http\Controllers\DonateController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SiteConnectWebhookController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StoreFrontController;
use App\Http\Controllers\TemplateCommerceController;
use App\Http\Controllers\TemplateRepoWebhookController;
use App\Http\Middleware\ServeLiveSite;
use App\Models\Site;
use App\Payments\SitePaymentOnboarding;
use App\Services\Domains\DomainPurchase;
use App\Services\PlatformBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ── Site Connect connector script — public, CDN-cacheable, served from the CMS
// domain so client sites embed <script src="https://cms.../connect.js">.
Route::get('/connect.js', function () {
    $path = resource_path('site-connect/connect.js');
    abort_unless(File::exists($path), 404);

    return response(File::get($path), 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        // CDN-cacheable in production; always-fresh elsewhere so edits to the
        // connector propagate without a hard refresh during development.
        'Cache-Control' => app()->isProduction() ? 'public, max-age=3600' : 'no-cache, must-revalidate',
    ]);
})->name('site-connect.script');

// ── Auth routes first (must be before the /{siteID} catch-all)
require __DIR__.'/auth.php';

// Marketplace Stripe Connect webhook (signature-verified, CSRF-exempt; public).
Route::post('/stripe/templates/webhook', [TemplateCommerceController::class, 'webhook'])->name('templates.webhook');

// Template source repos: push webhook → re-import (signature-verified, CSRF-exempt).
Route::post('/hooks/template-repo', TemplateRepoWebhookController::class)->name('templates.repo.webhook');

// Site payments: ONE platform Connect webhook for every connected site
// account (signature-verified, CSRF-exempt).
Route::post('/stripe/sites/webhook', SiteConnectWebhookController::class)->name('sites.connect.webhook');

// Platform subscription billing webhook (signature-verified, CSRF-exempt).
Route::post('/stripe/subscription/webhook', function (Request $request) {
    try {
        app(PlatformBilling::class)
            ->handleWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
    } catch (Throwable $e) {
        return response()->json(['message' => 'Invalid signature.'], 400);
    }

    return response()->json(['received' => true]);
})->name('subscription.webhook');

// Caddy on-demand TLS gate: 200 = mint a cert for ?domain=, 403 = refuse.
// Approves the platform's own hosts and LIVE client sites only.
Route::get('/caddy/ask', function (Request $request) {
    $host = strtolower(trim((string) $request->query('domain')));
    if ($host === '') {
        return response('', 403);
    }

    $platform = array_filter(array_merge(
        [parse_url((string) config('app.url'), PHP_URL_HOST)],
        (array) config('publishing.platform_hosts', []),
    ));
    if (in_array($host, array_map('strtolower', $platform), true)) {
        return response('', 200);
    }

    // Live custom domains and instant subdomains both get a certificate.
    return response('', ServeLiveSite::siteForHost($host) ? 200 : 403);
})->name('caddy.ask');

// Self-serve signup wizard. Guests first create + verify an account on the
// login page (register panel); the wizard then picks up at "your business".
// A signed-in user resumes where they left off.
Route::get('/start', function (Request $request) {
    $user = $request->user();
    if (! $user) {
        if ($type = $request->query('type')) {
            session(['signup_type' => $type]);
        }

        return redirect()->route('login', ['mode' => 'register']);
    }

    // Invited members own nothing and never see the signup wizard.
    if (! $user->sites()->exists() && $user->memberships()->exists()) {
        return redirect($user->landingUrl());
    }

    return view('start');
})->name('start');

// Legacy invite links: invites now create the account up front and email the
// credentials, so the old accept page is gone — send people to the login form.
Route::get('/invite/{token}', function () {
    return redirect()->route('login')->with('status', 'Team invites now log in directly — your access details were emailed to you.');
})->name('invite.accept');

// ── SPA fallback for template previews (public) ──────────────────────────────
// Static files under public/nuxt-preview/{key}/ are served directly; any OTHER
// path (deep links like /nuxt-preview/tekstack/about-us on hard reload) must
// serve the SPA shell so its client router can render the CMS page.
Route::get('nuxt-preview/{key}/{any?}', function (string $key) {
    abort_unless(preg_match('/^[A-Za-z0-9_-]+$/', $key), 404);
    $index = public_path("nuxt-preview/{$key}/index.html");
    if (! File::exists($index)) {
        // Deleted or never-built preview: fail clearly, never bounce to auth.
        abort(404, "Preview for template “{$key}” has not been built. Run: php artisan nuxt:preview-build --template={$key}");
    }

    return response()->file($index, [
        'X-Olux-Fallback' => '1',
        'Cache-Control' => 'no-cache, must-revalidate', // never pin a stale app shell
    ]);
})->where('any', '.*')->name('nuxt-preview.fallback');

// ── Public feature pages (MUST be before the public.page catch-all) ──────────
// Store. Static segments declared before the /{slug} param so they win.
Route::post('/preview/{siteName}/store/webhook', [StoreFrontController::class, 'webhook'])->middleware('feature:store')->name('public.store.webhook');
Route::middleware('feature:store')->group(function () {
    Route::get('/preview/{siteName}/store', [StoreFrontController::class, 'index'])->name('public.store');
    Route::get('/preview/{siteName}/store/success', [StoreFrontController::class, 'success'])->name('public.store.success');
    Route::get('/preview/{siteName}/store/cancel', [StoreFrontController::class, 'cancel'])->name('public.store.cancel');
    Route::post('/preview/{siteName}/store/checkout', [StoreFrontController::class, 'checkout'])->name('public.store.checkout');
    Route::get('/preview/{siteName}/store/{slug}', [StoreFrontController::class, 'show'])->name('public.store.show');
});

// Donations.
Route::post('/preview/{siteName}/donate/webhook', [DonateController::class, 'webhook'])->middleware('feature:donations')->name('public.donate.webhook');
Route::middleware('feature:donations')->group(function () {
    Route::get('/preview/{siteName}/donate', [DonateController::class, 'index'])->name('public.donate');
    Route::get('/preview/{siteName}/donate/success', [DonateController::class, 'success'])->name('public.donate.success');
    Route::post('/preview/{siteName}/donate/checkout', [DonateController::class, 'checkout'])->name('public.donate.checkout');
});

// Bookings. Static segments (success) declared before the /{service} param.
Route::middleware('feature:bookings')->group(function () {
    Route::get('/preview/{siteName}/book', [BookingController::class, 'index'])->name('public.book');
    Route::get('/preview/{siteName}/book/success', [BookingController::class, 'success'])->name('public.book.success');
    Route::post('/preview/{siteName}/book', [BookingController::class, 'store'])->name('public.book.store');
    Route::get('/preview/{siteName}/book/{service}', [BookingController::class, 'show'])->name('public.book.show');
    // Stripe payment webhook (signature-verified; CSRF-exempt in bootstrap/app.php).
    Route::post('/preview/{siteName}/booking/webhook', [BookingController::class, 'webhook'])->name('public.book.webhook');
});

// Invoices: tokenized public pay page + Stripe webhook.
Route::post('/preview/{siteName}/invoice/webhook', [PublicInvoiceController::class, 'webhook'])->middleware('feature:invoices')->name('public.invoice.webhook');
Route::middleware('feature:invoices')->group(function () {
    Route::get('/preview/{siteName}/invoice/{token}', [PublicInvoiceController::class, 'show'])->name('public.invoice');
    Route::post('/preview/{siteName}/invoice/{token}/pay', [PublicInvoiceController::class, 'pay'])->name('public.invoice.pay');
    Route::get('/preview/{siteName}/invoice/{token}/success', [PublicInvoiceController::class, 'success'])->name('public.invoice.success');
    Route::get('/preview/{siteName}/invoice/{token}/open.gif', [PublicInvoiceController::class, 'pixel'])->name('public.invoice.pixel');
    Route::get('/preview/{siteName}/invoice/{token}/pdf', [PublicInvoiceController::class, 'pdf'])->name('public.invoice.pdf');
    Route::get('/preview/{siteName}/billing/{token}', [PublicInvoiceController::class, 'portal'])->name('public.invoice.portal');
});

// Store orders: tokened public status page + courier delivery page.
Route::middleware('feature:store')->group(function () {
    Route::get('/preview/{siteName}/order/{token}', [PublicOrderController::class, 'status'])->name('public.order.status');
    Route::get('/preview/{siteName}/deliver/{token}', [PublicOrderController::class, 'courier'])->name('public.order.courier');
    Route::post('/preview/{siteName}/deliver/{token}/status', [PublicOrderController::class, 'courierStatus'])->name('public.order.courier.status');
});

// Live X / Twitter feed.
Route::get('/preview/{siteName}/feed', [FeedController::class, 'index'])->middleware('feature:twitter')->name('public.feed');

// ── Public page rendering (catch-all — keep last in the public group)
Route::get('/preview/{siteName}/{pageUrl}', [PublicPageController::class, 'show'])
    ->where('pageUrl', '.*')
    ->name('public.page');

// ── Root: the public landing page for EVERYONE (signed in or not) ────────────
Route::view('/', 'landing')->name('landing');
// Vertical landing: salons & barbershops (£79 pitch, demo link, register CTA).
Route::view('/salons', 'salon-landing')->name('landing.salons');
Route::redirect('/welcome', '/');

// Public template gallery — browse designs without an account. Lives at
// /designs because /templates is shadowed by the template-assets directory
// in public/. The route NAME stays 'templates' so existing links hold.
Route::get('/designs', fn () => view('template-gallery'))->name('templates');
Route::get('/designs/{key}/buy', fn (string $key) => view('template-buy', ['key' => $key]))
    ->middleware('auth')->name('template.buy');
Route::get('/designs/{key}', fn (string $key) => view('template-detail', ['key' => $key]))->name('template.detail');

// Public "getting started" tutorial — linked from the post-payment email.
Route::view('/tutorial', 'tutorial')->name('tutorial');

// Landing "choose a plan" CTA. Signed-in users go straight to checkout;
// guests are sent to sign up with the chosen plan remembered in the session,
// so after email verification + registration they land on checkout.
Route::get('/get-started/{plan?}', function (Request $request, ?string $plan = null) {
    if ($plan && ! array_key_exists($plan, config('plans.tiers'))) {
        $plan = null;
    }

    if ($plan) {
        $request->session()->put('intended_plan', $plan);
    }

    if ($request->user()) {
        return redirect()->route('account.subscription', $plan ? ['plan' => $plan] : []);
    }

    return redirect()->route('register');
})->name('plan.start');

// ── Auth-protected app routes
Route::middleware('auth')->group(function () {
    // The app home (site picker). Keeps the 'home' route name so every
    // existing route('home') redirect — auth flows, layouts — lands here.
    Route::get('/select-site', [SiteController::class, 'index'])->name('home');

    // In-app "How it works" guide (create a site → content → leads → bookings → live).
    Route::get('/how-it-works', function () {
        $site = auth()->user()->sites()->latest('id')->first();

        return view('how-it-works', ['site' => $site]);
    })->name('how-it-works');

    // Account subscription — the 5-tier plan page (trial → paid upgrades).
    Route::view('/account/subscription', 'subscription')->name('account.subscription');
    // Stripe Checkout return: verify the session server-side, activate, go back.
    // Platform admin (super admins only). The 'super' middleware requires a
    // fresh authenticator-app (TOTP) check; the verify page itself sits
    // outside it, gated inline, so enrollment/challenge is reachable.
    Route::view('/admin/verify', 'admin-verify')->name('admin.verify');
    Route::middleware('super')->group(function () {
        Route::view('/admin', 'platform-dashboard')->name('admin.dashboard');
        Route::view('/admin/accounts', 'platform-accounts')->name('admin.accounts');
        Route::get('/admin/accounts/{user}', function (string $user) {
            return view('platform-account', ['userId' => $user]);
        })->name('admin.account');
    });

    Route::get('/account/subscription/success', function (Request $request) {
        $back = null;
        try {
            $back = app(PlatformBilling::class)
                ->activateFromSession($request->user(), (string) $request->query('session_id'));
        } catch (Throwable $e) {
            report($e);
        }

        return redirect($back ?: route('account.subscription'));
    })->name('account.subscription.success');

    // Domain purchase: Stripe success return (fulfilment is idempotent with the webhook).
    Route::get('/{site}/domain/success', function (Request $request, Site $site) {
        abort_unless($site->allows($request->user(), 'publish.manage'), 403);
        try {
            app(DomainPurchase::class)
                ->fulfilFromSession($request->user(), (string) $request->query('session_id'));
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('site.publish', $site->id);
    })->name('site.domain.success');

    // ── BlockKit: the jigsaw block tree — ONE mutation API, two clients
    //    (Vue editor via these routes; AI assistant via tool dispatch → same service).
    Route::prefix('blockkit/pages/{page}')->name('blockkit.')->group(function () {
        Route::get('/tree', [BlockKitController::class, 'tree'])->name('tree');
        Route::post('/insert', [BlockKitController::class, 'insert'])->name('insert');
        Route::post('/update', [BlockKitController::class, 'update'])->name('update');
        Route::post('/move', [BlockKitController::class, 'move'])->name('move');
        Route::post('/delete', [BlockKitController::class, 'delete'])->name('delete');
        Route::post('/duplicate', [BlockKitController::class, 'duplicate'])->name('duplicate');
    });
    Volt::route('/settings', 'user-settings')->name('settings');
    Route::post('/settings/pw-banner-dismiss', function () {
        session(['pw-banner-dismissed' => true]);

        return response()->noContent();
    })->name('pw-banner.dismiss');
    Route::get('/my-templates', fn () => view('my-templates'))->name('my.templates');
    // Creator payouts (Stripe Connect onboarding) + buyer checkout returns.
    // Site payment onboarding: "Connect your account" — Stripe's hosted form,
    // no API keys. Return leg syncs charges_enabled and switches payments on.
    Route::get('/{siteID}/payments/connect', function (Request $request, string $siteID) {
        $site = Site::where('name', $siteID)->orWhere('id', $siteID)->firstOrFail();
        abort_unless($site->canManageTeam($request->user()), 403);
        $onboarding = app(SitePaymentOnboarding::class);
        if (! $onboarding->available()) {
            return redirect(url("/{$site->name}/marketplace"))->with('mp-message',
                'Stripe Connect isn\'t set up on this platform yet (STRIPE_PLATFORM_SECRET missing) — use "Advanced: your own API keys" for now.');
        }

        try {
            return redirect()->away($onboarding->onboardingLink(
                $site,
                route('site.payments.connect.return', $site->name),
                url("/{$site->name}/payments/connect"),
            ));
        } catch (Throwable $e) {
            report($e);

            return redirect(url("/{$site->name}/payments"))->with('mp-message',
                'Stripe refused to start onboarding: '.$e->getMessage());
        }
    })->name('site.payments.connect');
    Route::get('/{siteID}/payments/connect/return', function (Request $request, string $siteID) {
        $site = Site::where('name', $siteID)->orWhere('id', $siteID)->firstOrFail();
        abort_unless($site->canManageTeam($request->user()), 403);
        try {
            app(SitePaymentOnboarding::class)->syncAccount($site);
        } catch (Throwable $e) {
            report($e);
        }

        return redirect(url("/{$site->name}/payments"))->with('mp-message',
            $site->fresh()->paymentsEnabled()
                ? 'Stripe account connected — this site is now accepting payments.'
                : 'Stripe onboarding saved — finish any remaining steps in Stripe to start accepting payments.');
    })->name('site.payments.connect.return');

    Route::get('/creator/connect', [TemplateCommerceController::class, 'connect'])->name('creator.connect');
    Route::get('/creator/connect/return', [TemplateCommerceController::class, 'connectReturn'])->name('creator.connect.return');
    Route::get('/templates/checkout/success', [TemplateCommerceController::class, 'checkoutSuccess'])->name('templates.checkout.success');
    Route::get('/templates/checkout/cancel', [TemplateCommerceController::class, 'checkoutCancel'])->name('templates.checkout.cancel');

    Route::get('/greeting', function () {
        return view('welcome');
    });

    // Site routes — /{siteID} is a catch-all, keep last
    // Note: NOT named 'dashboard' to avoid conflicting with Laravel's guest-redirect logic
    // Each page is gated by the role permission it maps to (config/permissions.php).
    Route::post('/{siteID}/generate', [SiteController::class, 'generate'])->middleware('perm:builder.manage')->name('site.generate');
    Route::get('/{siteID}/blocks', function ($siteID) {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->allows(Auth::user(), 'builder.manage'), 403);

        return view('block-editor-page', ['site' => $site]);
    })->name('blocks');
    // Site Connect — review ingested pages + the faithful preview replica (iframe).
    Route::get('/{siteID}/connect', function ($siteID) {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->allows(Auth::user(), 'components.view'), 403);

        return view('connect-review-page', ['site' => $site]);
    })->name('site.connect');
    Route::get('/{siteID}/connect/export', [ConnectPreviewController::class, 'export'])
        ->name('site.connect.export');

    Route::get('/{siteID}', [SiteController::class, 'dashboard'])->name('site.dashboard');
    Route::get('/{siteID}/dashboard', [SiteController::class, 'dashboard']);
    Route::get('/{siteID}/pages', [SiteController::class, 'pages'])->middleware('perm:pages.view')->name('pages');
    Route::get('/{siteID}/pages/{page}/details', [SiteController::class, 'pageDetail'])->middleware('perm:pages.view')->name('site.page.detail');
    Route::get('/{siteID}/collections', [SiteController::class, 'collections'])->middleware('perm:collections.view')->name('collections');
    Route::get('/{siteID}/components', function ($siteID) {
        $site = Site::where('name', $siteID)->firstOrFail();
        abort_unless($site->allows(Auth::user(), 'components.view'), 403);

        return view('components-page', ['site' => $site]);
    })->name('site.components');
    Route::get('/{siteID}/media', [SiteController::class, 'media'])->middleware('perm:media.view')->name('media');
    // The Media page is presented as "Assets" — keep both URLs working.
    Route::redirect('/{siteID}/assets', '/{siteID}/media');
    Route::get('/{siteID}/analytics', [SiteController::class, 'analytics'])->middleware('perm:analytics.view')->name('analytics');
    // My Designs: the site's saved templates — switch the active look here.
    Route::get('/{siteID}/designs', [SiteController::class, 'designsPage'])->middleware('perm:builder.manage')->name('site.designs');
    Route::redirect('/{siteID}/templates', '/{siteID}/designs')->name('site.templates');
    Route::get('/{siteID}/marketplace', [SiteController::class, 'marketplace'])->middleware('perm:addons.manage')->name('site.marketplace');
    Route::get('/{siteID}/publish', [SiteController::class, 'publish'])->middleware('perm:publish.manage')->name('site.publish');
    Route::get('/{siteID}/api-docs', [SiteController::class, 'apiDocs'])->name('site.apidocs');
    Route::get('/{siteID}/api-keys', [SiteController::class, 'apiKeys'])->name('site.apikeys');
    Route::get('/{siteID}/emails', [SiteController::class, 'emails'])->middleware('perm:forms.view')->name('site.emails');
    Route::get('/{siteID}/team', [SiteController::class, 'team'])->middleware('perm:team.manage')->name('site.team');
    Route::get('/{siteID}/contacts', [SiteController::class, 'contacts'])->middleware('perm:contacts.view')->name('site.contacts');
    Route::get('/{siteID}/alerts', [SiteController::class, 'alerts'])->middleware('perm:analytics.view')->name('site.alerts');
    Route::get('/{siteID}/messages', [SiteController::class, 'messagesPage'])->middleware('perm:messages.view')->name('site.messages');
    Route::get('/{siteID}/payments', [SiteController::class, 'paymentsPage'])->name('site.payments');
    Route::get('/{siteID}/tasks', [SiteController::class, 'tasksPage'])->name('site.tasks');
    Route::get('/{siteID}/todos', fn ($siteID) => redirect("/{$siteID}/tasks"))->name('site.todos'); // legacy name
    Route::get('/{siteID}/store', [SiteController::class, 'store'])->middleware(['feature:store', 'perm:store.view'])->name('site.store');
    Route::get('/{siteID}/orders', [SiteController::class, 'orders'])->middleware(['feature:store', 'perm:orders.view'])->name('site.orders');
    Route::get('/{siteID}/bookings', [SiteController::class, 'bookings'])->middleware(['feature:bookings', 'perm:bookings.view'])->name('site.bookings');
    Route::get('/{siteID}/estimates', [SiteController::class, 'estimates'])->middleware(['feature:estimator', 'perm:estimates.view'])->name('site.estimates');
    Route::get('/{siteID}/posts', [SiteController::class, 'posts'])->middleware('perm:posts.view')->name('site.posts');
    Route::get('/{siteID}/donations', [SiteController::class, 'donations'])->middleware(['feature:donations', 'perm:donations.view'])->name('site.donations');
    Route::get('/{siteID}/invoices', [SiteController::class, 'invoices'])->middleware(['feature:invoices', 'perm:invoices.view'])->name('site.invoices');
    Route::get('/{siteID}/invoices/{invoice}', [SiteController::class, 'invoiceShow'])->middleware(['feature:invoices', 'perm:invoices.view'])->name('site.invoice.show');
    Route::get('/{siteID}/store/{product}', [SiteController::class, 'productShow'])->middleware(['feature:store', 'perm:store.view'])->name('site.store.product');
    Route::get('/{siteID}/invoices/{invoice}/pdf', [SiteController::class, 'invoicePdf'])->middleware(['feature:invoices', 'perm:invoices.view'])->name('site.invoice.pdf');
    Route::get('/{siteID}/submissions', [SiteController::class, 'submissions'])->middleware('perm:forms.view')->name('site.submissions');
    Route::get('/{siteID}/forms', [SiteController::class, 'forms'])->middleware('perm:forms.view')->name('site.forms');
    // Deep link from the admin submission-alert email — opens a specific response.
    Route::get('/{siteID}/forms/response/{responseId}', [SiteController::class, 'forms'])->middleware('perm:forms.view')->name('site.forms.response');
});
