<?php
// app/Http/Controllers/API/AuthController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Status;
use App\Models\Role;
use Google_Client;

class AuthController extends Controller {
    /**
     * Redirect the user to Google for authentication.
     */
    public function redirectToGoogle() {
        // No stateless() here—just call redirect()
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google, create or update the user,
     * assign default role, and issue a JWT.
     */
    public function handleGoogleCallback() {
        try {
            // No stateless() here either
            $socialUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Google authentication failed'
            ], 500);
        }

        // Create or update local user
        $user = User::updateOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name'              => $socialUser->getName(),
                'email_verified_at' => now(),
                'status_id'         => Status::where('name', 'active')->value('id'),
                'created_by'        => 1,
                'updated_by'        => 1,
            ]
        );

        // Ensure the "Member" role is attached
        $memberRole = Role::where('name', 'Member')->first();
        if (! $user->roles->contains($memberRole->id)) {
            $user->roles()->attach($memberRole->id);
        }

        // Issue JWT
        $token      = JWTAuth::fromUser($user);
        $ttlMinutes = JWTAuth::factory()->getTTL();

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlMinutes * 60,
        ], 200);
    }

    /**
     * Exchange a Google ID token for a JWT.
     */
    public function googleToken(Request $request) {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        // 1) Verify the ID token
        $client = new Google_Client([
            'client_id' => config('services.google.client_id'),
        ]);
        $payload = $client->verifyIdToken($request->id_token);

        if (! $payload) {
            return response()->json([
                'error' => 'Invalid or expired Google ID token'
            ], 422);
        }

        // 2) Create or update the user
        // In AuthController::handleGoogleCallback or googleToken…

        // Decode the Google payload earlier
        $email = $payload['email'];
        $googleName = $payload['name'] ?? null;

        // Try to find an existing user
        $user = User::where('email', $email)->first();

        if (! $user) {
            // First time: create with a guaranteed name
            $user = User::create([
                'email'             => $email,
                'name'              => $googleName ?: explode('@', $email)[0],
                'email_verified_at' => now(),
                'status_id'         => Status::where('name', 'active')->value('id'),
                'created_by'        => 1,
                'updated_by'        => 1,
            ]);
        } else {
            // Returning user: only update fields that are definitely non-null
            $user->email_verified_at = now();
            $user->status_id         = Status::where('name', 'active')->value('id');
            $user->updated_by        = $user->id;
            $user->save();
        }


        // 3) Ensure the “Member” role
        $memberRole = Role::where('name', 'Member')->first();
        if (! $user->roles->contains($memberRole->id)) {
            $user->roles()->attach($memberRole->id);
        }

        // 4) Issue a JWT
        $token      = JWTAuth::fromUser($user);
        $ttlMinutes = JWTAuth::factory()->getTTL();

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlMinutes * 60,
        ], 200);
    }

    /**
     * Invalidate the token on logout.
     */
    public function logout() {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'message' => 'Logged out'
        ], 200);
    }
}
