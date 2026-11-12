<?php

use App\Modules\CommerceIntegration\Controllers\Api\V1\EcommerceMappingController;
use App\Modules\CommerceIntegration\Middleware\CommerceApiAuthenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware([CommerceApiAuthenticate::class])->group(function () {
    // V1 Versioned routes only
    Route::prefix('v1')->group(function () {
        Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
        Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
        Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);

        Route::prefix('pos-integration')->group(function () {
            Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
            Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
            Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);
        });

        Route::prefix('commerce')->group(function () {
            Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
            Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
            Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);
        });
    });

    Route::prefix('pos-integration/v1')->group(function () {
        Route::post('/mappings/register', [EcommerceMappingController::class, 'register']);
        Route::post('/mappings/unregister', [EcommerceMappingController::class, 'unregister']);
        Route::post('/mappings/reconcile', [EcommerceMappingController::class, 'reconcile']);
    });
});
