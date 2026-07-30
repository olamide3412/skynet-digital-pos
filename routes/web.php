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
use Illuminate\Support\Facades\Route;

/* ── PUBLIC Redirects ──────────────────────────────────────────────────────── */
Route::get('/', function() {
    return redirect()->route('pos.index');
})->name('home');

Route::redirect('/login', '/pos/login')->name('login');

/* ── POS PANEL (CORE SOFTWARE) ─────────────────────────────────────────────── */
Route::prefix('pos')->name('pos.')->middleware(['auth:web'])->group(function () {

    // POS Login (Uses standard web auth guard)
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->withoutMiddleware(['auth:web']);
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit')->withoutMiddleware(['auth:web']);
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    // Main POS Screen — requires PosAccess role
    Route::get('/', [PosController::class, 'index'])->name('index')->middleware('pos.role:canAccessPos');

    // Sales (store+show accessible to PosAccess users; index & delete have separate guards)
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store')->middleware('pos.role:canAccessPos');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');

    // Returns
    Route::post('/sale-returns', [SaleReturnController::class, 'store'])->name('sale-returns.store')->middleware('pos.role:canProcessReturn');

    // Discounts
    Route::resource('discounts', SaleDiscountController::class)->only(['index', 'store', 'update', 'destroy'])->names([
        'index'   => 'discounts.index',
        'store'   => 'discounts.store',
        'update'  => 'discounts.update',
        'destroy' => 'discounts.destroy',
    ])->middleware('pos.role:canApplyDiscount');

    // Purchasing / Vendors
    Route::resource('vendors', \App\Http\Controllers\Pos\VendorController::class)->except(['create', 'show', 'edit'])->middleware('pos.role:canManagePurchases');
    Route::resource('purchases', \App\Http\Controllers\Pos\PurchaseOrderController::class)->except(['edit', 'update'])->middleware('pos.role:canManagePurchases');
    Route::get('/purchases/{purchase}/receive', [\App\Http\Controllers\Pos\PurchaseOrderController::class, 'receiveForm'])->name('purchases.receive')->middleware('pos.role:canManagePurchases');
    Route::post('/purchases/{purchase}/receive', [\App\Http\Controllers\Pos\PurchaseOrderController::class, 'processReceive'])->name('purchases.process-receive')->middleware('pos.role:canManagePurchases');

    // Inventory
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Pos\InventoryController::class, 'index'])->name('index')->middleware('pos.role:canAdjustStock');
        Route::get('/adjust', [\App\Http\Controllers\Pos\InventoryController::class, 'adjustForm'])->name('adjust')->middleware('pos.role:canAdjustStock');
        Route::post('/adjust', [\App\Http\Controllers\Pos\InventoryController::class, 'processAdjustment'])->name('process-adjust')->middleware('pos.role:canAdjustStock');
    });

    // Reports — End of Day (accessible to any POS user — scoped to own data)
    Route::get('/reports/end-of-day', [\App\Http\Controllers\Pos\ReportController::class, 'endOfDay'])->name('reports.end-of-day')->middleware('pos.role:canViewEndOfDay');

    // Reports — Management level (full analytics)
    Route::prefix('reports')->name('reports.')->middleware('pos.role:canViewReports')->group(function () {
        Route::get('/', [\App\Http\Controllers\Pos\ReportController::class, 'index'])->name('index');
        Route::get('/daily-sales', [\App\Http\Controllers\Pos\ReportController::class, 'dailySales'])->name('daily-sales');
        Route::get('/profit-loss', [\App\Http\Controllers\Pos\ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/low-stock', [\App\Http\Controllers\Pos\ReportController::class, 'lowStock'])->name('low-stock');
        Route::get('/customer-debt', [\App\Http\Controllers\Pos\ReportController::class, 'customerDebt'])->name('customer-debt');
    });

    // Users and Roles (admin only)
    Route::resource('users', \App\Http\Controllers\Pos\UserController::class)->except(['create', 'show', 'edit'])->middleware('pos.role:canManageUsers');
    Route::resource('roles', \App\Http\Controllers\Pos\RoleController::class)->except(['create', 'show', 'edit'])->middleware('pos.role:canManageUsers');
    Route::resource('categories', \App\Http\Controllers\Pos\CategoryController::class)->except(['create', 'show', 'edit'])->middleware('pos.role:canManageItems');

    // Items
    Route::resource('items', PosItemController::class)->names([
        'index'   => 'items.index',
        'create'  => 'items.create',
        'store'   => 'items.store',
        'edit'    => 'items.edit',
        'update'  => 'items.update',
        'destroy' => 'items.destroy',
    ])->middleware('pos.role:canManageItems');

    // Item Grids (Gallery View Setup)
    Route::resource('item-grids', ItemGridController::class)->only(['index', 'store', 'update', 'destroy'])->names([
        'index'   => 'item-grids.index',
        'store'   => 'item-grids.store',
        'update'  => 'item-grids.update',
        'destroy' => 'item-grids.destroy',
    ])->middleware('pos.role:canManageItems');

    // Customers
    Route::resource('customers', PosCustomerController::class)->names([
        'index'   => 'customers.index',
        'create'  => 'customers.create',
        'store'   => 'customers.store',
        'show'    => 'customers.show',
        'edit'    => 'customers.edit',
        'update'  => 'customers.update',
        'destroy' => 'customers.destroy',
    ])->middleware('pos.role:canManageCustomers');
    Route::post('/customers/{customer}/debt', [PosCustomerController::class, 'recordDebt'])->name('customers.debt')->middleware('pos.role:canManageCustomers');
    Route::get('/customers/{customer}/debt-ledger', [PosCustomerController::class, 'debtLedger'])->name('customers.debt-ledger')->middleware('pos.role:canManageCustomers');

    // Settings (admin only)
    Route::get('/settings', [PosSettingsController::class, 'index'])->name('settings.index')->middleware('pos.role:canEditSettings');
    Route::put('/settings', [PosSettingsController::class, 'update'])->name('settings.update')->middleware('pos.role:canEditSettings');

    // API routes (JSON)
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/items/search', [PosItemController::class, 'search'])->name('items.search');
        Route::get('/customers/search', [PosCustomerController::class, 'search'])->name('customers.search');
        Route::get('/held-sales', [HeldSaleController::class, 'apiIndex'])->name('held-sales.index');
        Route::post('/held-sales', [HeldSaleController::class, 'apiStore'])->name('held-sales.store');
        Route::delete('/held-sales/{id}', [HeldSaleController::class, 'apiDestroy'])->name('held-sales.destroy');
        Route::get('/settings', [PosSettingsController::class, 'apiShow'])->name('settings.show');
    });
});
