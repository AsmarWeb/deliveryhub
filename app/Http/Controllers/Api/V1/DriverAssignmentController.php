<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Services\Deliveries\DriverAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('driver'), 403);

        $driver = Driver::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $assignments = DeliveryAssignment::query()
            ->where('driver_id', $driver->id)
            ->with([
                'delivery.pickupAddress',
                'delivery.dropoffAddress',
            ])
            ->latest()
            ->paginate(15);

        return response()->json($assignments);
    }

    public function accept(
        Request $request,
        DeliveryAssignment $assignment,
        DriverAssignmentService $service
    ): JsonResponse {
        abort_unless($request->user()->hasRole('driver'), 403);

        $driver = Driver::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $updated = $service->accept(
            $assignment,
            $driver,
            $request->user()
        );

        return response()->json([
            'message' => 'Assignment accepted successfully.',
            'data' => $updated,
        ]);
    }

    public function reject(
        Request $request,
        DeliveryAssignment $assignment,
        DriverAssignmentService $service
    ): JsonResponse {
        abort_unless($request->user()->hasRole('driver'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $driver = Driver::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $updated = $service->reject(
            $assignment,
            $driver,
            $request->user(),
            $data['reason']
        );

        return response()->json([
            'message' => 'Assignment rejected successfully.',
            'data' => $updated,
        ]);
    }
}
