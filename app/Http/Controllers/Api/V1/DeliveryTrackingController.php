<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryTrackingController extends Controller
{
    public function __invoke(Request $request, Delivery $delivery): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            // Admins can view all deliveries.
        } elseif ($user->hasRole('merchant')) {
            $merchant = Merchant::query()->where('user_id', $user->id)->first();

            abort_unless(
                $merchant !== null && (int) $merchant->id === (int) $delivery->merchant_id,
                403
            );
        } elseif ($user->hasRole('driver')) {
            $driver = Driver::query()->where('user_id', $user->id)->first();

            abort_unless(
                $driver !== null && DeliveryAssignment::query()
                    ->where('delivery_id', $delivery->id)
                    ->where('driver_id', $driver->id)
                    ->where('status', 'accepted')
                    ->exists(),
                403
            );
        } else {
            abort(403);
        }

        $timeline = $delivery->statusHistories()
            ->orderBy('id')
            ->get(['id', 'from_status', 'to_status', 'reason', 'created_at'])
            ->map(fn($history) => [
                'from_status' => $history->from_status,
                'status' => $history->to_status,
                'reason' => $history->reason,
                'occurred_at' => $history->created_at?->toIso8601String(),
            ]);

        $driverLocation = null;

        if (in_array($delivery->status->value, [
            'accepted', 'pickup', 'in_transit',
        ], true)) {
            $location = DB::table('delivery_driver_locations')
                ->join(
                    'delivery_assignments',
                    'delivery_assignments.driver_id',
                    '=',
                    'delivery_driver_locations.driver_id'
                )
                ->where('delivery_driver_locations.delivery_id', $delivery->id)
                ->where('delivery_assignments.delivery_id', $delivery->id)
                ->where('delivery_assignments.status', 'accepted')
                ->where('delivery_driver_locations.recorded_at', '>=', now()->subSeconds(60))
                ->where('delivery_driver_locations.recorded_at', '<=', now())
                ->select('delivery_driver_locations.*')
                ->first();

            if ($location) {
                $driverLocation = [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'last_updated_at' => $location->recorded_at,
                ];
            }
        }

        return response()->json([
            'data' => [
                'tracking_number' => $delivery->tracking_number,
                'status' => $delivery->status->value,
                'timeline' => $timeline,
                'driver_location' => $driverLocation,
            ],
        ]);
    }
}
