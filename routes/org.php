<?php

use Illuminate\Support\Facades\Route;

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

