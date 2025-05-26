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
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller {
    /** @var SquareClient */
    protected $square;

    public function __construct() {
        $this->square = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => config('services.square.environment', 'sandbox'),
        ]);
    }

    public function index(): JsonResponse {
        $raw = Auth::user()
            ->payments()
            ->with('status')    // eager-load the status
            ->orderBy('created_at', 'desc')
            ->get();

        // map to only the fields your client needs,
        // extracting the status name
        $payments = $raw->map(function ($p) {
            return [
                'id'         => $p->id,
                'amount'     => $p->amount,
                'status'     => $p->status->name,
                'created_at' => $p->created_at,
            ];
        });

        return response()->json($payments->values(), 200);
    }

    /**
     * Create a Square payment and record it locally
     */
    public function createPayment(Request $request): JsonResponse {
        $request->validate([
            'membership_id' => 'required|exists:memberships,id',
            'amount'        => 'required|numeric|min:0.01',
            'nonce'         => 'required|string',
        ]);

        // 1) Initialize Square client
        $client = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => config('services.square.environment'),
        ]);
        $paymentsApi = $client->getPaymentsApi();

        // 2) Build the Money object
        $money = new Money();
        $money->setAmount(intval($request->amount * 100)); // cents
        $money->setCurrency($request->currency);

        // 3) Build and send CreatePaymentRequest
        $idempotencyKey = uniqid();
        $createReq = new CreatePaymentRequest(
            $request->nonce,
            $idempotencyKey,
            $money
        );

        $response = $paymentsApi->createPayment($createReq);
        if ($response->isSuccess()) {
            $squarePayment = $response->getResult()->getPayment();

            // 4) Record in local DB as 'pending'
            $pendingStatusId = Status::where('name', 'pending')->value('id');
            $payment = Payment::create([
                'user_id'           => Auth::id(),
                'amount'            => $request->amount,
                'status_id'         => $pendingStatusId,
                'square_payment_id' => $squarePayment->getId(),
                'created_by'        => Auth::id(),
                'updated_by'        => Auth::id(),
            ]);

            return response()->json([
                'payment_id' => $payment->id,
                'square_id'  => $squarePayment->getId(),
                'status'     => 'pending',
                'details'    => $squarePayment,
            ], 201);
        } else {
            $errors = $response->getErrors();
            return response()->json(['errors' => $errors], 422);
        }

        /* $membership = Membership::findOrFail($request->membership_id);

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
        } */
    }

    /**
     * Square webhook to update payment status
     */
    /* public function webhook(Request $request) {
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
    } */

    /**
     * Handle Square webhooks for payment updates.
     */
    public function webhookHandler(Request $request): JsonResponse {
        $event = $request->all();
        $paymentData = data_get($event, 'data.object.payment');
        if (! $paymentData) {
            return response()->json(['message' => 'No payment data'], 400);
        }

        $squareId = $paymentData['id'];
        $squareStatus = $paymentData['status']; // e.g. COMPLETED, PENDING, FAILED
        $map = [
            'COMPLETED' => 'success',
            'PENDING'   => 'pending',
        ];
        $statusName = $map[$squareStatus] ?? 'failed';
        $statusId   = Status::where('name', $statusName)->value('id');

        // Update our record
        $payment = Payment::where('square_payment_id', $squareId)->first();
        if ($payment) {
            $payment->update([
                'status_id'  => $statusId,
                'updated_by' => $payment->user_id,
            ]);
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }
}
