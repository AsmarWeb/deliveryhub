<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $driver = Driver::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $driver) {
            return response()->json([
                'message' => 'Driver profile not found.',
            ], 404);
        }

        if ($driver->status !== 'active') {
            return response()->json([
                'message' => 'Driver account is not active.',
            ], 403);
        }

        $assignment = DeliveryAssignment::query()
            ->where('delivery_id', $validated['delivery_id'])
            ->where('driver_id', $driver->id)
            ->where('status', 'accepted')
            ->whereHas('delivery', fn ($query) => $query->whereIn(
                'status',
                ['accepted', 'pickup', 'in_transit']
            ))
            ->first();

        if (! $assignment) {
        return response()->json([
                'message' => 'No active delivery to track.',
            ], 409);
        }

        DB::transaction(function () use ($driver, $validated) {
            $now = now();

            DB::table('delivery_driver_locations')->updateOrInsert(
                ['delivery_id' => $validated['delivery_id']],
                [
                    'driver_id' => $driver->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $driver->update([
                'current_latitude' => $validated['latitude'],
                'current_longitude' => $validated['longitude'],
                'last_location_at' => $now,
            ]);
        });

        return response()->json([
            'message' => 'Driver location updated successfully.',
            'data' => [
                'driver_id' => $driver->id,
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'last_location_at' => $driver->last_location_at?->toISOString(),
            ],
        ]);
    }
}
