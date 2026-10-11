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

class DriverDeliveryController extends Controller
{
    public function pickup(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        return $this->execute(
            $request,
            $delivery,
            DeliveryStatus::Accepted,
            DeliveryStatus::Pickup,
            'Driver picked up the shipment',
            $transitions
        );
    }

    public function startTransit(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        return $this->execute(
            $request,
            $delivery,
            DeliveryStatus::Pickup,
            DeliveryStatus::InTransit,
            'Shipment is in transit',
            $transitions
        );
    }

    public function deliver(
        Request $request,
        Delivery $delivery,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        return $this->execute(
            $request,
            $delivery,
            DeliveryStatus::InTransit,
            DeliveryStatus::Delivered,
            'Shipment delivered to recipient',
            $transitions
        );
    }

    private function execute(
        Request $request,
        Delivery $delivery,
        DeliveryStatus $expected,
        DeliveryStatus $next,
        string $reason,
        DeliveryTransitionService $transitions
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user->hasRole('driver'), 403);

        $updated = DB::transaction(function () use (
            $user,
            $delivery,
            $expected,
            $next,
            $reason,
            $transitions
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $driver = Driver::where('user_id', $user->id)
                ->firstOrFail();

            $assignment = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->where('driver_id', $driver->id)
                ->where('status', 'accepted')
                ->first();

            abort_unless($assignment !== null, 403);

            if ($delivery->status !== $expected) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid delivery status transition.',
                ]);
            }

            return $transitions->transition(
                $delivery,
                $next,
                $user,
                $reason
            );
        });

        return response()->json([
            'message' => 'Delivery status updated successfully.',
            'data' => $updated,
        ]);
    }
}
