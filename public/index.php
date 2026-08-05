<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// SPA fallback for template previews: deep links like
// /nuxt-preview/tekstack/about-us have no file on disk — serve the app shell so
// its client router renders the CMS page. Handled before the framework boots
// because PHP's built-in dev server mangles base-path detection for URIs under
// an existing directory (they'd otherwise mis-route to authenticated pages).
$oluxPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if (preg_match('#^/nuxt-preview/([A-Za-z0-9_-]+)/.#', $oluxPath, $oluxM)
    && ! is_file(__DIR__.$oluxPath)
    && is_file($oluxIndex = __DIR__."/nuxt-preview/{$oluxM[1]}/index.html")) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate'); // never pin a stale app shell
    readfile($oluxIndex);
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
