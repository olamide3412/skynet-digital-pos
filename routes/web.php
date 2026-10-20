<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\SaleController;
use App\Http\Controllers\Pos\SaleReturnController;
use App\Http\Controllers\Pos\HeldSaleController;
use App\Http\Controllers\Pos\ItemController as PosItemController;
use App\Http\Controllers\Pos\CustomerController as PosCustomerController;
use App\Http\Controllers\Pos\SettingsController as PosSettingsController;
use App\Http\Controllers\Pos\ItemGridController;
use App\Http\Controllers\Pos\BarcodeController;
use App\Http\Controllers\Pos\SaleDiscountController;
use App\Http\Controllers\Pos\ItemDiscountController;
use App\Http\Controllers\Pos\DiscountGroupController;
use App\Http\Controllers\Pos\StockTransferController;
use App\Http\Controllers\Pos\TillSessionController;
use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\SuperAdmin\BranchController;
use App\Http\Controllers\SuperAdmin\BranchUserController;
use App\Http\Controllers\SuperAdmin\GlobalItemController;
use App\Http\Controllers\SuperAdmin\ProfileController as SuperAdminProfileController;

// Storefront Controllers
use App\Http\Controllers\Frontend\HomeController;

use Illuminate\Support\Facades\Route;

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
 *  SUPER ADMIN PANEL — /superadmin/...
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/login',  [SuperAdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SuperAdminAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout',[SuperAdminAuthController::class, 'destroy'])->name('logout')
        ->middleware('auth:web');

    Route::middleware(['auth:web', 'superadmin'])->group(function () {
        Route::get('/dashboard', [SuperAdminDashboard::class, 'index'])->name('dashboard');

        // SuperAdmin Profile Management
        Route::get('/profile',          [SuperAdminProfileController::class, 'index'])->name('profile.index');
        Route::put('/profile',          [SuperAdminProfileController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [SuperAdminProfileController::class, 'updatePassword'])->name('profile.password');

        Route::resource('branches', BranchController::class)
            ->names([
                'index'   => 'branches.index',
                'create'  => 'branches.create',
                'store'   => 'branches.store',
                'show'    => 'branches.show',
                'edit'    => 'branches.edit',
                'update'  => 'branches.update',
                'destroy' => 'branches.destroy',
            ]);

        Route::post('/branches/{branch}/toggle', [BranchController::class, 'toggle'])
            ->name('branches.toggle');
        Route::post('/branches/{branch}/toggle-offline', [BranchController::class, 'toggleOffline'])
            ->name('branches.toggle-offline');
        Route::post('/branches/{branch}/toggle-offline-qty-deduction', [BranchController::class, 'toggleOfflineQtyDeduction'])
            ->name('branches.toggle-offline-qty-deduction');
        Route::post('/branches/{branch}/toggle-turnstile', [BranchController::class, 'toggleTurnstile'])
            ->name('branches.toggle-turnstile');
        Route::post('/branches/{branch}/toggle-ai', [BranchController::class, 'toggleAi'])
            ->name('branches.toggle-ai');
        Route::post('/branches/{branch}/toggle-public-catalog', [BranchController::class, 'togglePublicCatalog'])
            ->name('branches.toggle-public-catalog');
        Route::post('/branches/{branch}/toggle-auto-fullscreen', [BranchController::class, 'toggleAutoFullscreen'])
            ->name('branches.toggle-auto-fullscreen');
        Route::post('/branches/{branch}/toggle-till-opening', [BranchController::class, 'toggleTillOpening'])
            ->name('branches.toggle-till-opening');
        Route::put('/branches/{branch}/settings', [BranchController::class, 'updateSettings'])
            ->name('branches.settings.update');
        Route::post('/branches/{branch}/logo', [BranchController::class, 'updateLogo'])
            ->name('branches.logo.update');
        Route::get('/branches/{branch}/export-items', [BranchController::class, 'exportItems'])
            ->name('branches.export-items');
        Route::get('/branches/{branch}/export-sales', [BranchController::class, 'exportSales'])
            ->name('branches.export-sales');
        Route::get('/branches/{branch}/export-logs',  [BranchController::class, 'exportLogs'])
            ->name('branches.export-logs');
        Route::post('/branches/{branch}/move-organization', [BranchController::class, 'moveOrganization'])
            ->name('branches.move-organization');

        // Organizations Management (Platform Super Admin)
        Route::prefix('organizations')->name('organizations.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'index'])->name('index');
            Route::post('/',               [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'store'])->name('store');
            Route::get('/{organization}',  [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'show'])->name('show');
            Route::put('/{organization}',  [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'update'])->name('update');
            Route::delete('/{organization}', [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'destroy'])->name('destroy');

            // Organization Admin Users
            Route::post('/{organization}/users',                [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'storeAdminUser'])->name('users.store');
            Route::post('/{organization}/users/{user}/toggle',   [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'toggleAdminUser'])->name('users.toggle');
            Route::post('/{organization}/users/{user}/password', [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'resetAdminPassword'])->name('users.password');

            // Organization Subscription Management
            Route::post('/{organization}/subscription',          [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'updateSubscription'])->name('subscription.update');

            // Organization Branch Creation Toggle
            Route::post('/{organization}/toggle-branch-creation', [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'toggleBranchCreation'])->name('toggle-branch-creation');

            // Organization Staff Management Toggle (Phase 6 Delegation Toggle)
            Route::post('/{organization}/toggle-staff-management', [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'toggleStaffManagement'])->name('toggle-staff-management');

            // Organization AI Credit Top-up Management
            Route::post('/{organization}/ai-credits/topup', [\App\Http\Controllers\SuperAdmin\OrganizationController::class, 'addAiCredits'])->name('ai-credits.topup');

            // Organization Product Links (Inter-Branch Linking & Catalog Management)
            Route::get('/{organization}/product-links',                    [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'index'])->name('product-links.index');
            Route::post('/{organization}/product-links/link',              [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'link'])->name('product-links.link');
            Route::post('/{organization}/product-links/unlink',            [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'unlink'])->name('product-links.unlink');
            Route::post('/{organization}/product-links/groups',            [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'createGroup'])->name('product-links.create-group');
            Route::post('/{organization}/product-links/groups/update',     [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'updateGroup'])->name('product-links.update-group');
            Route::post('/{organization}/product-links/merge',             [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'merge'])->name('product-links.merge');
            Route::get('/{organization}/product-links/suggest',            [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'autoSuggest'])->name('product-links.suggest');
            Route::post('/{organization}/product-links/bulk-confirm',      [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'bulkConfirm'])->name('product-links.bulk-confirm');
            Route::post('/{organization}/product-links/undo-batch',        [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'undoBatch'])->name('product-links.undo-batch');
            Route::post('/{organization}/product-links/copy',              [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'copyToBranches'])->name('product-links.copy');
            Route::get('/{organization}/product-links/unlinked-items/{branch}', [\App\Http\Controllers\SuperAdmin\ProductLinkController::class, 'unlinkedItems'])->name('product-links.unlinked-items');
        });

        Route::prefix('branches/{branch}/users')->name('branches.users.')->group(function () {
            Route::get('/',                [BranchUserController::class, 'index'])->name('index');
            Route::post('/',               [BranchUserController::class, 'store'])->name('store');
            Route::put('/{user}',          [BranchUserController::class, 'update'])->name('update');
            Route::delete('/{user}',       [BranchUserController::class, 'destroy'])->name('destroy');
            Route::post('/{user}/toggle',  [BranchUserController::class, 'toggle'])->name('toggle');
            Route::post('/{user}/password',[BranchUserController::class, 'resetPassword'])->name('reset-password');
        });

        // Branch role & permission management
        Route::prefix('branches/{branch}/roles')->name('branches.roles.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\SuperAdmin\BranchRoleController::class, 'index'])->name('index');
            Route::put('/{role}',         [\App\Http\Controllers\SuperAdmin\BranchRoleController::class, 'update'])->name('update');
        });

        // Branch Catalog & Global Item Import Management
        Route::prefix('branches/{branch}/items')->name('branches.items.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\SuperAdmin\BranchItemController::class, 'index'])->name('index');
            Route::post('/import-batch', [\App\Http\Controllers\SuperAdmin\BranchItemController::class, 'importBatch'])->name('import-batch');
            Route::post('/import-all',   [\App\Http\Controllers\SuperAdmin\BranchItemController::class, 'importAll'])->name('import-all');
            Route::delete('/{item}',     [\App\Http\Controllers\SuperAdmin\BranchItemController::class, 'destroy'])->name('destroy');
        });

        // Branch Ecommerce Integration Management
        Route::prefix('branches/{branch}/ecommerce')->name('branches.ecommerce.')->group(function () {
            Route::get('/',                      [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'show'])->name('show');
            Route::post('/',                     [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'store'])->name('store');
            Route::put('/settings',              [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'updateSettings'])->name('settings.update');
            Route::post('/regenerate-token',     [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'regenerateToken'])->name('regenerate-token');
            Route::post('/toggle-status',        [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/test-webhook',         [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'testWebhook'])->name('test-webhook');
            Route::delete('/',                   [\App\Http\Controllers\SuperAdmin\CommerceIntegrationController::class, 'destroy'])->name('destroy');
        });

        // System Settings & Dynamic Theme Configuration
        Route::get('/settings',  [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'update'])->name('settings.update');

        // System Activity & Error Logs
        Route::get('/logs/export', [\App\Http\Controllers\SuperAdmin\ActivityLogController::class, 'export'])->name('logs.export');
        Route::delete('/logs/prune', [\App\Http\Controllers\SuperAdmin\ActivityLogController::class, 'prune'])->name('logs.prune');
        Route::get('/logs',        [\App\Http\Controllers\SuperAdmin\ActivityLogController::class, 'index'])->name('logs.index');

        // Cross-branch Expense Reporting
        Route::get('/reports/expenses',        [\App\Http\Controllers\SuperAdmin\CrossBranchExpenseController::class, 'index'])->name('reports.expenses');
        Route::get('/reports/expenses/export', [\App\Http\Controllers\SuperAdmin\CrossBranchExpenseController::class, 'export'])->name('reports.expenses.export');

        Route::resource('global-items', GlobalItemController::class)
            ->except(['show'])
            ->names([
                'index'   => 'global-items.index',
                'create'  => 'global-items.create',
                'store'   => 'global-items.store',
                'edit'    => 'global-items.edit',
                'update'  => 'global-items.update',
                'destroy' => 'global-items.destroy',
            ]);

        Route::post('/global-items/{globalItem}/import/{branch}',
            [GlobalItemController::class, 'import'])
            ->name('global-items.import');

        // Item Import Library (CSV / Excel Files & Manageable Categories)
        Route::prefix('import-library')->name('import-library.')->group(function () {
            Route::get('/',                      [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'index'])->name('index');
            Route::post('/',                     [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'store'])->name('store');
            Route::post('/{libraryFile}/replace', [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'replace'])->name('replace');
            Route::post('/{libraryFile}/toggle-status', [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'toggleStatus'])->name('toggle-status');
            Route::delete('/{libraryFile}',      [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'destroy'])->name('destroy');
            Route::get('/{libraryFile}/download', [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'download'])->name('download');
            Route::get('/template/{format}',     [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'downloadTemplate'])->name('template');
            Route::post('/preview',              [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'preview'])->name('preview');
            Route::post('/import-to-branch',     [\App\Http\Controllers\SuperAdmin\ImportLibraryController::class, 'importToBranch'])->name('import-to-branch');
        });

        // Manageable Item Type Tags
        Route::prefix('import-item-types')->name('import-item-types.')->group(function () {
            Route::post('/',             [\App\Http\Controllers\SuperAdmin\ImportItemTypeController::class, 'store'])->name('store');
            Route::put('/{itemType}',    [\App\Http\Controllers\SuperAdmin\ImportItemTypeController::class, 'update'])->name('update');
            Route::delete('/{itemType}', [\App\Http\Controllers\SuperAdmin\ImportItemTypeController::class, 'destroy'])->name('destroy');
        });

        // Super Admin Broadcast Notifications
        Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
            Route::get('/',            [\App\Http\Controllers\SuperAdmin\BroadcastController::class, 'index'])->name('index');
            Route::post('/',           [\App\Http\Controllers\SuperAdmin\BroadcastController::class, 'store'])->name('store');
            Route::get('/{broadcast}', [\App\Http\Controllers\SuperAdmin\BroadcastController::class, 'show'])->name('show');
        });

        // Mail Server Configuration
        Route::post('settings/mail',      [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test', [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'testMail'])->name('settings.mail.test');

        // Pricing Plans & Marketing Homepage Content Management
        Route::prefix('pricing-plans')->name('pricing-plans.')->group(function () {
            Route::get('/',                           [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'index'])->name('index');
            Route::post('/plans',                     [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'storePlan'])->name('plans.store');
            Route::put('/plans/{plan}',               [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'updatePlan'])->name('plans.update');
            Route::delete('/plans/{plan}',            [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'destroyPlan'])->name('plans.destroy');
            Route::post('/plans/{plan}/toggle',       [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'togglePlan'])->name('plans.toggle');
            Route::post('/plans/{plan}/highlight',    [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'toggleHighlight'])->name('plans.highlight');
            Route::post('/plans/reorder',             [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'reorderPlans'])->name('plans.reorder');
            Route::put('/settings',                   [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'updateSettings'])->name('settings.update');
            Route::post('/toggle-section',            [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'togglePricingSection'])->name('toggle-section');
            Route::put('/included-items',             [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'updateIncludedItems'])->name('included-items.update');
            Route::put('/value-props',                [\App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'updateValueProps'])->name('value-props.update');
        });

        // Plan Capabilities & System Limits (Operational Engine)
        Route::prefix('plan-capabilities')->name('plan-capabilities.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\SuperAdmin\PlanCapabilityController::class, 'index'])->name('index');
            Route::put('/',              [\App\Http\Controllers\SuperAdmin\PlanCapabilityController::class, 'update'])->name('update.base');
            Route::put('/{plan}',        [\App\Http\Controllers\SuperAdmin\PlanCapabilityController::class, 'update'])->name('update');
        });

        // Payment Gateway Configuration
        Route::post('settings/payment',      [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'updatePayment'])->name('settings.payment');
        Route::post('settings/payment/test', [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'testPaymentGateway'])->name('settings.payment.test');

        // AI Credit Packs Catalog Management
        Route::resource('credit-packs', \App\Http\Controllers\SuperAdmin\AiCreditPackController::class)
            ->except(['create', 'show', 'edit'])
            ->names([
                'index'   => 'credit-packs.index',
                'store'   => 'credit-packs.store',
                'update'  => 'credit-packs.update',
                'destroy' => 'credit-packs.destroy',
            ]);
        Route::post('credit-packs/{creditPack}/toggle', [\App\Http\Controllers\SuperAdmin\AiCreditPackController::class, 'toggle'])
            ->name('credit-packs.toggle');
    });
});

