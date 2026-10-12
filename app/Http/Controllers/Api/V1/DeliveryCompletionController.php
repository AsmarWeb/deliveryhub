<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Deliveries\DeliveryCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryCompletionController extends Controller
{
    public function __invoke(
        Request $request,
        Delivery $delivery,
        DeliveryCompletionService $completion
    ): JsonResponse {
        $result = $completion->complete(
            $delivery,
            $request->user()
        );

        return response()->json([
            'message' => 'Delivery completed successfully.',
            'data' => $result,
        ]);
    }
}
