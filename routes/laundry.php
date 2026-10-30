<?php

use App\Modules\Laundry\Controllers\LaundryDiscountController;
use App\Modules\Laundry\Controllers\LaundryHomeController;
use App\Modules\Laundry\Controllers\LaundryOrderController;
use App\Modules\Laundry\Controllers\LaundryReportController;
use App\Modules\Laundry\Controllers\LaundryServiceController;
use Illuminate\Support\Facades\Route;

/* ══════════════════════════════════════════════════════════════════════════════
 *  BRANCH LAUNDRY MODULE — /{branch}/laundry/...
 *  branch = slug (route model binding via getRouteKeyName = 'slug')
 * ══════════════════════════════════════════════════════════════════════════════ */
Route::prefix('{branch}/laundry')
    ->name('laundry.')
    ->middleware(['resolve.branch', 'auth:web', 'branch.scope', 'ensure.laundry.enabled'])
    ->group(function () {
        Route::get('/', [LaundryHomeController::class, 'index'])->name('home');

        // Customer search & quick add for laundry intake
        Route::get('/customers/search', [LaundryOrderController::class, 'searchCustomers'])->name('customers.search');
        Route::post('/customers/quick', [LaundryOrderController::class, 'quickCreateCustomer'])->name('customers.quick');

        // Orders & Workflow Board
        Route::get('/orders', [LaundryOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/export', [LaundryOrderController::class, 'export'])->name('orders.export');
        Route::get('/board', [LaundryOrderController::class, 'board'])->name('board.index');
        Route::get('/orders/create', [LaundryOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [LaundryOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/column-orders', [LaundryOrderController::class, 'columnOrders'])->name('orders.column-orders');
        Route::get('/board/column-orders', [LaundryOrderController::class, 'columnOrders'])->name('board.column-orders');
        Route::get('/orders/{order}', [LaundryOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [LaundryOrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('/orders/{order}/payments', [LaundryOrderController::class, 'recordPayment'])->name('orders.record-payment');
        Route::post('/orders/{order}/cancel', [LaundryOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/collect', [LaundryOrderController::class, 'markCollected'])->name('orders.collect');
        Route::get('/orders/{order}/ticket', [LaundryOrderController::class, 'printTicket'])->name('orders.ticket');
        Route::get('/orders/{order}/whatsapp-link', [LaundryOrderController::class, 'whatsappLink'])->name('orders.whatsapp-link');

        // Services Catalog & Categories
        Route::get('/services', [LaundryServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [LaundryServiceController::class, 'store'])->name('services.store');

        // Specific category sub-routes before {service} wildcard
        Route::post('/services/categories', [LaundryServiceController::class, 'storeCategory'])->name('services.categories.store');
        Route::put('/services/categories/{id}', [LaundryServiceController::class, 'updateCategory'])->name('services.categories.update');
        Route::delete('/services/categories/{id}', [LaundryServiceController::class, 'destroyCategory'])->name('services.categories.destroy');

        // Service wildcard routes
        Route::put('/services/{service}', [LaundryServiceController::class, 'update'])->name('services.update');
        Route::patch('/services/{service}/toggle', [LaundryServiceController::class, 'toggleActive'])->name('services.toggle');
        Route::delete('/services/{service}', [LaundryServiceController::class, 'destroy'])->name('services.destroy');

        // Discounts & Promotions (General and Service Level)
        Route::get('/discounts', [LaundryDiscountController::class, 'index'])->name('discounts.index');
        Route::post('/discounts', [LaundryDiscountController::class, 'store'])->name('discounts.store');
        Route::put('/discounts/{discount}', [LaundryDiscountController::class, 'update'])->name('discounts.update');
        Route::patch('/discounts/{discount}/toggle', [LaundryDiscountController::class, 'toggleActive'])->name('discounts.toggle');
        Route::delete('/discounts/{discount}', [LaundryDiscountController::class, 'destroy'])->name('discounts.destroy');

        // Reports
        Route::get('/reports', [LaundryReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/end-of-day', [LaundryReportController::class, 'endOfDay'])->name('reports.end-of-day');
    });

