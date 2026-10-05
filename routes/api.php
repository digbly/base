<?php

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Enums\MediaPermission;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('ping', fn () => response()->json(['status' => 'ok']));

Route::get('settings', SettingController::class)->name('settings.index');

Route::get('languages', LanguageController::class)->name('languages.index');

Route::get('translations/{locale}', TranslationController::class)->name('translations.show');

Route::middleware(['auth:api'])
    ->prefix('admin/media')
    ->group(function () {
        Route::get('/', [MediaController::class, 'index'])
            ->middleware('permission:'.MediaPermission::MediaView->value);
        Route::post('/', [MediaController::class, 'store'])
            ->middleware('permission:'.MediaPermission::MediaCreate->value);
        Route::get('{media}', [MediaController::class, 'show'])
            ->middleware('permission:'.MediaPermission::MediaView->value);
        Route::match(['put', 'patch'], '{media}', [MediaController::class, 'update'])
            ->middleware('permission:'.MediaPermission::MediaUpdate->value);
        Route::delete('{media}', [MediaController::class, 'destroy'])
            ->middleware('permission:'.MediaPermission::MediaDelete->value);
    });
