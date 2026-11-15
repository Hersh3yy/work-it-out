<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MagicLoginController;
use App\Http\Controllers\Api\OverviewController;
use App\Http\Controllers\Api\SmartLogController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Sign in with the one-time link from /login in Telegram
// -------------------------------------------------------------------------
Route::post('/auth/magic', [MagicLoginController::class, 'store'])->middleware('throttle:login');

// -------------------------------------------------------------------------
// Authenticated routes (Sanctum bearer token required)
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // The app's home: facts, written notes, limitations, recent messages
    Route::get('/me/overview', [OverviewController::class, 'show']);
    Route::get('/logs', [OverviewController::class, 'logs']);
    Route::post('/limitations/{limitation}/resolve', [OverviewController::class, 'resolveLimitation']);

    // The app's door for logging, the same interpreter as the chat
    Route::post('/log', [SmartLogController::class, 'store'])
        ->middleware('throttle:smart-log');
    Route::post('/log/{log}/answer', [SmartLogController::class, 'answer']);
    Route::post('/log/{log}/skip', [SmartLogController::class, 'skip']);
    Route::delete('/log/{log}', [SmartLogController::class, 'destroy']);
});
