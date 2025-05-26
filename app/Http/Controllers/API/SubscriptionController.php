<?php

// app/Http/Controllers/API/SubscriptionController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller {
    /* public function subscribes(Request $request) {
        $request->validate([
            'plan_id' => 'required|exists:membership_plans,id'
        ]);

        $user = Auth::user();
        $plan = $request->plan_id;
        $starts = Carbon::now();
        $expires = $starts->copy()->addMonths($request->plan_id);

        $membership = Membership::create([
            'user_id'     => $user->id,
            'plan_id'     => $plan,
            'status_id'   => Status::where('name', 'active')->first()->id,
            'starts_at'   => $starts,
            'expires_at'  => $expires,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        return response()->json($membership, 201);
    } */

    /**
     * Subscribe the current user to a plan.
     *
     * @param  Request  $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function subscribe(Request $request): JsonResponse {
        $data = $request->validate([
            'plan_id' => 'required|exists:membership_plans,id',
        ]);

        $user = Auth::user();

        // Fetch the plan
        $plan = MembershipPlan::findOrFail($data['plan_id']);

        // Compute start and expiration
        $startsAt  = now();
        $expiresAt = now()->addMonths($plan->duration_months);

        // Get the "active" status id
        $statusId = Status::where('name', 'active')->value('id');

        // Create or update the user’s membership
        $membership = Membership::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $plan->id,
                'status_id'          => $statusId,
                'starts_at'          => $startsAt,
                'expires_at'         => $expiresAt,
                'created_by'         => $user->id,
                'updated_by'         => $user->id,
            ]
        );

        return response()->json([
            'message'    => 'Subscribed successfully',
            'membership' => $membership->only([
                'id',
                'plan_id',
                'status_id',
                'starts_at',
                'expires_at'
            ]),
        ], 201);
    }
}
