<?php

// app/Http/Controllers/API/SubscriptionController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller {
    public function subscribe(Request $request) {
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
    }
}
