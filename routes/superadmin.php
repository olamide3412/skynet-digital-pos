<?php

use App\Http\Controllers\SuperAdmin\BranchController;
use App\Http\Controllers\SuperAdmin\BranchUserController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\SuperAdmin\GlobalItemController;
use App\Http\Controllers\SuperAdmin\ProfileController as SuperAdminProfileController;
use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use Illuminate\Support\Facades\Route;

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
        Route::post('/branches/{branch}/toggle-laundry', [BranchController::class, 'toggleLaundry'])
            ->name('branches.toggle-laundry');
        Route::post('/branches/{branch}/toggle-pos', [BranchController::class, 'togglePos'])
            ->name('branches.toggle-pos');
        Route::put('/branches/{branch}/settings', [BranchController::class, 'updateSettings'])
            ->name('branches.settings.update');
        Route::put('/branches/{branch}/theme', [BranchController::class, 'updateTheme'])
            ->name('branches.theme.update');
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

