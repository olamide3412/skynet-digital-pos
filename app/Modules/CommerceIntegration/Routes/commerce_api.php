<?php

use App\Modules\CommerceIntegration\Controllers\Api\CatalogApiController;
use App\Modules\CommerceIntegration\Controllers\Api\MappingApiController;
use App\Modules\CommerceIntegration\Controllers\Api\ReservationApiController;
use App\Modules\CommerceIntegration\Middleware\CommerceApiAuthenticate;
use Illuminate\Support\Facades\Route;

Route::middleware([CommerceApiAuthenticate::class])
    ->group(function () {
        foreach (['api/commerce/v1', 'api/pos-integration'] as $prefix) {
            Route::prefix($prefix)->group(function () {
                // Connection Verification endpoint
                Route::get('/ping', [CatalogApiController::class, 'ping']);
                Route::match(['get', 'post'], '/test-connection', [CatalogApiController::class, 'ping']);

                // Catalog & Inventory endpoints
                Route::get('/catalog', [CatalogApiController::class, 'index']);
                Route::get('/catalog/batch-stock', [CatalogApiController::class, 'batchStock']);
                Route::post('/availability', [CatalogApiController::class, 'batchStock']);
                Route::get('/catalog/{itemId}/stock', [CatalogApiController::class, 'stock'])->whereNumber('itemId');
                Route::get('/catalog/{itemId}', [CatalogApiController::class, 'stock'])->whereNumber('itemId');

                // Reservation Lifecycle endpoints
                Route::post('/reservations', [ReservationApiController::class, 'reserve']);
                Route::post('/reservations/confirm', [ReservationApiController::class, 'confirm']);
                Route::post('/reservations/{externalReference}/confirm', [ReservationApiController::class, 'confirm']);
                Route::post('/reservations/release', [ReservationApiController::class, 'release']);
                Route::post('/reservations/{externalReference}/release', [ReservationApiController::class, 'release']);
                Route::get('/reservations/{externalReference}', [ReservationApiController::class, 'status']);

                // Item Mapping Registry endpoints
                Route::get('/mappings', [MappingApiController::class, 'index']);
                Route::post('/mappings', [MappingApiController::class, 'upsert']);
                Route::post('/mappings/batch', [MappingApiController::class, 'batchUpsert']);
                Route::post('/mappings/register', [MappingApiController::class, 'batchUpsert']);
                Route::post('/mappings/reconcile', [MappingApiController::class, 'batchUpsert']);
                Route::post('/mappings/unregister', [MappingApiController::class, 'batchUnregister']);
                Route::delete('/mappings/{itemId}', [MappingApiController::class, 'destroy'])->whereNumber('itemId');
            });
        }
    });
