<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/api/auth.php';

// Middleware public (non authentifié)
Route::prefix('public')->middleware('throttle.public')->group(function () {
    require __DIR__.'/api/public.php';
});

// Middleware auth
Route::middleware(['auth:sanctum', 'verified', 'throttle.auth'])->group(function () {
    require __DIR__.'/api/resources.php';
    require __DIR__.'/api/favorites.php';
    require __DIR__.'/api/progressions.php';
    require __DIR__.'/api/comments.php';
    require __DIR__.'/api/activities.php';
    require __DIR__.'/api/profile.php';
    require __DIR__.'/api/moderation.php';
    require __DIR__.'/api/admin.php';
    require __DIR__.'/api/superadmin.php';
    require __DIR__.'/api/statistics.php';
});

// Fallback route
require __DIR__.'/api/fallback.php';
