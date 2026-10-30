<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Frontend\HomeController;
use Illuminate\Support\Facades\Route;

/* ══════════════════════════════════════════════════════════════════════════════
 *  PUBLIC STOREFRONT & MARKETING
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::get('/',                      [HomeController::class, 'index'])->name('home');
Route::get('/features',              [HomeController::class, 'features'])->name('features');
Route::get('/industries',            [HomeController::class, 'industries'])->name('industries');
Route::get('/pricing',               [HomeController::class, 'pricing'])->name('pricing');
Route::get('/terms',                 [HomeController::class, 'terms'])->name('terms');
Route::get('/terms-and-conditions',  [HomeController::class, 'terms'])->name('terms.alias');
Route::get('/privacy',               [HomeController::class, 'privacy'])->name('privacy');
Route::get('/privacy-policy',        [HomeController::class, 'privacy'])->name('privacy.alias');
Route::get('/refund-policy',         [HomeController::class, 'refundPolicy'])->name('refund-policy');
Route::get('/security',              [HomeController::class, 'security'])->name('security');

// PWA Service Worker & Web Manifest routes (guarantees HTTP 200 even if static routing is missed)
Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    if (file_exists($path)) {
        return response()->file($path, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
    $fallbackScript = <<<'JS'
const CACHE_NAME = 'skynet-pos-shell-v1';
const STATIC_ASSETS = ['/', '/favicon.ico', '/manifest.json'];
self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(STATIC_ASSETS).catch(() => {}))); self.skipWaiting(); });
self.addEventListener('activate', (e) => { e.waitUntil(caches.keys().then(keys => Promise.all(keys.map(k => k !== CACHE_NAME ? caches.delete(k) : null)))); self.clients.claim(); });
self.addEventListener('fetch', (e) => {
    if (e.request.method !== 'GET') return;
    const url = new URL(e.request.url);
    if (url.pathname.includes('/api/') || url.pathname.startsWith('/catalog')) return;
    e.respondWith(fetch(e.request).then(res => {
        if (res && res.status === 200 && res.type === 'basic') {
            const clone = res.clone();
            caches.open(CACHE_NAME).then(c => c.put(e.request, clone).catch(() => {}));
        }
        return res;
    }).catch(() => caches.match(e.request).then(c => c || (e.request.mode === 'navigate' ? caches.match('/') : null))));
});
JS;
    return response($fallbackScript, 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.sw');

Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    if (file_exists($path)) {
        return response()->file($path, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
    return response()->json([
        'name' => 'Skynet POS',
        'short_name' => 'POS',
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#0f172a',
        'theme_color' => '#059669',
    ]);
})->name('pwa.manifest');

// Global Auth Fallbacks — Redirects /login to current or default branch login
Route::get('/login', function () {
    $branchSlug = current_branch()?->slug
        ?? \App\Models\Branch::orderBy('id')->value('slug')
        ?? 'skynet-digital-enterprise';
    return redirect()->route('pos.login', ['branch' => $branchSlug]);
})->name('login');

Route::post('/logout',     [AuthController::class, 'destroy'])->name('logout');
Route::post('/pos/logout', [AuthController::class, 'destroy'])->name('pos.logout');

// ── Payment Gateway Webhooks (CSRF Exempt) ────────────────────────────────────
Route::post('/webhooks/payments/flutterwave', [\App\Http\Controllers\Payment\PaymentWebhookController::class, 'handleFlutterwave'])->name('webhooks.payments.flutterwave');
Route::post('/webhooks/payments/paystack',    [\App\Http\Controllers\Payment\PaymentWebhookController::class, 'handlePaystack'])->name('webhooks.payments.paystack');

/* ══════════════════════════════════════════════════════════════════════════════
 *  PUBLIC BRANCH CATALOG (SHAREABLE READ-ONLY PRICE LIST) — /catalog/{slug}
 *  Dedicated route: no auth required, rate limited, safe from POS app shell.
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::get('/catalog/{slug}', [\App\Modules\PublicCatalog\Controllers\PublicCatalogController::class, 'show'])
    ->name('catalog.show')
    ->middleware(['throttle:public-catalog']);

/* ══════════════════════════════════════════════════════════════════════════════
 *  SUBSYSTEM MODULAR ROUTE INCLUDES
 * ══════════════════════════════════════════════════════════════════════════════ */
require __DIR__ . '/superadmin.php';
require __DIR__ . '/org.php';
require __DIR__ . '/pos.php';
require __DIR__ . '/laundry.php';
