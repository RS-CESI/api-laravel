<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProgressionController;

Route::prefix('progressions')->group(function () {
    Route::get('/', [ProgressionController::class, 'index']);
    Route::get('/dashboard', [ProgressionController::class, 'dashboard']);
    Route::post('/resources/{resource}', [ProgressionController::class, 'createOrUpdate']);
    Route::put('/resources/{resource}', [ProgressionController::class, 'update']);
    Route::post('/resources/{resource}/start', [ProgressionController::class, 'start']);
    Route::post('/resources/{resource}/complete', [ProgressionController::class, 'complete']);
    Route::post('/resources/{resource}/bookmark', [ProgressionController::class, 'bookmark']);
    Route::post('/resources/{resource}/pause', [ProgressionController::class, 'pause']);
    Route::post('/resources/{resource}/time', [ProgressionController::class, 'addTime']);
    Route::post('/resources/{resource}/rate', [ProgressionController::class, 'rate']);
});
