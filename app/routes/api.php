<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerInteractionController;
use App\Http\Controllers\Api\V1\FarmMemberController;
use App\Http\Controllers\Api\V1\FarmSubscriptionController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HerdController;
use App\Http\Controllers\Api\V1\CrmMemberController;
use App\Http\Controllers\Api\V1\CrmNotificationController;
use App\Http\Controllers\Api\V1\CrmReportController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\FarmOverviewController;
use App\Http\Controllers\Api\V1\FarmNotificationController;
use App\Http\Controllers\Api\V1\MpesaPaymentController;
use App\Http\Controllers\Api\V1\GrowthController;
use App\Http\Controllers\Api\V1\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('farms/{farm}/overview', [FarmOverviewController::class, 'show']);
        Route::get('farms/{farm}/feed', [FeedController::class, 'index']);
        Route::post('farms/{farm}/feed/stock', [FeedController::class, 'storeStock']);
        Route::post('farms/{farm}/feed/usage', [FeedController::class, 'storeUsage']);
        Route::get('farms/{farm}/notifications', [FarmNotificationController::class, 'index']);
        Route::patch('farms/{farm}/notifications/{notification}/read', [FarmNotificationController::class, 'markAsRead']);

        Route::apiResource('crm/customers', CustomerController::class);
        Route::get('crm/customers/{customer}/interactions', [CustomerInteractionController::class, 'index']);
        Route::post('crm/customers/{customer}/interactions', [CustomerInteractionController::class, 'store']);
        Route::get('crm/members', [CrmMemberController::class, 'index']);
        Route::post('crm/members', [CrmMemberController::class, 'store']);
        Route::patch('crm/members/{user}', [CrmMemberController::class, 'update']);
        Route::delete('crm/members/{user}', [CrmMemberController::class, 'destroy']);
        Route::get('crm/notifications', [CrmNotificationController::class, 'index']);
        Route::post('crm/notifications', [CrmNotificationController::class, 'store']);
        Route::get('crm/reports/overview', [CrmReportController::class, 'overview']);
        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);
        Route::post('subscription-plans', [SubscriptionPlanController::class, 'store']);
        Route::patch('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'update']);
        Route::delete('subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'destroy']);

        Route::get('farms/{farm}/members', [FarmMemberController::class, 'index']);
        Route::patch('farms/{farm}/members/{user}', [FarmMemberController::class, 'update']);
        Route::patch('farms/{farm}/subscription', [FarmSubscriptionController::class, 'update']);
        Route::post('farms/{farm}/subscription/payment', [MpesaPaymentController::class, 'store']);
        Route::get('farms/{farm}/animals', [HerdController::class, 'index']);
        Route::post('farms/{farm}/animals', [HerdController::class, 'store']);
        Route::get('farms/{farm}/animals/{animal}', [HerdController::class, 'show']);
        Route::patch('farms/{farm}/animals/{animal}', [HerdController::class, 'update']);
        Route::delete('farms/{farm}/animals/{animal}', [HerdController::class, 'destroy']);

        Route::get('farms/{farm}/health-records', [HealthController::class, 'index']);
        Route::post('farms/{farm}/health-records', [HealthController::class, 'store']);
        Route::get('farms/{farm}/health-records/{healthRecord}', [HealthController::class, 'show']);
        Route::patch('farms/{farm}/health-records/{healthRecord}', [HealthController::class, 'update']);
        Route::delete('farms/{farm}/health-records/{healthRecord}', [HealthController::class, 'destroy']);
        Route::get('farms/{farm}/health-alerts', [HealthController::class, 'getAlerts']);
        Route::get('farms/{farm}/health-analytics', [HealthController::class, 'getAnalytics']);

        Route::get('farms/{farm}/growth-records', [GrowthController::class, 'index']);
        Route::post('farms/{farm}/growth-records', [GrowthController::class, 'store']);
        Route::get('farms/{farm}/growth-records/{growthRecord}', [GrowthController::class, 'show']);
        Route::patch('farms/{farm}/growth-records/{growthRecord}', [GrowthController::class, 'update']);
        Route::delete('farms/{farm}/growth-records/{growthRecord}', [GrowthController::class, 'destroy']);
        Route::get('farms/{farm}/growth-overview', [GrowthController::class, 'getOverview']);
        Route::get('farms/{farm}/growth-analytics', [GrowthController::class, 'getAnalytics']);

        // Inventory Routes
        Route::get('farms/{farm}/inventory/items', [InventoryController::class, 'index']);
        Route::post('farms/{farm}/inventory/items', [InventoryController::class, 'store']);
        Route::get('farms/{farm}/inventory/items/{item}', [InventoryController::class, 'show']);
        Route::patch('farms/{farm}/inventory/items/{item}', [InventoryController::class, 'update']);
        Route::delete('farms/{farm}/inventory/items/{item}', [InventoryController::class, 'destroy']);
        Route::post('farms/{farm}/inventory/items/{item}/movements', [InventoryController::class, 'recordMovement']);
        Route::get('farms/{farm}/inventory/items/{item}/movements', [InventoryController::class, 'getMovements']);
        Route::get('farms/{farm}/inventory/alerts', [InventoryController::class, 'getAlerts']);
        Route::get('farms/{farm}/inventory/by-category', [InventoryController::class, 'getByCategory']);
    });

    Route::post('payments/mpesa/callback', [MpesaPaymentController::class, 'callback']);
});
