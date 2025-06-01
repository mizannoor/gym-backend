<?php
// app/Http/Controllers/API/DashboardController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
// PNG backend instead of SVG
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use Endroid\QrCode\Builder\Builder;
use App\Models\Membership;

class DashboardController extends Controller {
    /**
     * Return user status and a Base64-encoded QR code
     */
    public function home(Request $request) {
        return response()->json([
            'status' => json_encode($request),
        ], 200);
    }

    public function index() {
        $user   = Auth::user();
        $status = $user->status->name;
        $membership = Membership::select("id", "user_id", "plan_id", "status_id", "starts_at", "expires_at")
            ->with(['status:id,name,description'])
            ->where('user_id', $user->id)
            ->where('status_id', 1)
            ->first();

        $payload = json_encode([
            'user_id' => $user->id,
            'status'  => $status,
            'membership'  => isset($membership) ? $membership : null,
        ]);

        // Build a 200×200 PNG via GD
        $result = Builder::create()->data($payload)->size(200)->margin(0)->build();

        $pngData  = $result->getString();           // raw PNG bytes
        // Only encode the QR if the membership’s status ID is 1 (“active”).
        $qrBase64 = '';
        $statusId = optional(optional($membership)->status)->id;

        if ($statusId === 1) {
            $pngData  = $result->getString();    // raw PNG bytes
            $qrBase64 = base64_encode($pngData);
        }

        // Return the JSON payload. If $membership is null, it will simply be null.
        return response()->json([
            'status'     => $status,
            'qr'         => $qrBase64,
            'membership' => $membership,
        ], 200);
    }
}
