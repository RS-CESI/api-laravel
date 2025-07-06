<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\RelationTypeController;
use App\Http\Controllers\ResourceTypeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ProgressionController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityParticipantController;
use App\Http\Controllers\ActivityMessageController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminStatisticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes pour l'application (RE)Sources Relationnelles
| Compatible Web et Mobile (Expo)
| Utilise les contrôleurs d'auth existants dans routes/auth.php
|
*/

// ==========================================
// INCLUSION DES ROUTES D'AUTHENTIFICATION
// ==========================================
require __DIR__.'/auth.php';

// ==========================================
// ROUTES PUBLIQUES (Sans authentification)
// ==========================================

// Login spécifique à l'API (en plus de celui dans auth.php)
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle.public'])
    ->name('api.login');

// Ressources publiques - Compatible Mobile
Route::prefix('public')->middleware('throttle.public')->group(function () {
    // Lister les ressources publiques
    Route::get('/resources', [ResourceController::class, 'indexPublic']);
    Route::get('/resources/{resource}', [ResourceController::class, 'showPublic']);

    // Catégories et types (pour filtres)
    Route::get('/categories', [CategoryController::class, 'indexPublic']);
    Route::get('/relation-types', [RelationTypeController::class, 'indexPublic']);
    Route::get('/resource-types', [ResourceTypeController::class, 'indexPublic']);

    // Recherche
    Route::get('/search', [ResourceController::class, 'search']);

    // Statistiques publiques
    Route::get('/statistics', [StatisticsController::class, 'publicStats']);
});

// ==========================================
// ROUTES AUTHENTIFIÉES (Utilisateurs connectés)
// ==========================================

Route::middleware(['auth:sanctum', 'verified', 'throttle.auth'])->group(function () {

    // Informations utilisateur - Compatible Mobile (utilise le contrôleur existant)
    Route::get('/user', [MeController::class, 'show']);

    // ==========================================
    // RESSOURCES - Compatible Mobile
    // ==========================================
    Route::apiResource('resources', ResourceController::class)->except(['index']);

    // Routes spécifiques ressources
    Route::prefix('resources')->group(function () {
        // Mes ressources
        Route::get('/my', [ResourceController::class, 'myResources']);
        Route::get('/drafts', [ResourceController::class, 'myDrafts']);
        Route::get('/published', [ResourceController::class, 'myPublished']);

        // Actions sur les ressources
        Route::post('/{resource}/view', [ResourceController::class, 'incrementView']);
        Route::post('/{resource}/download', [ResourceController::class, 'incrementDownload']);
        Route::get('/{resource}/file', [ResourceController::class, 'downloadFile']);

        // Soumission pour validation
        Route::post('/{resource}/submit', [ResourceController::class, 'submitForValidation'])
            ->middleware('can.edit.resource');
    });

    // ==========================================
    // FAVORIS - Compatible Mobile
    // ==========================================
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/resources/{resource}', [FavoriteController::class, 'toggle']);
        Route::delete('/resources/{resource}', [FavoriteController::class, 'remove']);
    });

    // ==========================================
    // PROGRESSION - Compatible Mobile
    // ==========================================
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

    // ==========================================
    // COMMENTAIRES - Compatible Mobile
    // ==========================================
    Route::prefix('comments')->group(function () {
        Route::get('/resources/{resource}', [CommentController::class, 'index']);
        Route::post('/resources/{resource}', [CommentController::class, 'store']);
        Route::put('/{comment}', [CommentController::class, 'update']);
        Route::delete('/{comment}', [CommentController::class, 'destroy']);
        Route::post('/{comment}/reply', [CommentController::class, 'reply']);
        Route::post('/{comment}/like', [CommentController::class, 'toggleLike']);
    });

    // ==========================================
    // ACTIVITÉS COLLABORATIVES - Compatible Mobile
    // ==========================================
    Route::apiResource('activities', ActivityController::class);

    Route::prefix('activities')->group(function () {
        // Mes activités
        Route::get('/my/created', [ActivityController::class, 'myCreated']);
        Route::get('/my/participating', [ActivityController::class, 'myParticipating']);
        Route::get('/my/invitations', [ActivityController::class, 'myInvitations']);

        // Actions sur les activités
        Route::post('/{activity}/join', [ActivityController::class, 'join']);
        Route::post('/{activity}/leave', [ActivityController::class, 'leave']);
        Route::post('/{activity}/start', [ActivityController::class, 'start']);
        Route::post('/{activity}/complete', [ActivityController::class, 'complete']);
        Route::post('/{activity}/cancel', [ActivityController::class, 'cancel']);

        // Gestion des participants
        Route::post('/{activity}/invite', [ActivityParticipantController::class, 'invite']);
        Route::post('/{activity}/accept', [ActivityParticipantController::class, 'accept']);
        Route::post('/{activity}/decline', [ActivityParticipantController::class, 'decline']);
        Route::get('/{activity}/participants', [ActivityParticipantController::class, 'index']);
        Route::put('/{activity}/participants/{participant}', [ActivityParticipantController::class, 'update']);

        // Messages dans les activités
        Route::get('/{activity}/messages', [ActivityMessageController::class, 'index']);
        Route::post('/{activity}/messages', [ActivityMessageController::class, 'store']);
        Route::put('/messages/{message}', [ActivityMessageController::class, 'update']);
        Route::delete('/messages/{message}', [ActivityMessageController::class, 'destroy']);
        Route::post('/messages/{message}/pin', [ActivityMessageController::class, 'pin']);
        Route::post('/messages/{message}/react', [ActivityMessageController::class, 'react']);

        // Messages privés
        Route::post('/{activity}/private-messages', [ActivityMessageController::class, 'storePrivate']);
        Route::get('/{activity}/private-messages', [ActivityMessageController::class, 'indexPrivate']);
    });

    // ==========================================
    // PROFIL UTILISATEUR - Compatible Mobile
    // ==========================================
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::put('/', [UserController::class, 'updateProfile']);
        Route::put('/password', [UserController::class, 'updatePassword']);
        Route::delete('/', [UserController::class, 'deleteAccount']);

        // Statistiques personnelles
        Route::get('/stats', [UserController::class, 'personalStats']);
    });

    // ==========================================
    // MODÉRATION (Modérateurs et Admins)
    // ==========================================
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

    // ==========================================
    // ADMINISTRATION (Admins et Super-Admins)
    // ==========================================
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

    // ==========================================
    // SUPER-ADMINISTRATION (Super-Admins uniquement)
    // ==========================================
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

    // ==========================================
    // STATISTIQUES GÉNÉRALES (Tous utilisateurs connectés)
    // ==========================================
    Route::prefix('statistics')->group(function () {
        Route::get('/categories', [StatisticsController::class, 'categoryStats']);
        Route::get('/popular-resources', [StatisticsController::class, 'popularResources']);
    });
});

// ==========================================
// ROUTES DE FALLBACK
// ==========================================

// Route pour gérer les 404 API
Route::fallback(function () {
    return response()->json([
        'message' => 'Route not found',
        'status' => 404
    ], 404);
});
