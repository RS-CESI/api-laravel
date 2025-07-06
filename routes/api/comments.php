<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::prefix('comments')->group(function () {
    Route::get('/resources/{resource}', [CommentController::class, 'index']);
    Route::post('/resources/{resource}', [CommentController::class, 'store']);
    Route::put('/{comment}', [CommentController::class, 'update']);
    Route::delete('/{comment}', [CommentController::class, 'destroy']);
    Route::post('/{comment}/reply', [CommentController::class, 'reply']);
    Route::post('/{comment}/like', [CommentController::class, 'toggleLike']);
});
