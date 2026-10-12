<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\ProofOfDeliveryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProofOfDeliveryViewController extends Controller
{
    public function show(
        Request $request,
        Delivery $delivery,
        ProofOfDeliveryAccessService $access
    ): JsonResponse {
        $access->authorize($request->user(), $delivery);

        $proof = $access->proof($delivery);

        return response()->json([
            'message' => 'Proof of delivery retrieved successfully.',
            'data' => [
                'id' => $proof->id,
                'delivery_id' => $proof->delivery_id,
                'driver_id' => $proof->driver_id,
                'recipient_name' => $proof->recipient_name,
                'notes' => $proof->notes,
                'confirmed_at' => $proof->confirmed_at,
                'has_photo' => $proof->photo_path !== null,
                'has_signature' => $proof->signature_path !== null,
            ],
        ]);
    }

    public function file(
        Request $request,
        Delivery $delivery,
        string $type,
        ProofOfDeliveryAccessService $access
    ): BinaryFileResponse {
        $access->authorize($request->user(), $delivery);

        return $access->file($delivery, $type);
    }
}
