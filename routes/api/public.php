<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\RelationTypeController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ResourceTypeController;
use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;


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
