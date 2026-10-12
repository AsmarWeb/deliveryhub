<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\DriverDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverDeliveryController extends Controller
{
    public function pickup(
        Request $request,
        Delivery $delivery,
        DriverDeliveryService $service
    ): JsonResponse {
        $updated = $service->pickup(
            $delivery,
            $request->user()
        );

        return response()->json([
            'message' => 'Delivery status updated successfully.',
            'data' => $updated,
        ]);
    }

    public function startTransit(
        Request $request,
        Delivery $delivery,
        DriverDeliveryService $service
    ): JsonResponse {
        $updated = $service->startTransit(
            $delivery,
            $request->user()
        );

        return response()->json([
            'message' => 'Delivery status updated successfully.',
            'data' => $updated,
        ]);
    }
}
