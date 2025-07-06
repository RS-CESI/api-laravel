<?php

use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

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
