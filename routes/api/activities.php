<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityMessageController;
use App\Http\Controllers\ActivityParticipantController;
use Illuminate\Support\Facades\Route;

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
