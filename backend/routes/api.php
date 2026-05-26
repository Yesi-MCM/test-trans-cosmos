<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function () {
    // Task Management
    Route::get('tasks', [\App\Http\Controllers\TaskController::class, 'index']);
    Route::post('tasks', [\App\Http\Controllers\TaskController::class, 'store']);
    Route::get('tasks/{id}', [\App\Http\Controllers\TaskController::class, 'show']);
    Route::put('tasks/{id}', [\App\Http\Controllers\TaskController::class, 'update']);
    Route::delete('tasks/{id}', [\App\Http\Controllers\TaskController::class, 'destroy']);
    Route::post('tasks/bulk-status', [\App\Http\Controllers\TaskController::class, 'bulkStatus']);

    // Comments
    Route::get('tasks/{id}/comments', [\App\Http\Controllers\TaskController::class, 'comments']);
    Route::post('tasks/{id}/comments', [\App\Http\Controllers\TaskController::class, 'storeComment']);

    // File Upload / Attachments
    Route::post('tasks/{id}/attachments', [\App\Http\Controllers\AttachmentController::class, 'upload']);
    Route::post('attachments/chunk', [\App\Http\Controllers\AttachmentController::class, 'uploadChunk']);
    Route::get('attachments/{id}/download', [\App\Http\Controllers\AttachmentController::class, 'download']);
    Route::delete('attachments/{id}', [\App\Http\Controllers\AttachmentController::class, 'destroy']);
});
