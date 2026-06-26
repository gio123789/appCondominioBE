<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerificationByEmail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPasswordWithCode']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
    });
});

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('/chat/messages', [ChatMessageController::class, 'index'])->middleware('ability:chat:read,*');
    Route::post('/chat/messages', [ChatMessageController::class, 'store'])->middleware('ability:chat:write,*');

    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('ability:notifications:read,*');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->middleware('ability:notifications:read,*');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('ability:notifications:read,*');
    Route::post('/notifications', [NotificationController::class, 'store'])->middleware('role:admin');
});
