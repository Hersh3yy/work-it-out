<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AiTrainerController;
use App\Http\Controllers\Api\BodyWeightController;
use App\Http\Controllers\Api\NutritionLogController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WorkoutSessionController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Public auth routes
// -------------------------------------------------------------------------
Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// -------------------------------------------------------------------------
// Authenticated routes (Sanctum bearer token required)
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {

    // Auth management
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/trainer', [ProfileController::class, 'updateTrainer']);

    // Workouts
    Route::apiResource('workouts', WorkoutSessionController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    // Nutrition
    Route::get('/nutrition', [NutritionLogController::class, 'index']);
    Route::post('/nutrition', [NutritionLogController::class, 'store']);
    Route::delete('/nutrition/{nutritionLog}', [NutritionLogController::class, 'destroy']);

    // Body weight
    Route::get('/body-weight', [BodyWeightController::class, 'index']);
    Route::post('/body-weight', [BodyWeightController::class, 'store']);

    // AI Trainer — rate-limited to 20 requests per user per hour
    Route::get('/trainer', [AiTrainerController::class, 'index']);
    Route::post('/trainer/chat', [AiTrainerController::class, 'chat'])
        ->middleware('throttle:trainer-chat');

    // Dashboard summary
    Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);
});
