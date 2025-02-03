<?php

use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::apiResource('workouts', WorkoutController::class);