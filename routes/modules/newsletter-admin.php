<?php

// newsletter module — ADMIN pages — inside the auth group, prefix none: paths start /{siteID}/… ; apply ->middleware(['feature:newsletter', 'perm:newsletter.view']).
// Owned by the newsletter module; keep routes for this module here.

use App\Modules\Newsletter\Http\Controllers\NewsletterAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/{siteID}/newsletter', [NewsletterAdminController::class, 'show'])
    ->middleware(['feature:newsletter', 'perm:newsletter.view'])->name('site.newsletter');
