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

class DriverAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('driver'), 403);

        $driver = Driver::where('user_id', $request->user()->id)
            ->firstOrFail();

        $assignments = DeliveryAssignment::query()
            ->where('driver_id', $driver->id)
            ->with(['delivery.pickupAddress', 'delivery.dropoffAddress'])
            ->latest()
            ->paginate(15);

        return response()->json($assignments);
    }

    public function accept(
        Request $request,
        DeliveryAssignment $assignment,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        abort_unless($request->user()->hasRole('driver'), 403);

        $updated = DB::transaction(function () use (
            $request,
            $assignment,
            $transitions
        ) {
            $assignment = DeliveryAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            $driver = Driver::query()
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            abort_unless($assignment->driver_id === $driver->id, 403);

            if (
                $assignment->status !== 'pending' ||
                $driver->status !== 'active' ||
                ! $driver->is_available
            ) {
                throw ValidationException::withMessages([
                    'assignment' => 'Assignment cannot be accepted.',
                ]);
            }

            $delivery = Delivery::findOrFail($assignment->delivery_id);

            if ($delivery->status !== DeliveryStatus::Assigned) {
                throw ValidationException::withMessages([
                    'delivery' => 'Delivery is not awaiting acceptance.',
                ]);
            }

            $assignment->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $driver->update(['is_available' => false]);

            $transitions->transition(
                $delivery,
                DeliveryStatus::Accepted,
                $request->user(),
                'Driver accepted assignment'
            );

            return $assignment->fresh(['delivery', 'driver']);
        });

        return response()->json([
            'message' => 'Assignment accepted successfully.',
            'data' => $updated,
        ]);
    }

    public function reject(
        Request $request,
        DeliveryAssignment $assignment,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        abort_unless($request->user()->hasRole('driver'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $updated = DB::transaction(function () use (
            $request,
            $assignment,
            $transitions,
            $data
        ) {
            $assignment = DeliveryAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            $driver = Driver::where('user_id', $request->user()->id)
                ->firstOrFail();

            abort_unless($assignment->driver_id === $driver->id, 403);

            if ($assignment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'assignment' => 'Only pending assignments can be rejected.',
                ]);
            }

            $delivery = Delivery::findOrFail($assignment->delivery_id);

            if ($delivery->status !== DeliveryStatus::Assigned) {
                throw ValidationException::withMessages([
                    'delivery' => 'Delivery is not awaiting a driver response.',
                ]);
            }

            $assignment->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $data['reason'],
            ]);

            $transitions->transition(
                $delivery,
                DeliveryStatus::Matching,
                $request->user(),
                'Driver rejected assignment: ' . $data['reason']
            );

            return $assignment->fresh(['delivery', 'driver']);
        });

        return response()->json([
            'message' => 'Assignment rejected successfully.',
            'data' => $updated,
        ]);
    }
}
