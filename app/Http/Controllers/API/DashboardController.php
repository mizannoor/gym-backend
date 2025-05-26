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

class DashboardController extends Controller {
    /**
     * Return user status and a Base64-encoded QR code
     */
    public function home(Request $request) {
        return response()->json([
            'status' => json_encode($request),
        ], 200);
    }
    /* public function index() {
        $user   = Auth::user();
        $status = $user->status->name;
        $payload = "user:{$user->id};status:{$status}";

        // Configure a 200×200 SVG QR code
        $user   = Auth::user();
        $status = $user->status->name;
        $payload = json_encode([
            'user_id' => $user->id,
            'status'  => $status,
        ]);

        // Render a 200×200 PNG
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new ImagickImageBackEnd()          // ← swap this in
        );
        $writer   = new Writer($renderer);
        $pngData  = $writer->writeString($payload);
        $qrBase64 = base64_encode($pngData);

        return response()->json([
            'status' => $status,
            'qr'     => $qrBase64,
        ]);
    } */

    public function index() {
        $user   = Auth::user();
        $status = $user->status->name;
        $payload = json_encode([
            'user_id' => $user->id,
            'status'  => $status,
        ]);

        // Build a 200×200 PNG via GD
        $result = Builder::create()
            ->data($payload)
            ->size(200)
            ->margin(0)
            ->build();

        $pngData  = $result->getString();           // raw PNG bytes
        $qrBase64 = base64_encode($pngData);

        return response()->json([
            'status' => $status,
            'qr'     => $qrBase64,
        ], 200);
    }
}
