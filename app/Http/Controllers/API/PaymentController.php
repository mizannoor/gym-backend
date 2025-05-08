<?php
// app/Http/Controllers/API/PaymentController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Status;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Exceptions\ApiException;

class PaymentController extends Controller {
    /** @var SquareClient */
    protected $square;

    public function __construct() {
        $this->square = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => config('services.square.environment', 'sandbox'),
        ]);
    }

    /**
     * Create a Square payment and record it locally
     */
    public function createPayment(Request $request) {
        $request->validate([
            'membership_id' => 'required|exists:memberships,id',
            'amount'        => 'required|numeric|min:0.01',
            'nonce'         => 'required|string',
        ]);

        $membership = Membership::findOrFail($request->membership_id);

        // Build the Money object
        $money = new Money();
        $money->setAmount(intval($request->amount * 100));  // in cents
        $money->setCurrency('USD');

        // Build the CreatePaymentRequest
        $body = new CreatePaymentRequest(
            $request->nonce,
            uniqid('', true),
            $money
        );

        try {
            $paymentsApi  = $this->square->getPaymentsApi();
            $apiResponse  = $paymentsApi->createPayment($body);

            if ($apiResponse->isSuccess()) {
                $result  = $apiResponse->getResult()->getPayment();

                $payment = Payment::create([
                    'user_id'             => $membership->user_id,
                    'membership_id'       => $membership->id,
                    'provider_payment_id' => $result->getId(),
                    'amount'              => $request->amount,
                    'status_id'           => Status::where('name', 'success')->first()->id,
                    'paid_at'             => now(),
                    'created_by'          => $membership->user_id,
                    'updated_by'          => $membership->user_id,
                ]);

                return response()->json($payment, 201);
            }

            // API-level errors
            return response()->json([
                'errors' => $apiResponse->getErrors()
            ], 500);
        } catch (ApiException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Square webhook to update payment status
     */
    public function webhook(Request $request) {
        $data    = $request->input('data.object.payment', []);
        $payment = Payment::where('provider_payment_id', $data['id'] ?? '')->first();

        if (! $payment) {
            return response()->json([], 404);
        }

        // Map Square status to our status names
        $rawStatus = $data['status'] ?? '';
        switch ($rawStatus) {
            case 'COMPLETED':
                $statusName = 'success';
                break;
            case 'PENDING':
                $statusName = 'pending';
                break;
            default:
                $statusName = 'failed';
                break;
        }

        $status = Status::where('name', $statusName)->first();
        if ($status) {
            $payment->update([
                'status_id'  => $status->id,
                'updated_by' => $payment->user_id,
            ]);
        }

        return response()->json([], 200);
    }
}
