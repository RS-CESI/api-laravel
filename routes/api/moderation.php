<?php

use App\Http\Controllers\ModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:moderator,administrator,super-administrator')->group(function () {
    Route::prefix('moderation')->group(function () {
        // Ressources en attente
        Route::get('/resources/pending', [ModerationController::class, 'pendingResources']);
        Route::post('/resources/{resource}/approve', [ModerationController::class, 'approveResource']);
        Route::post('/resources/{resource}/reject', [ModerationController::class, 'rejectResource']);
        Route::post('/resources/{resource}/suspend', [ModerationController::class, 'suspendResource']);

        // Commentaires en attente
        Route::get('/comments/pending', [ModerationController::class, 'pendingComments']);
        Route::post('/comments/{comment}/approve', [ModerationController::class, 'approveComment']);
        Route::post('/comments/{comment}/reject', [ModerationController::class, 'rejectComment']);
        Route::post('/comments/{comment}/hide', [ModerationController::class, 'hideComment']);
    });
});
