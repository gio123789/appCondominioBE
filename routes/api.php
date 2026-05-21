<?php

use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/chat/messages', [ChatMessageController::class, 'index']);
Route::post('/chat/messages', [ChatMessageController::class, 'store']);

Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
Route::post('/notifications', [NotificationController::class, 'store']);
Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