/* ══════════════════════════════════════════════════════════════════════════════
 *  ORGANIZATION ADMIN PORTAL — /org/...
 *  Prefix 'org' is in config('organizations.reserved_slugs')
 *  Access gated by ORGANIZATIONS_ENABLED and 'org.admin' middleware
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::prefix('org')->name('org.')->group(function () {
    Route::get('/', function () {
        if (!config('organizations.enabled')) {
            abort(404, 'Organization portal is disabled.');
        }

        if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
            if ($user->isOrganizationAdmin() || $user->isSuperAdmin()) {
                return redirect()->route('org.dashboard');
            }
        }

        return redirect()->route('org.login');
    })->name('index');

    Route::get('/login',   [\App\Http\Controllers\Org\OrgAuthController::class, 'showLogin'])->name('login');
    Route::post('/login',  [\App\Http\Controllers\Org\OrgAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [\App\Http\Controllers\Org\OrgAuthController::class, 'destroy'])->name('logout')
        ->middleware('auth:web');

    Route::middleware(['auth:web', 'org.admin'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Org\OrgDashboardController::class, 'index'])->name('dashboard');
        Route::get('/branches',  [\App\Http\Controllers\Org\OrgDashboardController::class, 'branches'])->name('branches');
        Route::post('/branches', [\App\Http\Controllers\Org\OrgDashboardController::class, 'storeBranch'])->name('branches.store');

        // Organization Staff & User Management (Multi-Branch Staff Transfers & Status)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',                     [\App\Http\Controllers\Org\OrgUserController::class, 'index'])->name('index');
            Route::get('/{user}',               [\App\Http\Controllers\Org\OrgUserController::class, 'show'])->name('show');
            Route::get('/{user}/precheck-move', [\App\Http\Controllers\Org\OrgUserController::class, 'precheckMove'])->name('precheck-move');
            Route::post('/{user}/move',         [\App\Http\Controllers\Org\OrgUserController::class, 'move'])->name('move');
            Route::post('/{user}/disable',      [\App\Http\Controllers\Org\OrgUserController::class, 'disable'])->name('disable');
            Route::post('/{user}/enable',       [\App\Http\Controllers\Org\OrgUserController::class, 'enable'])->name('enable');
        });

        // Organization Product Links (Inter-Branch Product Linking & Catalog Management)
        Route::prefix('product-links')->name('product-links.')->group(function () {
            Route::get('/',                   [\App\Http\Controllers\Org\OrgProductLinkController::class, 'index'])->name('index');
            Route::post('/link',              [\App\Http\Controllers\Org\OrgProductLinkController::class, 'link'])->name('link');
            Route::post('/unlink',            [\App\Http\Controllers\Org\OrgProductLinkController::class, 'unlink'])->name('unlink');
            Route::post('/groups',            [\App\Http\Controllers\Org\OrgProductLinkController::class, 'createGroup'])->name('create-group');
            Route::post('/groups/update',     [\App\Http\Controllers\Org\OrgProductLinkController::class, 'updateGroup'])->name('update-group');
            Route::post('/merge',             [\App\Http\Controllers\Org\OrgProductLinkController::class, 'merge'])->name('merge');
            Route::get('/suggest',            [\App\Http\Controllers\Org\OrgProductLinkController::class, 'autoSuggest'])->name('suggest');
            Route::post('/bulk-confirm',      [\App\Http\Controllers\Org\OrgProductLinkController::class, 'bulkConfirm'])->name('bulk-confirm');
            Route::post('/undo-batch',        [\App\Http\Controllers\Org\OrgProductLinkController::class, 'undoBatch'])->name('undo-batch');
            Route::post('/copy',              [\App\Http\Controllers\Org\OrgProductLinkController::class, 'copyToBranches'])->name('copy');
            Route::get('/unlinked-items/{branch}', [\App\Http\Controllers\Org\OrgProductLinkController::class, 'unlinkedItems'])->name('unlinked-items');
        });

        Route::get('/reports',             [\App\Http\Controllers\Org\OrgReportController::class, 'index'])->name('reports');
        Route::get('/reports/end-of-day',   [\App\Http\Controllers\Org\OrgReportController::class, 'endOfDay'])->name('reports.end-of-day');
        Route::get('/reports/export',       [\App\Http\Controllers\Org\OrgReportController::class, 'exportSales'])->name('reports.export');
        Route::get('/ai/chat',   [\App\Http\Controllers\Org\OrgAiController::class, 'chat'])->name('ai.chat');
        Route::post('/ai/chat/ask', [\App\Http\Controllers\Org\OrgAiController::class, 'ask'])->name('ai.chat.ask');
        Route::delete('/ai/chat/clear', [\App\Http\Controllers\Org\OrgAiController::class, 'clear'])->name('ai.chat.clear');

        // Organization Activity & Audit Logs
        Route::get('/activity-logs',        [\App\Http\Controllers\Org\OrgActivityLogController::class, 'index'])->name('activity-logs');
        Route::get('/activity-logs/export', [\App\Http\Controllers\Org\OrgActivityLogController::class, 'export'])->name('activity-logs.export');

        // Organization Ecommerce Activity (Multi-Branch Aggregated Visibility & Corrective Actions)
        Route::prefix('ecommerce-activity')->name('ecommerce-activity.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Org\OrgEcommerceActivityController::class, 'index'])->name('index');
            Route::get('/export', [\App\Http\Controllers\Org\OrgEcommerceActivityController::class, 'export'])->name('export');
            Route::post('/reservations/{reservation}/release', [\App\Http\Controllers\Org\OrgEcommerceActivityController::class, 'releaseReservation'])->name('reservations.release');
            Route::post('/mappings/{mapping}/deactivate', [\App\Http\Controllers\Org\OrgEcommerceActivityController::class, 'deactivateMapping'])->name('mappings.deactivate');
        });

        // Organization AI Credits & Payment
        Route::prefix('credits')->name('credits.')->group(function () {
            Route::get('/',          [\App\Http\Controllers\Org\OrgCreditController::class, 'index'])->name('index');
            Route::post('/purchase', [\App\Http\Controllers\Org\OrgCreditController::class, 'purchase'])->name('purchase');
            Route::get('/callback',  [\App\Http\Controllers\Org\OrgCreditController::class, 'callback'])->name('callback');
        });

        // Organization Subscription & Plans (Renewals & Upgrades)
        Route::prefix('subscription')->name('subscription.')->group(function () {
            Route::get('/',          [\App\Http\Controllers\Org\OrgSubscriptionController::class, 'index'])->name('index');
            Route::post('/checkout', [\App\Http\Controllers\Org\OrgSubscriptionController::class, 'checkout'])->name('checkout');
            Route::get('/callback',  [\App\Http\Controllers\Org\OrgSubscriptionController::class, 'callback'])->name('callback');
        });
    });
});

/* ══════════════════════════════════════════════════════════════════════════════
 *  PUBLIC BRANCH CATALOG (SHAREABLE READ-ONLY PRICE LIST) — /catalog/{slug}
 *  Dedicated route: no auth required, rate limited, safe from POS app shell.
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::get('/catalog/{slug}', [\App\Modules\PublicCatalog\Controllers\PublicCatalogController::class, 'show'])
    ->name('catalog.show')
    ->middleware(['throttle:public-catalog']);

/* ══════════════════════════════════════════════════════════════════════════════
 *  BRANCH POS PANEL — /{branch}/...
 *  branch = slug (route model binding via getRouteKeyName = 'slug')
 *  ResolveBranchMiddleware runs first: resolves Branch, checks is_active.
 *  BranchScopeMiddleware: validates user belongs to the branch.
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::prefix('{branch}')
    ->name('pos.')
    ->middleware(['resolve.branch'])
    ->group(function () {

    // Branch Login (no auth required)
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    // All authenticated branch routes
    Route::middleware(['auth:web', 'branch.scope'])->group(function () {

        // Main POS Screen
        Route::get('/', [PosController::class, 'index'])->name('index')
            ->middleware('pos.role:canAccessPos');

        // Sales
        Route::get('/sales/search-receipt', [SaleController::class, 'searchReceipt'])->name('sales.search-receipt')
            ->middleware('pos.role:canViewSales');
        Route::get('/sales/export',    [SaleController::class, 'export'])->name('sales.export')
            ->middleware('pos.role:canViewSales');
        Route::get('/sales',           [SaleController::class, 'index'])->name('sales.index')
            ->middleware('pos.role:canViewSales');
        Route::post('/sales',          [SaleController::class, 'store'])->name('sales.store')
            ->middleware('pos.role:canAccessPos');
        Route::get('/sales/{sale}',    [SaleController::class, 'show'])->name('sales.show')
            ->middleware('pos.role:canViewSales');
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy')
            ->middleware('pos.role:canDeleteSale');

        // Returns
        Route::post('/sale-returns', [SaleReturnController::class, 'store'])
            ->name('sale-returns.store')
            ->middleware('pos.role:canProcessReturn');

        // Discounts
        Route::resource('discounts', SaleDiscountController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names([
                'index'   => 'discounts.index',
                'store'   => 'discounts.store',
                'update'  => 'discounts.update',
                'destroy' => 'discounts.destroy',
            ])
            ->middleware('pos.role:canApplyDiscount');

        // Item Discounts
        Route::post('/item-discounts', [ItemDiscountController::class, 'store'])
            ->name('item-discounts.store')
            ->middleware('pos.role:canApplyDiscount');
        Route::put('/item-discounts/{itemDiscount}', [ItemDiscountController::class, 'update'])
            ->name('item-discounts.update')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);
        Route::patch('/item-discounts/{itemDiscount}/toggle', [ItemDiscountController::class, 'toggleActive'])
            ->name('item-discounts.toggle')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);
        Route::delete('/item-discounts/{itemDiscount}', [ItemDiscountController::class, 'destroy'])
            ->name('item-discounts.destroy')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);

        // Discount Groups
        Route::post('/discount-groups', [DiscountGroupController::class, 'store'])
            ->name('discount-groups.store')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);
        Route::put('/discount-groups/{discountGroup}', [DiscountGroupController::class, 'update'])
            ->name('discount-groups.update')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);
        Route::patch('/discount-groups/{discountGroup}/toggle', [DiscountGroupController::class, 'toggleActive'])
            ->name('discount-groups.toggle')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);
        Route::delete('/discount-groups/{discountGroup}', [DiscountGroupController::class, 'destroy'])
            ->name('discount-groups.destroy')
            ->middleware(['pos.role:canApplyDiscount', 'plan.feature:discounts']);


        // Vendors & Purchases
        Route::resource('vendors', \App\Http\Controllers\Pos\VendorController::class)
            ->except(['create', 'show', 'edit'])
            ->middleware('pos.role:canManagePurchases');

        Route::resource('purchases', \App\Http\Controllers\Pos\PurchaseOrderController::class)
            ->except(['edit', 'update'])
            ->middleware('pos.role:canManagePurchases');
        Route::get('/purchases/{purchase}/receive',
                [\App\Http\Controllers\Pos\PurchaseOrderController::class, 'receiveForm'])
            ->name('purchases.receive')
            ->middleware('pos.role:canManagePurchases');
        Route::post('/purchases/{purchase}/receive',
                [\App\Http\Controllers\Pos\PurchaseOrderController::class, 'processReceive'])
            ->name('purchases.process-receive')
            ->middleware('pos.role:canManagePurchases');

        // Inventory
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/',
                [\App\Http\Controllers\Pos\InventoryController::class, 'index'])
                ->name('index')
                ->middleware('pos.role:canAdjustStock');
            Route::get('/export',
                [\App\Http\Controllers\Pos\InventoryController::class, 'export'])
                ->name('export')
                ->middleware('pos.role:canAdjustStock');
            Route::get('/adjust',
                [\App\Http\Controllers\Pos\InventoryController::class, 'adjustForm'])
                ->name('adjust')
                ->middleware('pos.role:canAdjustStock');
            Route::post('/adjust',
                [\App\Http\Controllers\Pos\InventoryController::class, 'processAdjustment'])
                ->name('process-adjust')
                ->middleware('pos.role:canAdjustStock');

            // Stock Transfers (back-store ↔ front-store)
            Route::get('/transfers',    [StockTransferController::class, 'index'])
                ->name('transfers.index')
                ->middleware('pos.role:canTransferStock');
            Route::get('/transfer',     [StockTransferController::class, 'index'])
                ->name('transfer')
                ->middleware('pos.role:canTransferStock');
            Route::post('/transfers',   [StockTransferController::class, 'store'])
                ->name('transfers.store')
                ->middleware('pos.role:canTransferStock');

            // Inter-Branch Transfers & Receiving Queue (any-to-any across branches)
            Route::prefix('transfers/inter-branch')->name('transfers.inter-branch.')->group(function () {
                Route::get('/',                   [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'index'])->name('index')->middleware('pos.role:canTransferInterBranchStock');
                Route::get('/create',             [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'create'])->name('create')->middleware('pos.role:canTransferInterBranchStock');
                Route::post('/',                  [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'store'])->name('store')->middleware('pos.role:canTransferInterBranchStock');
                Route::get('/{transfer}/receive', [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'showReceive'])->name('receive')->middleware('pos.role:canTransferInterBranchStock');
                Route::get('/{transfer}',         [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'show'])->name('show')->middleware('pos.role:canTransferInterBranchStock');
                Route::post('/{transfer}/cancel', [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'cancel'])->name('cancel')->middleware('pos.role:canTransferInterBranchStock');
                Route::post('/{transfer}/accept', [\App\Http\Controllers\Pos\InterBranchTransferController::class, 'accept'])->name('accept')->middleware('pos.role:canTransferInterBranchStock');
            });

            // Reorder Points & Replenishment
            Route::get('/reorder-points', [\App\Http\Controllers\Pos\ReorderPointController::class, 'index'])
                ->name('reorder-points')
                ->middleware('pos.role:canViewReorderPoints');
            Route::get('/reorder-points/export', [\App\Http\Controllers\Pos\ReorderPointController::class, 'export'])
                ->name('reorder-points.export')
                ->middleware('pos.role:canViewReorderPoints');
            Route::post('/reorder-points/{item}', [\App\Http\Controllers\Pos\ReorderPointController::class, 'update'])
                ->name('reorder-points.update')
                ->middleware('pos.role:canViewReorderPoints');

            // Stock Conflicts & Offline Reconciliation
            Route::get('/conflicts', [\App\Http\Controllers\Pos\StockConflictController::class, 'index'])
                ->name('conflicts.index')
                ->middleware('pos.role:canAdjustStock');
            Route::get('/conflicts/export', [\App\Http\Controllers\Pos\StockConflictController::class, 'export'])
                ->name('conflicts.export')
                ->middleware('pos.role:canAdjustStock');
            Route::post('/conflicts/{conflict}/resolve', [\App\Http\Controllers\Pos\StockConflictController::class, 'resolve'])
                ->name('conflicts.resolve')
                ->middleware('pos.role:canAdjustStock');
        });

        // Notifications Center
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\Pos\NotificationController::class, 'index'])->name('index');
            Route::get('/preview',                [\App\Http\Controllers\Pos\NotificationController::class, 'preview'])->name('preview');
            Route::post('/mark-all-read',          [\App\Http\Controllers\Pos\NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::post('/{notification}/read',    [\App\Http\Controllers\Pos\NotificationController::class, 'markAsRead'])->name('read');
            Route::post('/{notification}/resolve', [\App\Http\Controllers\Pos\NotificationController::class, 'resolve'])->name('resolve');
            Route::delete('/{notification}',       [\App\Http\Controllers\Pos\NotificationController::class, 'destroy'])->name('destroy');
        });

        // Reports
        Route::get('/reports/end-of-day',
                [\App\Http\Controllers\Pos\ReportController::class, 'endOfDay'])
            ->name('reports.end-of-day')
            ->middleware('pos.role:canViewEndOfDay');

        Route::prefix('reports')->name('reports.')
            ->middleware('pos.role:canViewReports')
            ->group(function () {
                Route::get('/',                     [\App\Http\Controllers\Pos\ReportController::class, 'index'])->name('index');
                Route::get('/daily-sales',          [\App\Http\Controllers\Pos\ReportController::class, 'dailySales'])->name('daily-sales');
                Route::get('/daily-sales/export',   [\App\Http\Controllers\Pos\ReportController::class, 'dailySalesExport'])->name('daily-sales.export');
                Route::get('/returns',              [\App\Http\Controllers\Pos\ReportController::class, 'returns'])->name('returns');
                Route::get('/returns/export',       [\App\Http\Controllers\Pos\ReportController::class, 'returnsExport'])->name('returns.export');
                Route::get('/profit-loss',          [\App\Http\Controllers\Pos\ReportController::class, 'profitLoss'])->name('profit-loss')
                    ->middleware('pos.role:canViewProfitLoss');
                Route::get('/low-stock',            [\App\Http\Controllers\Pos\ReportController::class, 'lowStock'])->name('low-stock');
                Route::get('/low-stock/export',     [\App\Http\Controllers\Pos\ReportController::class, 'lowStockExport'])->name('low-stock.export');
                Route::get('/customer-debt',        [\App\Http\Controllers\Pos\ReportController::class, 'customerDebt'])->name('customer-debt')
                    ->middleware('pos.role:canManageDebt');
                Route::get('/customer-debt/export', [\App\Http\Controllers\Pos\ReportController::class, 'customerDebtExport'])->name('customer-debt.export')
                    ->middleware('pos.role:canManageDebt');
                Route::get('/expenses',               [\App\Http\Controllers\Pos\ExpenseReportController::class, 'index'])->name('expenses')
                    ->middleware('pos.role:canViewExpenseReports');
                // Item Expiry Report (Direct product expiry tracking)
                Route::get('/item-expiry',            [\App\Http\Controllers\Pos\ItemExpiryReportController::class, 'index'])->name('item-expiry')
                    ->middleware('pos.role:canViewExpiryReport');
                Route::get('/item-expiry/export',     [\App\Http\Controllers\Pos\ItemExpiryReportController::class, 'export'])->name('item-expiry.export')
                    ->middleware('pos.role:canViewExpiryReport');

                // Batch Expiry Report (Batch lot tracking & expiry)
                Route::get('/batch-expiry',           [\App\Http\Controllers\Pos\ExpiryReportController::class, 'index'])->name('batch-expiry')
                    ->middleware('pos.role:canViewBatchExpiryReport');
                Route::get('/batch-expiry/export',    [\App\Http\Controllers\Pos\ExpiryReportController::class, 'export'])->name('batch-expiry.export')
                    ->middleware('pos.role:canViewBatchExpiryReport');
                Route::get('/expiry-overview',        [\App\Http\Controllers\Pos\ExpiryReportController::class, 'index'])->name('expiry-overview')
                    ->middleware('pos.role:canViewBatchExpiryReport');
                Route::get('/expiry-overview/export', [\App\Http\Controllers\Pos\ExpiryReportController::class, 'export'])->name('expiry-overview.export')
                    ->middleware('pos.role:canViewBatchExpiryReport');
            });

        // Expenses & Categories Management
        Route::post('expenses', [\App\Http\Controllers\Pos\ExpenseController::class, 'store'])
            ->name('expenses.store')
            ->middleware('pos.role:canLogExpense');
        Route::put('expenses/{expense}', [\App\Http\Controllers\Pos\ExpenseController::class, 'update'])
            ->name('expenses.update')
            ->middleware('pos.role:canManageExpenses');
        Route::delete('expenses/{expense}', [\App\Http\Controllers\Pos\ExpenseController::class, 'destroy'])
            ->name('expenses.destroy')
            ->middleware('pos.role:canManageExpenses');

        Route::post('expense-categories', [\App\Http\Controllers\Pos\ExpenseCategoryController::class, 'store'])
            ->name('expense-categories.store')
            ->middleware('pos.role:canLogExpense');
        Route::put('expense-categories/{category}', [\App\Http\Controllers\Pos\ExpenseCategoryController::class, 'update'])
            ->name('expense-categories.update')
            ->middleware('pos.role:canManageExpenses');
        Route::delete('expense-categories/{category}', [\App\Http\Controllers\Pos\ExpenseCategoryController::class, 'destroy'])
            ->name('expense-categories.destroy')
            ->middleware('pos.role:canManageExpenses');

        // Users & Roles
        Route::resource('users', \App\Http\Controllers\Pos\UserController::class)
            ->except(['create', 'show', 'edit'])
            ->middleware('pos.role:canManageUsers');

        Route::post('users/{user}/toggle', [\App\Http\Controllers\Pos\UserController::class, 'toggle'])
            ->name('users.toggle')
            ->middleware('pos.role:canManageUsers');

        Route::post('users/{user}/reset-password', [\App\Http\Controllers\Pos\UserController::class, 'resetPassword'])
            ->name('users.reset-password')
            ->middleware('pos.role:canManageUsers');

        Route::resource('roles', \App\Http\Controllers\Pos\RoleController::class)
            ->except(['create', 'show', 'edit'])
            ->middleware('pos.role:canManageUsers');

        // Categories
        Route::resource('categories', \App\Http\Controllers\Pos\CategoryController::class)
            ->except(['create', 'show', 'edit'])
            ->middleware('pos.role:canManageItems');

        // Group / Address Storage Locations
        Route::resource('group-addresses', \App\Http\Controllers\Pos\GroupAddressController::class)
            ->except(['create', 'show', 'edit'])
            ->middleware('pos.role:canManageItems');

        // Items
        Route::get('items/export-template', [PosItemController::class, 'exportTemplate'])->name('items.export-template')->middleware('pos.role:canManageItems');
        Route::get('items/export',          [PosItemController::class, 'exportCsv'])->name('items.export')->middleware('pos.role:canManageItems');
        Route::get('items/print-catalog',   [PosItemController::class, 'printCatalog'])->name('items.print-catalog');
        Route::post('items/import',          [PosItemController::class, 'importNativeCsv'])->name('items.import')->middleware(['pos.role:canManageItems', 'plan.feature:inventory_import']);
        Route::post('items/import-medfusion',[PosItemController::class, 'importMedfusionCsv'])->name('items.import-medfusion')->middleware(['pos.role:canManageItems', 'plan.feature:inventory_import']);
        Route::post('items/bulk-action',     [PosItemController::class, 'bulkAction'])->name('items.bulk-action')->middleware('pos.role:canManageItems');
        Route::post('items/{item}/duplicate',[PosItemController::class, 'duplicate'])->name('items.duplicate')->middleware('pos.role:canManageItems');
        // Barcode Management & Studio
        Route::get('items/barcodes',                      [BarcodeController::class, 'index'])->name('items.barcodes')->middleware(['pos.role:canManageBarcodes', 'plan.feature:barcode_label_printing']);
        Route::get('items/barcodes/search-items',         [BarcodeController::class, 'searchItems'])->name('items.barcodes.search-items')->middleware(['pos.role:canManageBarcodes', 'plan.feature:barcode_label_printing']);
        Route::post('items/{item}/barcodes/generate',     [BarcodeController::class, 'generate'])->name('items.barcodes.generate')->middleware(['pos.role:canManageBarcodes', 'plan.feature:barcode_label_printing']);
        Route::post('items/barcodes/generate-bulk',       [BarcodeController::class, 'generateBulk'])->name('items.barcodes.generate-bulk')->middleware(['pos.role:canManageBarcodes', 'plan.feature:barcode_label_printing']);
        Route::post('items/barcodes/log-print',           [BarcodeController::class, 'logPrint'])->name('items.barcodes.log-print')->middleware(['pos.role:canManageBarcodes', 'plan.feature:barcode_label_printing']);

        // IMEI & Device Unit Tracking
        Route::get('items/imei-tracking',                 [\App\Http\Controllers\Pos\ImeiTrackingController::class, 'index'])->name('items.imeis')->middleware(['pos.role:canManageItems', 'plan.feature:imei_tracking']);
        Route::get('items/{item}/available-imeis',        [PosItemController::class, 'availableImeis'])->name('items.available-imeis');

        Route::resource('items', PosItemController::class)
            ->names([
                'index'   => 'items.index',
                'create'  => 'items.create',
                'store'   => 'items.store',
                'edit'    => 'items.edit',
                'update'  => 'items.update',
                'destroy' => 'items.destroy',
            ])
            ->middleware('pos.role:canManageItems');

        // Item Batches & Expiry Tracking
        Route::get('items/{item}/batches',           [\App\Http\Controllers\Pos\ItemBatchController::class, 'index'])->name('items.batches')->middleware(['pos.role:canManageItems', 'plan.feature:batch_expiry_tracking']);
        Route::post('batches',                       [\App\Http\Controllers\Pos\ItemBatchController::class, 'store'])->name('batches.store')->middleware(['pos.role:canManageItems', 'plan.feature:batch_expiry_tracking']);
        Route::put('batches/{batch}',                [\App\Http\Controllers\Pos\ItemBatchController::class, 'update'])->name('batches.update')->middleware(['pos.role:canManageItems', 'plan.feature:batch_expiry_tracking']);
        Route::delete('batches/{batch}',             [\App\Http\Controllers\Pos\ItemBatchController::class, 'destroy'])->name('batches.destroy')->middleware(['pos.role:canManageItems', 'plan.feature:batch_expiry_tracking']);
        Route::post('batches/issues/{issue}/resolve', [\App\Http\Controllers\Pos\ItemBatchController::class, 'resolveIssue'])->name('batches.resolve-issue')->middleware(['pos.role:canManageItems', 'plan.feature:batch_expiry_tracking']);

        // Item Grids
        Route::resource('item-grids', ItemGridController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names([
                'index'   => 'item-grids.index',
                'store'   => 'item-grids.store',
                'update'  => 'item-grids.update',
                'destroy' => 'item-grids.destroy',
            ])
            ->middleware('pos.role:canManageItems');

        // Customers
        Route::resource('customers', PosCustomerController::class)
            ->names([
                'index'   => 'customers.index',
                'create'  => 'customers.create',
                'store'   => 'customers.store',
                'show'    => 'customers.show',
                'edit'    => 'customers.edit',
                'update'  => 'customers.update',
                'destroy' => 'customers.destroy',
            ])
            ->middleware('pos.role:canManageCustomers');
        Route::post('/customers/{customer}/toggle-watchlist',
                [PosCustomerController::class, 'toggleWatchlist'])
            ->name('customers.toggle-watchlist')
            ->middleware('pos.role:canManageCustomers');
        Route::patch('/customers/{customer}/note',
                [PosCustomerController::class, 'updateNote'])
            ->name('customers.update-note')
            ->middleware('pos.role:canManageCustomers');
        Route::post('/customers/{customer}/debt',
                [PosCustomerController::class, 'recordDebt'])
            ->name('customers.debt')
            ->middleware(['pos.role:canGiveDebt', 'plan.feature:debt_customer_accounts']);
        Route::get('/customers/{customer}/debt-ledger',
                [PosCustomerController::class, 'debtLedger'])
            ->name('customers.debt-ledger')
            ->middleware(['pos.role:canManageDebt', 'plan.feature:debt_customer_accounts']);

        // Vendors
        Route::resource('vendors', \App\Http\Controllers\Pos\VendorController::class)
            ->except(['create', 'show', 'edit'])
            ->names([
                'index'   => 'vendors.index',
                'store'   => 'vendors.store',
                'update'  => 'vendors.update',
                'destroy' => 'vendors.destroy',
            ]);

        // Purchase Orders
        Route::resource('purchases', \App\Http\Controllers\Pos\PurchaseOrderController::class)
            ->names([
                'index'   => 'purchases.index',
                'create'  => 'purchases.create',
                'store'   => 'purchases.store',
                'show'    => 'purchases.show',
                'destroy' => 'purchases.destroy',
            ]);
        Route::get('/purchases/{purchase}/receive', [\App\Http\Controllers\Pos\PurchaseOrderController::class, 'receiveForm'])
            ->name('purchases.receive');
        Route::post('/purchases/{purchase}/receive', [\App\Http\Controllers\Pos\PurchaseOrderController::class, 'processReceive'])
            ->name('purchases.process-receive');

        // Settings
        Route::get('/settings',  [PosSettingsController::class, 'index'])
            ->name('settings.index')
            ->middleware('pos.role:canEditSettings');
        Route::match(['put', 'post'], '/settings', [PosSettingsController::class, 'update'])
            ->name('settings.update')
            ->middleware('pos.role:canEditSettings');

        // Till Sessions & Cash Movements (§3, §5, §7)
        Route::prefix('till')->name('till.')->group(function () {
            Route::get('/active',                         [TillSessionController::class, 'active'])->name('active');
            Route::post('/open',                          [TillSessionController::class, 'open'])->name('open');
            Route::post('/close',                         [TillSessionController::class, 'close'])->name('close');
            Route::post('/movements',                     [TillSessionController::class, 'storeMovement'])->name('movements.store')
                ->middleware('pos.role:canManageTillCashMovements');
            Route::get('/sessions',                       [TillSessionController::class, 'index'])->name('sessions.index')
                ->middleware('pos.role:canViewEndOfDay');
            Route::post('/sessions/{session}/force-close', [TillSessionController::class, 'forceClose'])->name('sessions.force-close')
                ->middleware('pos.role:canViewEndOfDay');
            Route::get('/sessions/{session}/slip',        [TillSessionController::class, 'slip'])->name('sessions.slip');
        });

        // Workstation & Terminal Printer Setup (Accessible to all staff)
        Route::get('/printer-setup', [\App\Http\Controllers\Pos\PrinterSetupController::class, 'index'])
            ->name('printer-setup');
        Route::post('/printer-setup/branch-default', [\App\Http\Controllers\Pos\PrinterSetupController::class, 'saveBranchDefault'])
            ->name('printer-setup.save-branch-default')
            ->middleware('pos.role:canEditSettings');

        // Branch Audit & Error Logs
        Route::get('/reports/logs', [\App\Http\Controllers\Pos\BranchLogController::class, 'index'])
            ->name('reports.logs');

        // Branch Ecommerce Activity (Operational Visibility & Narrow Actions)
        Route::prefix('ecommerce-activity')->name('ecommerce-activity.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Pos\EcommerceActivityController::class, 'index'])
                ->name('index');
            Route::get('/export', [\App\Http\Controllers\Pos\EcommerceActivityController::class, 'export'])
                ->name('export');
            Route::post('/reservations/{reservation}/release', [\App\Http\Controllers\Pos\EcommerceActivityController::class, 'releaseReservation'])
                ->name('reservations.release');
            Route::post('/mappings/{mapping}/deactivate', [\App\Http\Controllers\Pos\EcommerceActivityController::class, 'deactivateMapping'])
                ->name('mappings.deactivate');
        });

        // Double-Entry Accounting Module (§8, §9)
        Route::prefix('accounting')->name('accounting.')->middleware(['plan.feature:feature_accounting', 'pos.role:canViewAccounting'])->group(function () {
            // Chart of Accounts
            Route::get('/accounts/export', [\App\Modules\Accounting\Controllers\AccountController::class, 'export'])
                ->name('accounts.export')
                ->middleware('pos.role:canManageChartOfAccounts');
            Route::get('/accounts', [\App\Modules\Accounting\Controllers\AccountController::class, 'index'])
                ->name('accounts.index')
                ->middleware('pos.role:canManageChartOfAccounts');
            Route::post('/accounts', [\App\Modules\Accounting\Controllers\AccountController::class, 'store'])
                ->name('accounts.store')
                ->middleware('pos.role:canManageChartOfAccounts');
            Route::put('/accounts/{account}', [\App\Modules\Accounting\Controllers\AccountController::class, 'update'])
                ->name('accounts.update')
                ->middleware('pos.role:canManageChartOfAccounts');

            // General Ledger
            Route::get('/ledger/export', [\App\Modules\Accounting\Controllers\LedgerController::class, 'export'])
                ->name('ledger.export');
            Route::get('/ledger', [\App\Modules\Accounting\Controllers\LedgerController::class, 'index'])
                ->name('ledger.index');

            // Journal Entries
            Route::get('/journals/export', [\App\Modules\Accounting\Controllers\JournalController::class, 'export'])
                ->name('journals.export');
            Route::get('/journals', [\App\Modules\Accounting\Controllers\JournalController::class, 'index'])
                ->name('journals.index');
            Route::post('/journals', [\App\Modules\Accounting\Controllers\JournalController::class, 'store'])
                ->name('journals.store')
                ->middleware('pos.role:canManageJournalEntries');
            Route::get('/journals/{journal}', [\App\Modules\Accounting\Controllers\JournalController::class, 'show'])
                ->name('journals.show');
            Route::post('/journals/{journal}/reverse', [\App\Modules\Accounting\Controllers\JournalController::class, 'reverse'])
                ->name('journals.reverse')
                ->middleware('pos.role:canManageJournalEntries');

            // Financial Periods
            Route::get('/periods', [\App\Modules\Accounting\Controllers\PeriodController::class, 'index'])
                ->name('periods.index');
            Route::post('/periods/{period}/close', [\App\Modules\Accounting\Controllers\PeriodController::class, 'close'])
                ->name('periods.close')
                ->middleware('pos.role:canCloseFinancialPeriods');
            Route::post('/periods/{period}/reopen', [\App\Modules\Accounting\Controllers\PeriodController::class, 'reopen'])
                ->name('periods.reopen')
                ->middleware('pos.role:canCloseFinancialPeriods');

            // Opening Balances
            Route::get('/opening-balances', [\App\Modules\Accounting\Controllers\OpeningBalanceController::class, 'index'])
                ->name('opening-balances.index')
                ->middleware('pos.role:canManageChartOfAccounts');
            Route::post('/opening-balances', [\App\Modules\Accounting\Controllers\OpeningBalanceController::class, 'store'])
                ->name('opening-balances.store')
                ->middleware('pos.role:canManageChartOfAccounts');

            // Unposted Transactions Screen
            Route::get('/unposted', [\App\Modules\Accounting\Controllers\UnpostedTransactionsController::class, 'index'])
                ->name('unposted.index');
            Route::post('/unposted/retry', [\App\Modules\Accounting\Controllers\UnpostedTransactionsController::class, 'retry'])
                ->name('unposted.retry')
                ->middleware('pos.role:canManageJournalEntries');

            // Financial Reports
            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/trial-balance', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'trialBalance'])
                    ->name('trial-balance');
                Route::get('/profit-loss', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'profitLoss'])
                    ->name('profit-loss');
                Route::get('/balance-sheet', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'balanceSheet'])
                    ->name('balance-sheet');
                Route::get('/cash-flow', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'cashFlow'])
                    ->name('cash-flow');
                Route::get('/ar-aging', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'arAging'])
                    ->name('ar-aging');
                Route::get('/vat', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'vat'])
                    ->name('vat');
                Route::get('/export/{type}', [\App\Modules\Accounting\Controllers\AccountingReportController::class, 'export'])
                    ->name('export');
            });
        });

        // JSON API
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/items/search',          [PosItemController::class, 'search'])->name('items.search');
            Route::get('/items/available-imeis', [PosItemController::class, 'availableImeis'])->name('items.available-imeis');
            Route::get('/customers/search',      [PosCustomerController::class, 'search'])->name('customers.search');
            Route::get('/held-sales',            [HeldSaleController::class, 'apiIndex'])->name('held-sales.index');
            Route::post('/held-sales',           [HeldSaleController::class, 'apiStore'])->name('held-sales.store');
            Route::delete('/held-sales/{id}',     [HeldSaleController::class, 'apiDestroy'])->name('held-sales.destroy');
            Route::post('/sync-held-sales',       [HeldSaleController::class, 'apiSyncBatch'])->name('held-sales.sync');
            Route::get('/settings',              [PosSettingsController::class, 'apiShow'])->name('settings.show');
            Route::get('/offline-bootstrap',     [\App\Http\Controllers\Pos\OfflineSyncController::class, 'bootstrap'])->name('offline.bootstrap');
            Route::post('/sync-offline-sales',   [\App\Http\Controllers\Pos\OfflineSyncController::class, 'sync'])->name('offline.sync');
        });
    });
});
