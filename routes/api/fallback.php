<?php

use Illuminate\Support\Facades\Route;

Route::fallback(function () {
    return response()->json([
        'message' => 'Route not found',
        'status' => 404
    ], 404);
});
