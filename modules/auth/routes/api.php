<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\SocialLoginController;

Route::get('auth/social-providers', [SocialLoginController::class, 'providers'])->name('api.auth.social-providers');

Route::prefix('auth/user')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
        Route::post('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('api.verification.verify');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('api.password.reset');
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('profile', [AuthController::class, 'updateProfile']);
        Route::put('change-password', [AuthController::class, 'changePassword']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
