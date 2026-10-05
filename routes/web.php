<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Web\LoginController;
use Modules\Auth\Http\Controllers\Web\SocialLoginController;

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/auth/social/{driver}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/social/{driver}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');
