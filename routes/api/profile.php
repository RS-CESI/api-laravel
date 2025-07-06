<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->group(function () {
    Route::get('/', [UserController::class, 'profile']);
    Route::put('/', [UserController::class, 'updateProfile']);
    Route::put('/password', [UserController::class, 'updatePassword']);
    Route::delete('/', [UserController::class, 'deleteAccount']);

    // Statistiques personnelles
    Route::get('/stats', [UserController::class, 'personalStats']);
});
