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

    /**
     * GET /membership/current
     *
     * Returns the current authenticated user's membership, or `null` if none.
     */
    public function currentMembership(Request $request) {
        // 1) Get the currently authenticated user
        $user = Auth::user();
        if (!$user) {
            // If no user is authenticated, return 401
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        // 2) Find the membership record for this user
        //    We assume each user has at most one membership row.
        $membership = Membership::where('user_id', $user->id)->first();

        // 3) Return it as JSON. If none found, `membership` will be null.
        return response()->json([
            'membership' => $membership,
        ], 200);
    }

    /**
     * POST /api/subscription/cancel
     *
     * Sets the current user's membership status to “inactive,” effectively cancelling it.
     * Requires the user to be authenticated.
     */
    public function cancel(Request $request) {
        // 1) Ensure user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // 2) Find the membership belonging to this user
        $membership = Membership::where('user_id', $user->id)->where('status_id', 1)->first();

        if (!$membership) {
            return response()->json([
                'message' => 'No active membership found.'
            ], 404);
        }

        // 3) Look up the "inactive" status ID
        $inactiveStatus = Status::where('name', 'inactive')->first();
        if (!$inactiveStatus) {
            return response()->json([
                'message' => 'Inactive status not configured in database.'
            ], 500);
        }

        // 4) Update the membership record
        $membership->status_id = $inactiveStatus->id;
        // Optionally, if you want to set expires_at to today:
        // $membership->expires_at = now()->toDateString();
        $membership->save();

        // 5) Return success JSON (you can include the updated membership if you like)
        return response()->json([
            'message'    => 'Membership cancelled successfully.',
            'membership' => $membership->load('status') // reload in case you want nested status
        ], 200);
    }
}
