<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
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

        $hasActiveDelivery = DeliveryAssignment::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'accepted')
            ->whereHas('delivery', function ($query) {
                $query->whereIn('status', [
                    'accepted',
                    'pickup',
                    'in_transit',
                ]);
            })
            ->exists();

        if (! $hasActiveDelivery) {
            return response()->json([
                'message' => 'No active delivery to track.',
            ], 409);
        }

        $driver->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'last_location_at' => now(),
        ]);

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
