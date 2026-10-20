<?php

use App\Modules\CommerceIntegration\Controllers\Api\V1\CatalogApiController;
use App\Modules\CommerceIntegration\Controllers\Api\V1\EcommerceMappingController;
use App\Modules\CommerceIntegration\Controllers\Api\V1\MappingApiController;
use App\Modules\CommerceIntegration\Controllers\Api\V1\ReservationApiController;
use App\Modules\CommerceIntegration\Middleware\CommerceApiAuthenticate;
use Illuminate\Support\Facades\Route;

Route::middleware([CommerceApiAuthenticate::class])
    ->group(function () {
        // Supported API prefixes: Canonical V1 versioned only (no backward compatibility)
        $prefixes = [
            'api/v1/pos-integration',
            'api/pos-integration/v1',
            'api/v1/commerce',
            'api/commerce/v1',
        ];

        foreach ($prefixes as $prefix) {
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
                Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
                Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);
                Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
                Route::delete('/mappings/{itemId}', [MappingApiController::class, 'destroy'])->whereNumber('itemId');
            });
        }

        // Direct /v1/mappings/* and /api/v1/mappings/* endpoints
        foreach (['v1/mappings', 'api/v1/mappings'] as $mappingPrefix) {
            Route::prefix($mappingPrefix)->group(function () {
                Route::post('/register', [EcommerceMappingController::class, 'register']);
                Route::post('/unregister', [EcommerceMappingController::class, 'unregister']);
                Route::post('/reconcile', [EcommerceMappingController::class, 'reconcile']);
            });
        }
    });
