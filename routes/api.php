<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\PaymentController;
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

Route::middleware('auth:api')->get('/payment/status/{id}', [PaymentController::class, 'checkStatus']);

Route::prefix('auth')->group(function () {
    Route::get('google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::post('google/token',   [AuthController::class, 'googleToken']);
    // later: search routes
});

Route::get('/user', function (Request $request) {
    return $request->user();
});

// routes/api.php
// All other API routes require a valid JWT
Route::middleware('auth:api')->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Membership plans
    Route::get('plans',       [PlanController::class, 'index']);
    Route::get('plans/{id}',  [PlanController::class, 'show']);

    // Subscribe to a plan
    Route::post('subscribe',  [SubscriptionController::class, 'subscribe']);

    // Payments
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payment/create',  [PaymentController::class, 'createPayment']);
    Route::post('payment/webhook', [PaymentController::class, 'webhookHandler']);
    Route::post('payment/checkout-link', [PaymentController::class, 'createCheckoutLink']);
    // later: search routes
    // (Add your search routes here when ready)
});

Route::prefix('auth')->group(function () {
    Route::get('google/redirect',  [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback',  [AuthController::class, 'handleGoogleCallback']);
    Route::post('google/token',    [AuthController::class, 'googleToken']);     // ← corrected
    Route::post('logout',          [AuthController::class, 'logout'])
        ->middleware('auth:api');
});
