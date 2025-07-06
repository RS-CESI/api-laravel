<?php

use App\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::prefix('favorites')->group(function () {
    Route::get('/', [FavoriteController::class, 'index']);
    Route::post('/resources/{resource}', [FavoriteController::class, 'toggle']);
    Route::delete('/resources/{resource}', [FavoriteController::class, 'remove']);
});
