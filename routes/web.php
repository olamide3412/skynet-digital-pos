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
use App\Http\Controllers\Pos\SaleDiscountController;
use App\Http\Controllers\Pos\StockTransferController;
use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\SuperAdmin\BranchController;
use App\Http\Controllers\SuperAdmin\BranchUserController;
use App\Http\Controllers\SuperAdmin\GlobalItemController;

// Storefront Controllers
use App\Http\Controllers\Frontend\HomeController;

use Illuminate\Support\Facades\Route;

/* ══════════════════════════════════════════════════════════════════════════════
 *  PUBLIC & AUTH FALLBACK ROUTES
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::get('/', [HomeController::class, 'index'])->name('home');

// Global Auth Fallbacks — Redirects /login to current or default branch login
Route::get('/login', function () {
    $branchSlug = current_branch()?->slug
        ?? \App\Models\Branch::first()?->slug
        ?? 'skynet-digital-enterprise';
    return redirect()->route('pos.login', ['branch' => $branchSlug]);
})->name('login');

Route::post('/logout',     [AuthController::class, 'destroy'])->name('logout');
Route::post('/pos/logout', [AuthController::class, 'destroy'])->name('pos.logout');


/* ══════════════════════════════════════════════════════════════════════════════
 *  SUPER ADMIN PANEL — /superadmin/...
 *  No branch context here. Separate auth, no ResolveBranch middleware.
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/login',  [SuperAdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SuperAdminAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout',[SuperAdminAuthController::class, 'destroy'])->name('logout')
        ->middleware('auth:web');

    Route::middleware(['auth:web', 'superadmin'])->group(function () {
        Route::get('/dashboard', [SuperAdminDashboard::class, 'index'])->name('dashboard');

        Route::resource('branches', BranchController::class)
            ->except(['show'])
            ->names([
                'index'   => 'branches.index',
                'create'  => 'branches.create',
                'store'   => 'branches.store',
                'edit'    => 'branches.edit',
                'update'  => 'branches.update',
                'destroy' => 'branches.destroy',
            ]);

        Route::post('/branches/{branch}/toggle', [BranchController::class, 'toggle'])
            ->name('branches.toggle');

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
    });
});

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
            Route::post('/transfers',   [StockTransferController::class, 'store'])
                ->name('transfers.store')
                ->middleware('pos.role:canTransferStock');
        });

        // Reports
        Route::get('/reports/end-of-day',
                [\App\Http\Controllers\Pos\ReportController::class, 'endOfDay'])
            ->name('reports.end-of-day')
            ->middleware('pos.role:canViewEndOfDay');

        Route::prefix('reports')->name('reports.')
            ->middleware('pos.role:canViewReports')
            ->group(function () {
                Route::get('/',             [\App\Http\Controllers\Pos\ReportController::class, 'index'])->name('index');
                Route::get('/daily-sales',  [\App\Http\Controllers\Pos\ReportController::class, 'dailySales'])->name('daily-sales');
                Route::get('/profit-loss',  [\App\Http\Controllers\Pos\ReportController::class, 'profitLoss'])->name('profit-loss')
                    ->middleware('pos.role:canViewProfitLoss');
                Route::get('/low-stock',    [\App\Http\Controllers\Pos\ReportController::class, 'lowStock'])->name('low-stock');
                Route::get('/customer-debt',[\App\Http\Controllers\Pos\ReportController::class, 'customerDebt'])->name('customer-debt')
                    ->middleware('pos.role:canManageDebt');
            });

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
        Route::post('items/import',          [PosItemController::class, 'importNativeCsv'])->name('items.import')->middleware('pos.role:canManageItems');
        Route::post('items/import-medfusion',[PosItemController::class, 'importMedfusionCsv'])->name('items.import-medfusion')->middleware('pos.role:canManageItems');
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
        Route::post('/customers/{customer}/debt',
                [PosCustomerController::class, 'recordDebt'])
            ->name('customers.debt')
            ->middleware('pos.role:canGiveDebt');
        Route::get('/customers/{customer}/debt-ledger',
                [PosCustomerController::class, 'debtLedger'])
            ->name('customers.debt-ledger')
            ->middleware('pos.role:canManageDebt');

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

        // JSON API
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/items/search',     [PosItemController::class, 'search'])->name('items.search');
            Route::get('/customers/search', [PosCustomerController::class, 'search'])->name('customers.search');
            Route::get('/held-sales',       [HeldSaleController::class, 'apiIndex'])->name('held-sales.index');
            Route::post('/held-sales',      [HeldSaleController::class, 'apiStore'])->name('held-sales.store');
            Route::delete('/held-sales/{id}',[HeldSaleController::class, 'apiDestroy'])->name('held-sales.destroy');
            Route::get('/settings',         [PosSettingsController::class, 'apiShow'])->name('settings.show');
        });
    });
});
