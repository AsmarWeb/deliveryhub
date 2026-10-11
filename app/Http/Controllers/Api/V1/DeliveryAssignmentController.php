<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\Deliveries\DeliveryAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryAssignmentController extends Controller
{
    public function store(
        Request $request,
        Delivery $delivery,
        DeliveryAssignmentService $service
    ): JsonResponse {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
            ],
        ]);

        $driver = Driver::findOrFail($data['driver_id']);

        $assignment = $service->assign(
            $delivery,
            $driver,
            $request->user()
        );

        return response()->json([
            'message' => 'Delivery assigned successfully.',
            'data' => $assignment,
        ], 201);
    }
}
