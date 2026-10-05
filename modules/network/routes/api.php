<?php

use Illuminate\Support\Facades\Route;
use Modules\Network\Enums\WebsitePermission;
use Modules\Network\Http\Controllers\DashboardController;
use Modules\Network\Http\Controllers\MyWebsiteController;
use Modules\Network\Http\Controllers\NetworkConfigController;
use Modules\Network\Http\Controllers\RoleController;
use Modules\Network\Http\Controllers\UserController;
use Modules\Network\Http\Controllers\WebsiteController;
use Modules\Network\Http\Middleware\EnsureSuperAdmin;

/*
|--------------------------------------------------------------------------
| Network API Routes
|--------------------------------------------------------------------------
|
| Network-wide management endpoints. They are not scoped to a single website
| and are restricted to super admins.
|
*/

Route::prefix('v1/network')->name('network.')->group(function () {
    Route::get('config', NetworkConfigController::class)->name('config');
});

/*
|--------------------------------------------------------------------------
| "My websites" API
|--------------------------------------------------------------------------
|
| Websites the authenticated user is a member of, used by the admin SPA and
| picker. Scoped to the user rather than the whole network.
|
*/

Route::middleware('auth:api')->prefix('v1/admin/websites')->group(function () {
    Route::get('/', [MyWebsiteController::class, 'index']);
    Route::post('/', [MyWebsiteController::class, 'store'])
        ->middleware('permission:'.WebsitePermission::Create->value);
    Route::get('{website}', [MyWebsiteController::class, 'show'])
        ->middleware('permission:'.WebsitePermission::View->value);
    Route::match(['put', 'patch'], '{website}', [MyWebsiteController::class, 'update'])
        ->middleware('permission:'.WebsitePermission::Update->value);
    Route::delete('{website}', [MyWebsiteController::class, 'destroy'])
        ->middleware('permission:'.WebsitePermission::Delete->value);
});

Route::middleware(['auth:api', EnsureSuperAdmin::class])
    ->prefix('v1/network')
    ->name('network.')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::apiResource('websites', WebsiteController::class);

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::post('{user}/restore', [UserController::class, 'restore'])
                ->withTrashed()
                ->name('restore');
            Route::put('{user}/password', [UserController::class, 'resetPassword'])->name('password');
            Route::post('{user}/resend-verification', [UserController::class, 'resendVerification'])
                ->name('resend-verification');
            Route::get('{user}', [UserController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '{user}', [UserController::class, 'update'])->name('update');
            Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    });
