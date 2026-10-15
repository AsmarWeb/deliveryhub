<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $driver = Driver::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $driver) {
            return response()->json([
                'message' => 'Driver profile not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatDriver($driver),
        ]);
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $driver = DB::transaction(function () use ($request, $validated) {
            $driver = Driver::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $driver) {
                return null;
            }

            // Only active drivers may become available.
            if (
                $validated['is_available'] &&
                $driver->status !== 'active'
            ) {
                return false;
            }

            $driver->update([
                'is_available' => $validated['is_available'],
            ]);

            return $driver->refresh();
        });

        if ($driver === null) {
            return response()->json([
                'message' => 'Driver profile not found.',
            ], 404);
        }

        if ($driver === false) {
            return response()->json([
                'message' => 'Only active drivers can become available.',
            ], 422);
        }

        return response()->json([
            'message' => 'Driver availability updated successfully.',
            'data' => $this->formatDriver($driver),
        ]);
    }

    private function formatDriver(Driver $driver): array
    {
        return [
            'id' => $driver->id,
            'user_id' => $driver->user_id,
            'phone' => $driver->phone,
            'vehicle_type' => $driver->vehicle_type,
            'vehicle_plate' => $driver->vehicle_plate,
            'license_number' => $driver->license_number,
            'status' => $driver->status,
            'is_available' => $driver->is_available,
            'last_location_at' => $driver->last_location_at?->toISOString(),
        ];
    }
}
