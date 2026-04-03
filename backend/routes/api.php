<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLoadController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::prefix('audit')->group(function () {
        Route::get('/loads', [AuditLoadController::class, 'index']);
        Route::get('/loads/{load}', [AuditLoadController::class, 'show']);
        Route::put('/loads/{load}', [AuditLoadController::class, 'update']);
        Route::post('/loads/{load}/approve', [AuditLoadController::class, 'approve']);
    });
});
