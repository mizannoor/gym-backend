<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Status;
use App\Http\Controllers\API\PaymentController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment/return', [PaymentController::class, 'handleReturn'])->name('payment.return');


Route::get('/payment/complete', function (Request $r) {
    $pid = $r->query('payment_id');
    if ($pid && $payment = \App\Models\Payment::find($pid)) {
        // mark success
        $status = Status::where('name', 'success')->first();
        $payment->update([
            'status_id'           => $status->id,
            'provider_payment_id' => $r->query('transactionId'), // if you forward it
            'updated_by'          => Auth::id() ?? $payment->user_id,
        ]);
    }
    // then immediately hand off back into your app via deep-link:
    return redirect()->away('gymmembership://payment-complete?status=success');
});

Route::get('/payment/failure', function () {
    // Optional: you could queue up server-side logic here,
    // then immediately hand off back into your app via deep-link:
    return redirect()->away('gymmembership://payment-complete?status=failure');
});

Route::get('/payment/pending', function () {
    // Optional: you could queue up server-side logic here,
    // then immediately hand off back into your app via deep-link:
    return redirect()->away('gymmembership://payment-complete?status=pending');
});

Route::get('/payment/cancelled', function () {
    // Optional: you could queue up server-side logic here,
    // then immediately hand off back into your app via deep-link:
    return redirect()->away('gymmembership://payment-complete?status=cancelled');
});

Route::get('/payment/unknown', function () {
    // Optional: you could queue up server-side logic here,
    // then immediately hand off back into your app via deep-link:
    return redirect()->away('gymmembership://payment-complete?status=unknown');
});
