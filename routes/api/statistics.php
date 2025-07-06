<?php

use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('statistics')->group(function () {
    Route::get('/categories', [StatisticsController::class, 'categoryStats']);
    Route::get('/popular-resources', [StatisticsController::class, 'popularResources']);
});
