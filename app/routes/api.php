<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerInteractionController;
use App\Http\Controllers\Api\V1\FarmMemberController;
use App\Http\Controllers\Api\V1\FarmSubscriptionController;
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

        Route::get('farms/{farm}/members', [FarmMemberController::class, 'index']);
        Route::patch('farms/{farm}/members/{user}', [FarmMemberController::class, 'update']);
        Route::patch('farms/{farm}/subscription', [FarmSubscriptionController::class, 'update']);
    });
});

