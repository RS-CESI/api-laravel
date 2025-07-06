<?php

use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:super-administrator')->group(function () {
    Route::prefix('super-admin')->group(function () {
        // Création de comptes modérateurs/admins
        Route::post('/users/create-moderator', [AdminUserController::class, 'createModerator']);
        Route::post('/users/create-administrator', [AdminUserController::class, 'createAdministrator']);

        // Logs et audit
        Route::get('/logs', [AdminUserController::class, 'systemLogs']);
        Route::get('/audit', [AdminUserController::class, 'auditTrail']);

        // Configuration système
        Route::get('/config', [AdminUserController::class, 'systemConfig']);
        Route::put('/config', [AdminUserController::class, 'updateSystemConfig']);
    });
});
