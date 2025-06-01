<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller {

    /**
     * Return the authenticated user's basic info (id, name, email).
     */
    public function show(Request $request) {
        // Grab the currently authenticated user
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Only return id, name, email to the client
        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ], 200);
    }

    /**
     * Delete the authenticated user and cascade‐remove related records.
     */
    public function destroy(Request $request) {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 1) Delete all related memberships
        //    (Assumes User has a memberships() relationship)
        if (method_exists($user, 'memberships')) {
            $user->memberships()->delete();
        }

        // 2) Delete all related payments
        //    (Assumes User has a payments() relationship)
        if (method_exists($user, 'payments')) {
            $user->payments()->delete();
        }

        // 3) Finally, delete the user
        $user->delete();

        return response()->json([
            'message' => 'Account and all related data deleted successfully.'
        ], 200);
    }
}
