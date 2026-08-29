<?php

use App\Modules\AI\Controllers\AiSettingsController;
use App\Modules\AI\Controllers\ChatAssistantController;
use App\Modules\AI\Controllers\ReorderPredictionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Module Routes
|--------------------------------------------------------------------------
*/

// ── Super Admin AI Routes ──────────────────────────────────────────────────
Route::prefix('superadmin/ai')
    ->name('superadmin.ai.')
    ->middleware(['web', 'auth:web', 'superadmin'])
    ->group(function () {
        // AI Provider Settings
        Route::get('/settings',                 [AiSettingsController::class, 'index'])->name('settings');
        Route::post('/settings',                [AiSettingsController::class, 'update'])->name('settings.update');
        Route::post('/test-connection',         [AiSettingsController::class, 'testConnection'])->name('test-connection');
        Route::post('/branches/{branch}/toggle',[AiSettingsController::class, 'toggleBranch'])->name('branches.toggle');

        // Cross-Branch Reorder Insights
        Route::get('/reorder',                  [ReorderPredictionController::class, 'index'])->name('reorder');

        // Cross-Branch Chat Assistant
        Route::get('/chat',                     [ChatAssistantController::class, 'index'])->name('chat');
        Route::post('/chat/ask',                [ChatAssistantController::class, 'ask'])->name('chat.ask');
        Route::delete('/chat/clear',            [ChatAssistantController::class, 'clear'])->name('chat.clear');
    });

// ── Branch Admin / POS AI Routes ───────────────────────────────────────────
Route::prefix('{branch}/ai')
    ->name('pos.ai.')
    ->middleware(['web', 'resolve.branch', 'auth:web', 'branch.scope'])
    ->group(function () {
        // Reorder Predictions
        Route::get('/reorder',          [ReorderPredictionController::class, 'index'])->name('reorder');
        Route::post('/reorder/notify',  [ReorderPredictionController::class, 'notify'])->name('reorder.notify');

        // Ask-Your-Business Chat Assistant
        Route::get('/chat',             [ChatAssistantController::class, 'index'])->name('chat');
        Route::post('/chat/ask',        [ChatAssistantController::class, 'ask'])->name('chat.ask');
        Route::delete('/chat/clear',    [ChatAssistantController::class, 'clear'])->name('chat.clear');
    });
