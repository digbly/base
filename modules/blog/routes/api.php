<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Enums\Permission;
use Modules\Blog\Http\Controllers\Admin\CategoryController;
use Modules\Blog\Http\Controllers\Admin\CommentController;
use Modules\Blog\Http\Controllers\Admin\PostController;
use Modules\Blog\Http\Controllers\Api\CategoryController as ApiCategoryController;
use Modules\Blog\Http\Controllers\Api\CommentController as ApiCommentController;
use Modules\Blog\Http\Controllers\Api\PostController as ApiPostController;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->prefix('admin/blog')->group(function () {
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'index'])->middleware('permission:'.Permission::PostsView->value);
        Route::post('/', [PostController::class, 'store'])->middleware('permission:'.Permission::PostsCreate->value);
        Route::get('{post}', [PostController::class, 'show'])->middleware('permission:'.Permission::PostsView->value);
        Route::match(['put', 'patch'], '{post}', [PostController::class, 'update'])->middleware('permission:'.Permission::PostsUpdate->value);
        Route::delete('{post}', [PostController::class, 'destroy'])->middleware('permission:'.Permission::PostsDelete->value);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:'.Permission::CategoriesView->value);
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:'.Permission::CategoriesCreate->value);
        Route::get('{category}', [CategoryController::class, 'show'])->middleware('permission:'.Permission::CategoriesView->value);
        Route::match(['put', 'patch'], '{category}', [CategoryController::class, 'update'])->middleware('permission:'.Permission::CategoriesUpdate->value);
        Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('permission:'.Permission::CategoriesDelete->value);
    });

    Route::prefix('comments')->group(function () {
        Route::get('/', [CommentController::class, 'index'])->middleware('permission:'.Permission::CommentsView->value);
        Route::get('{comment}', [CommentController::class, 'show'])->middleware('permission:'.Permission::CommentsView->value);
        Route::match(['put', 'patch'], '{comment}', [CommentController::class, 'update'])->middleware('permission:'.Permission::CommentsUpdate->value);
        Route::delete('{comment}', [CommentController::class, 'destroy'])->middleware('permission:'.Permission::CommentsDelete->value);
    });
});

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('blog')->group(function () {
    Route::get('posts', [ApiPostController::class, 'index']);
    Route::get('posts/{slug}', [ApiPostController::class, 'show']);

    Route::get('categories', [ApiCategoryController::class, 'index']);
    Route::get('categories/{slug}', [ApiCategoryController::class, 'show']);

    Route::get('posts/{post}/comments', [ApiCommentController::class, 'index']);
    Route::post('posts/{post}/comments', [ApiCommentController::class, 'store'])
        ->middleware('throttle:20,1');
});
