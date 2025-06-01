<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\PaymentController;

// Public/auth routes
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google/token', [AuthController::class, 'googleToken']);

// All routes below require a valid JWT
Route::middleware('auth:api')->group(function () {
    // 1) GET /api/user → returns { id, name, email }
    Route::get('/user', [UserController::class, 'show']);
    // 2) DELETE /api/user → deletes user + related data
    Route::delete('/user', [UserController::class, 'destroy']);

    // Existing authenticated endpoints:
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Plans
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);

    // Subscriptions
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('/membership/current', [SubscriptionController::class, 'currentMembership']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payment/create', [PaymentController::class, 'createPayment']);
    Route::post('/payment/webhook', [PaymentController::class, 'webhookHandler']);
    Route::post('/payment/checkout-link', [PaymentController::class, 'createCheckoutLink']);
    Route::get('/payment/{id}/status', [PaymentController::class, 'checkStatus']);

    // Logout
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
