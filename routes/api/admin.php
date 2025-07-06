<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Controllers\Admin\AdminStatisticsController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\RelationTypeController;
use App\Http\Controllers\ResourceTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:administrator,super-administrator')->group(function () {
    Route::prefix('admin')->group(function () {

        // Gestion des ressources
        Route::apiResource('resources', AdminResourceController::class);
        Route::get('/resources/stats/overview', [AdminResourceController::class, 'statsOverview']);

        // Gestion des utilisateurs
        Route::apiResource('users', AdminUserController::class);
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate']);
        Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate']);
        Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole']);

        // Gestion des catégories
        Route::apiResource('categories', AdminCategoryController::class);
        Route::post('/categories/{category}/activate', [AdminCategoryController::class, 'activate']);
        Route::post('/categories/{category}/deactivate', [AdminCategoryController::class, 'deactivate']);

        // Gestion des types de relations
        Route::apiResource('relation-types', RelationTypeController::class);

        // Gestion des types de ressources
        Route::apiResource('resource-types', ResourceTypeController::class);
        Route::get('/resource-types/active', [ResourceTypeController::class, 'getActiveTypes']);
        Route::patch('/resource-types/sort-order', [ResourceTypeController::class, 'updateSortOrder']);
        Route::post('/resource-types/{resourceType}/toggle-status', [ResourceTypeController::class, 'toggleStatus']);
        Route::post('/resource-types/{resourceType}/validate-file', [ResourceTypeController::class, 'validateFileType']);
        Route::get('/resource-types/stats', [ResourceTypeController::class, 'getStats']);
        Route::get('/resource-types/common-file-types', [ResourceTypeController::class, 'getCommonFileTypes']);

        // Statistiques avancées
        Route::prefix('statistics')->group(function () {
            Route::get('/dashboard', [AdminStatisticsController::class, 'dashboard']);
            Route::get('/resources', [AdminStatisticsController::class, 'resourceStats']);
            Route::get('/users', [AdminStatisticsController::class, 'userStats']);
            Route::get('/activities', [AdminStatisticsController::class, 'activityStats']);
            Route::get('/engagement', [AdminStatisticsController::class, 'engagementStats']);
            Route::post('/export', [AdminStatisticsController::class, 'export']);
        });
    });
});
