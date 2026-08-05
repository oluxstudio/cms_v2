<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Custom-domain serving: resolve the Host header to a live Site and
        // serve its renderer — must run globally, before any routing.
        $middleware->prepend(\App\Http\Middleware\ServeLiveSite::class);

        // Per-site feature gate: ->middleware('feature:store')
        $middleware->alias([
            'feature'    => \App\Http\Middleware\EnsureFeatureEnabled::class,
            // Bearer-token auth for third-party API access: ->middleware('auth.token')
            'auth.token' => \App\Http\Middleware\AuthenticateApiToken::class,
            // Role-permission gate for site admin pages: ->middleware('perm:pages.view')
            'perm'       => \App\Http\Middleware\EnsureSitePermission::class,
        ]);

        // Stripe webhooks post without a CSRF token; verified via signature instead.
        $middleware->validateCsrfTokens(except: [
            'preview/*/store/webhook',
            'preview/*/donate/webhook',
            'preview/*/booking/webhook',
            'preview/*/invoice/webhook',
            'stripe/templates/webhook',
            'stripe/subscription/webhook',
        ]);
    })
    ->withProviders([
        \SocialiteProviders\Manager\ServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
