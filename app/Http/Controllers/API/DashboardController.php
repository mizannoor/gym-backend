<?php
// app/Http/Controllers/API/DashboardController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

class DashboardController extends Controller
{
    /**
     * Return user status and a Base64-encoded QR code
     */
    public function index()
    {
        $user   = Auth::user();
        $status = $user->status->name;
        $payload= "user:{$user->id};status:{$status}";

        // Configure a 200×200 SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer   = new Writer($renderer);

        // writeString() returns raw SVG XML
        $svgString = $writer->writeString($payload);
        $qrBase64  = base64_encode($svgString);

        return response()->json([
            'status' => $status,
            'qr'     => $qrBase64,
        ], 200);
    }
}
