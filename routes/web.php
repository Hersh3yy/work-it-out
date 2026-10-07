<?php

declare(strict_types=1);

use App\Http\Controllers\LabController;
use App\Http\Middleware\LocalOnly;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Developer lab, local only: try the classify step per model in a browser.
Route::middleware(LocalOnly::class)->prefix('lab')->group(function (): void {
    Route::get('/classify', [LabController::class, 'page']);
    Route::post('/classify', [LabController::class, 'classify']);
    Route::post('/classify/eval', [LabController::class, 'evaluate']);
});
