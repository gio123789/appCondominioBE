<?php

use App\Http\Controllers\ChatMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/chat/messages', [ChatMessageController::class, 'index']);
Route::post('/chat/messages', [ChatMessageController::class, 'store']);
