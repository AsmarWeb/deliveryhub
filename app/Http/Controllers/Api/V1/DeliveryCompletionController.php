<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCompletionController extends Controller
{
    public function __invoke(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user->hasRole('admin'), 403);

        $result = DB::transaction(function () use (
            $delivery,
            $user,
            $transitions
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($delivery->status !== DeliveryStatus::Delivered) {
                throw ValidationException::withMessages([
                    'status' => 'Delivery must be delivered before completion.',
                ]);
            }

            $assignment = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->where('status', 'accepted')
                ->lockForUpdate()
                ->first();

            if (!$assignment) {
                throw ValidationException::withMessages([
                    'assignment' => 'Accepted assignment not found.',
                ]);
            }

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($assignment->driver_id);

            $completed = $transitions->transition(
                $delivery,
                DeliveryStatus::Completed,
                $user,
                'Delivery completed by admin'
            );

            $driver->update([
                'is_available' => true,
            ]);

            return [
                'delivery' => $completed->fresh(),
                'driver' => $driver->fresh(),
            ];
        });

        return response()->json([
            'message' => 'Delivery completed successfully.',
            'data' => $result,
        ]);
    }
}
