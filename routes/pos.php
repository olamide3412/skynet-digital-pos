<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Pos\BarcodeController;
use App\Http\Controllers\Pos\CustomerController as PosCustomerController;
use App\Http\Controllers\Pos\DiscountGroupController;
use App\Http\Controllers\Pos\HeldSaleController;
use App\Http\Controllers\Pos\ItemController as PosItemController;
use App\Http\Controllers\Pos\ItemDiscountController;
use App\Http\Controllers\Pos\ItemGridController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\SaleController;
use App\Http\Controllers\Pos\SaleDiscountController;
use App\Http\Controllers\Pos\SaleReturnController;
use App\Http\Controllers\Pos\SettingsController as PosSettingsController;
use App\Http\Controllers\Pos\StockTransferController;
use App\Http\Controllers\Pos\TillSessionController;
use Illuminate\Support\Facades\Route;

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
            ->middleware(['ensure.pos.enabled', 'pos.role:canAccessPos']);

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

