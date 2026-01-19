<?php

use DhruvilNagar\ActionEngine\Http\Controllers\Api\BulkActionController;
use DhruvilNagar\ActionEngine\Http\Controllers\Api\ProgressController;
use DhruvilNagar\ActionEngine\Http\Controllers\Api\UndoController;
use Illuminate\Support\Facades\Route;

$prefix = config('action-engine.routes.prefix', 'bulk-actions');
$middleware = config('action-engine.routes.middleware.api', ['api', 'auth:sanctum']);

Route::middleware($middleware)
    ->prefix("api/{$prefix}")
    ->group(function () {
        // List user's bulk actions
        Route::get('/', [BulkActionController::class, 'index'])
            ->name('action-engine.index');

        // Get available actions
        Route::get('/actions', [BulkActionController::class, 'actions'])
            ->name('action-engine.actions');

        // Execute bulk action
        Route::post('/', [BulkActionController::class, 'execute'])
            ->name('action-engine.execute');

        // Preview action (dry run)
        Route::post('/preview', [BulkActionController::class, 'preview'])
            ->name('action-engine.preview');

        // Get execution details
        Route::get('/{uuid}', [BulkActionController::class, 'show'])
            ->name('action-engine.show');

        // Cancel action
        Route::post('/{uuid}/cancel', [BulkActionController::class, 'cancel'])
            ->name('action-engine.cancel');

        // Get progress
        Route::get('/{uuid}/progress', [ProgressController::class, 'show'])
            ->name('action-engine.progress');

        // Undo action
        Route::post('/{uuid}/undo', [UndoController::class, 'undo'])
            ->name('action-engine.undo');

        // Check if action can be undone
        Route::get('/{uuid}/undo', [UndoController::class, 'check'])
            ->name('action-engine.undo.check');
    });
