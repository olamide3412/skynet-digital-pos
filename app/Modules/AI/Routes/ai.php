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
        Route::post('/reorder/insight',         [ReorderPredictionController::class, 'generateInsight'])->name('reorder.insight');

        // Cross-Branch Chat Assistant
        Route::get('/chat',                     [ChatAssistantController::class, 'index'])->name('chat');
        Route::post('/chat/ask',                [ChatAssistantController::class, 'ask'])->name('chat.ask');
        Route::delete('/chat/clear',            [ChatAssistantController::class, 'clear'])->name('chat.clear');
    });

// ── Branch Admin / POS AI Routes ───────────────────────────────────────────
Route::prefix('{branch}/ai')
    ->where(['branch' => '(?!org(?=/|$)|superadmin(?=/|$))[a-zA-Z0-9_\-]+'])
    ->name('pos.ai.')
    ->middleware(['web', 'resolve.branch', 'auth:web', 'branch.scope'])
    ->group(function () {
        // Reorder Predictions
        Route::get('/reorder',          [ReorderPredictionController::class, 'index'])->name('reorder')->middleware('plan.feature:ai_reorder_prediction');
        Route::post('/reorder/insight', [ReorderPredictionController::class, 'generateInsight'])->name('reorder.insight')->middleware('plan.feature:ai_reorder_prediction');
        Route::post('/reorder/notify',  [ReorderPredictionController::class, 'notify'])->name('reorder.notify')->middleware('plan.feature:ai_reorder_prediction');

        // Ask-Your-Business Chat Assistant
        Route::get('/chat',             [ChatAssistantController::class, 'index'])->name('chat')->middleware('plan.feature:ai_chat_assistant');
        Route::post('/chat/ask',        [ChatAssistantController::class, 'ask'])->name('chat.ask')->middleware('plan.feature:ai_chat_assistant');
        Route::delete('/chat/clear',    [ChatAssistantController::class, 'clear'])->name('chat.clear')->middleware('plan.feature:ai_chat_assistant');
    });
