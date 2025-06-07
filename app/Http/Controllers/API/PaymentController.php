<?php
// app/Http/Controllers/API/PaymentController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Status;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Illuminate\Support\Str;
use Square\Models\Money;
use Square\Exceptions\ApiException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Square\Models\CreateCheckoutRequest;
use Square\Models\CreateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Illuminate\Support\Facades\Log;

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
            'nonce'         => 'required|string',
            'amount'        => 'required|numeric|min:0.01',
            'currency'      => 'required|string|size:3',
            // 'membership_id' => 'required|exists:memberships,id',  <-- remove or make nullable
        ]);

        // Initialize Square client
        $client = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => config('services.square.environment'),
        ]);
        $paymentsApi = $client->getPaymentsApi();

        // Build the Money object
        $money = new Money();
        $money->setAmount(intval($request->amount * 100)); // in cents
        $money->setCurrency($request->currency);

        // Create the request with just sourceId + idempotencyKey
        $idempotencyKey = uniqid();
        $createReq = new CreatePaymentRequest(
            $request->nonce,       // sourceId (string)
            $idempotencyKey        // idempotencyKey (string)
        );

        // Attach the money via setter
        $createReq->setAmountMoney($money);

        // Send it off
        $response = $paymentsApi->createPayment($createReq);

        if ($response->isSuccess()) {
            $squarePayment = $response->getResult()->getPayment();

            // Record in local DB as 'pending'
            $pendingStatusId = Status::where('name', 'pending')->value('id');
            $membership = Auth::user()->membership; // or ->memberships()->active()->first()

            if (! $membership) {
                return response()->json(['error' => 'No active membership'], 422);
            }

            $payment = Payment::create([
                'user_id'              => Auth::id(),
                'membership_id'        => $membership->id,
                'amount'               => $request->amount,
                'status_id'            => $pendingStatusId,
                'provider_payment_id'  => $squarePayment->getId(),  // ← add this
                'created_by'           => Auth::id(),
                'updated_by'           => Auth::id(),
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
    }

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

    public function handleReturn(Request $request) {
        $paymentId = $request->query('payment_id');
        $status = $request->query('status', 'success');

        // Optionally update payment record
        if ($paymentId) {
            $payment = Payment::find($paymentId);
            $user = Auth::user() ? Auth::user() : User::find($payment->user_id);
            
            if ($payment) {

                $payment->update([
                    'status_id' => Status::where('name', 'success')->value('id'),
                    'paid_at' => now(),
                    'updated_by' => $payment->user_id,
                ]);

                // Get the "pending" status id
                $statusId = Status::where('name', 'pending')->value('id');
                // Find user’s membership
                $membership = Membership::where('user_id', $user->id)->where('status_id', $statusId)->first();

                // Fetch the plan
                $plan = MembershipPlan::findOrFail($membership->plan_id);

                // Compute start and expiration
                $startsAt  = now();
                $expiresAt = now()->addMonths($plan->duration_months);

                // Get the "active" status id
                $statusId = Status::where('name', 'active')->value('id');

                // Update the user’s membership
                $membership->starts_at  = $startsAt;
                $membership->expires_at = $expiresAt;
                $membership->status_id  = $statusId;
                $membership->updated_by = $user->id;
                $membership->save();
            }
        }

        // ✅ Redirect to app using deep link
        $callbackURL = "gymmembership://callback?payment_id={$paymentId}&status={$status}";
        return redirect()->to($callbackURL);
    }

    public function checkStatus($id) {
        $payment = Payment::findOrFail($id);

        return response()->json([
            'status' => $payment->status->name, // assuming relation exists
        ]);
    }


    public function createCheckoutLink(Request $request) {
        $request->validate([
            'membership_id' => 'required|exists:memberships,id',
        ]);

        $user = Auth::user();
        $membership = Membership::with('plan')->findOrFail($request->membership_id);
        $amount = $membership->plan->price;
        $amountCents = (int) round($amount * 100);

        // Initialize Square client
        $client = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => config('services.square.environment'),
        ]);

        // Create line item for the order
        $money = new Money();
        $money->setAmount($amountCents);
        $money->setCurrency('USD');

        $lineItem = new OrderLineItem('1');
        $lineItem->setName($membership->plan->name);
        $lineItem->setBasePriceMoney($money);

        $order = new Order(config('services.square.location_id'));
        $order->setLineItems([$lineItem]);

        $orderRequest = new CreateOrderRequest();
        $orderRequest->setIdempotencyKey((string) Str::uuid());
        $orderRequest->setOrder($order);

        $checkoutRequest = new CreateCheckoutRequest(
            (string) Str::uuid(),
            $orderRequest
        );

        // Save payment first to get its ID
        $statusId = Status::where('name', 'pending')->value('id');
        $payment = Payment::create([
            'user_id'             => $user->id,
            'membership_id'       => $membership->id,
            'amount'              => $amount,
            'provider_payment_id' => null,
            'status_id'           => $statusId,
            'created_by'          => $user->id,
            'updated_by'          => $user->id,
        ]);

        // Set redirect URL with payment_id
        $redirectUrl = config('services.square.redirect_url') . '?payment_id=' . $payment->id . '&status=pending';
        $checkoutRequest->setRedirectUrl($redirectUrl);

        $checkoutApi = $client->getCheckoutApi();
        $response = $checkoutApi->createCheckout(
            config('services.square.location_id'),
            $checkoutRequest
        );

        if (! $response->isSuccess()) {
            return response()->json([
                'errors' => $response->getErrors()
            ], 422);
        }

        $checkout = $response->getResult()->getCheckout();
        $squareCheckoutId = $checkout->getId();
        $checkoutUrl = $checkout->getCheckoutPageUrl();

        // Update payment with Square checkout ID
        $payment->update([
            'provider_payment_id' => $squareCheckoutId,
        ]);

        return response()->json([
            'payment_id'   => $payment->id,
            'checkout_url' => $checkoutUrl
        ]);
    }
}
