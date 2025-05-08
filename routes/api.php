<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// routes/api.php
Route::group(['middleware' => 'auth:api'], function () {
    Route::get('dashboard',          'API\DashboardController@index');
    Route::get('plans',              'API\PlanController@index');
    Route::get('plans/{id}',         'API\PlanController@show');
    Route::post('subscribe',         'API\SubscriptionController@subscribe');
    Route::post('payment/create',    'API\PaymentController@createPayment');
    Route::post('payment/webhook',   'API\PaymentController@webhook');
    // later: search routes
});
