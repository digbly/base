<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Enums\LanguagePermission;
use Modules\Admin\Enums\MenuPermission;
use Modules\Admin\Enums\PagePermission;
use Modules\Admin\Enums\ThemePermission;
use Modules\Admin\Enums\WidgetPermission;
use Modules\Admin\Http\Controllers\Admin\CustomizeController;
use Modules\Admin\Http\Controllers\Admin\LanguageController;
use Modules\Admin\Http\Controllers\Admin\MenuController;
use Modules\Admin\Http\Controllers\Admin\NavigationController;
use Modules\Admin\Http\Controllers\Admin\PageController;
use Modules\Admin\Http\Controllers\Admin\PermissionController;
use Modules\Admin\Http\Controllers\Admin\RoleController;
use Modules\Admin\Http\Controllers\Admin\SettingController;
use Modules\Admin\Http\Controllers\Admin\UserController;
use Modules\Admin\Http\Controllers\Admin\WidgetController;
use Modules\Admin\Http\Controllers\AdminController;
use Modules\Auth\Enums\Permission;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('admins', AdminController::class)->names('admin');
});

Route::middleware(['auth:api'])
    ->prefix('v1/admin/menus')
    ->group(function () {
        Route::get('/', [MenuController::class, 'index'])
            ->middleware('permission:'.MenuPermission::View->value);
        Route::post('/', [MenuController::class, 'store'])
            ->middleware('permission:'.MenuPermission::Create->value);
        Route::get('boxes', [MenuController::class, 'boxes'])
            ->middleware('permission:'.MenuPermission::View->value);
        Route::get('boxes/{box}', [MenuController::class, 'boxItems'])
            ->middleware('permission:'.MenuPermission::View->value);
        Route::get('locations', [MenuController::class, 'locations'])
            ->middleware('permission:'.MenuPermission::View->value);
        Route::get('{menu}', [MenuController::class, 'show'])
            ->middleware('permission:'.MenuPermission::View->value);
        Route::match(['put', 'patch'], '{menu}', [MenuController::class, 'update'])
            ->middleware('permission:'.MenuPermission::Update->value);
        Route::delete('{menu}', [MenuController::class, 'destroy'])
            ->middleware('permission:'.MenuPermission::Delete->value);
    });

Route::middleware(['auth:api'])
    ->prefix('v1/admin/widgets')
    ->group(function () {
        Route::get('/', [WidgetController::class, 'index'])
            ->middleware('permission:'.WidgetPermission::View->value);
        Route::put('{sidebar}', [WidgetController::class, 'update'])
            ->middleware('permission:'.WidgetPermission::Update->value);
    });

Route::middleware(['auth:api'])
    ->prefix('v1/admin/navigation')
    ->group(function () {
        Route::get('/', [NavigationController::class, 'index']);
    });

Route::middleware(['auth:api'])
    ->prefix('v1/admin/languages')
    ->group(function () {
        Route::get('/', [LanguageController::class, 'index'])
            ->middleware('permission:'.LanguagePermission::View->value);
        Route::post('/', [LanguageController::class, 'store'])
            ->middleware('permission:'.LanguagePermission::Create->value);
        Route::get('{language}', [LanguageController::class, 'show'])
            ->middleware('permission:'.LanguagePermission::View->value);
        Route::match(['put', 'patch'], '{language}', [LanguageController::class, 'update'])
            ->middleware('permission:'.LanguagePermission::Update->value);
        Route::delete('{language}', [LanguageController::class, 'destroy'])
            ->middleware('permission:'.LanguagePermission::Delete->value);
    });

Route::middleware(['auth:api', 'permission:'.Permission::SettingsManage->value])
    ->prefix('v1/admin/settings')
    ->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::match(['put', 'patch'], '/', [SettingController::class, 'update']);
    });

Route::middleware(['auth:api', 'permission:'.Permission::UsersManage->value])
    ->prefix('v1/admin/users')
    ->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::post('{user}/restore', [UserController::class, 'restore'])->withTrashed();
        Route::put('{user}/password', [UserController::class, 'resetPassword']);
        Route::post('{user}/resend-verification', [UserController::class, 'resendVerification']);
        Route::get('{user}', [UserController::class, 'show']);
        Route::match(['put', 'patch'], '{user}', [UserController::class, 'update']);
        Route::delete('{user}', [UserController::class, 'destroy']);
    });

Route::middleware(['auth:api', 'permission:'.Permission::RolesManage->value])
    ->prefix('v1/admin/roles')
    ->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('{role}', [RoleController::class, 'show']);
        Route::match(['put', 'patch'], '{role}', [RoleController::class, 'update']);
        Route::delete('{role}', [RoleController::class, 'destroy']);
    });

Route::middleware(['auth:api', 'permission:'.Permission::RolesManage->value])
    ->prefix('v1/admin/permissions')
    ->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
    });

Route::middleware(['auth:api'])
    ->prefix('v1/admin/customize')
    ->group(function () {
        Route::get('/', [CustomizeController::class, 'index'])
            ->middleware('permission:'.ThemePermission::View->value);
        Route::post('/', [CustomizeController::class, 'update'])
            ->middleware('permission:'.ThemePermission::Update->value);
        Route::get('page-blocks/{page}', [CustomizeController::class, 'pageBlocks'])
            ->middleware('permission:'.ThemePermission::View->value);
        Route::get('widgets', [CustomizeController::class, 'widgets'])
            ->middleware('permission:'.ThemePermission::View->value);
    });

Route::middleware(['auth:api'])
    ->prefix('v1/admin/pages')
    ->group(function () {
        Route::get('/', [PageController::class, 'index'])
            ->middleware('permission:'.PagePermission::View->value);
        Route::post('/', [PageController::class, 'store'])
            ->middleware('permission:'.PagePermission::Create->value);
        Route::get('{page}', [PageController::class, 'show'])
            ->middleware('permission:'.PagePermission::View->value);
        Route::match(['put', 'patch'], '{page}', [PageController::class, 'update'])
            ->middleware('permission:'.PagePermission::Update->value);
        Route::delete('{page}', [PageController::class, 'destroy'])
            ->middleware('permission:'.PagePermission::Delete->value);
    });
