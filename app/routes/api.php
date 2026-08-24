<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerInteractionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::apiResource('crm/customers', CustomerController::class);
        Route::get('crm/customers/{customer}/interactions', [CustomerInteractionController::class, 'index']);
        Route::post('crm/customers/{customer}/interactions', [CustomerInteractionController::class, 'store']);
    });
});
