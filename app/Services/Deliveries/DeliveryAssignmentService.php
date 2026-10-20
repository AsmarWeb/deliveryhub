<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryAssignmentService
{
    public function __construct(
        private DeliveryTransitionService $transitions
    ) {}

    public function assign(
        Delivery $delivery,
        Driver $driver,
        User $actor
    ): DeliveryAssignment {
        abort_unless($actor->hasRole('admin'), 403);
        return $this->perform($delivery, $driver, $actor, false, 'manual');
    }

    public function assignAutomatically(Delivery $delivery, Driver $driver, string $method): DeliveryAssignment
    {
        abort_unless(config('dispatch.enabled'), 422);
        return $this->perform($delivery, $driver, $delivery->merchant->user, true, $method);
    }

    private function perform(Delivery $delivery, Driver $driver, User $actor, bool $automatic, string $method): DeliveryAssignment
    {
        return DB::transaction(function () use (
            $delivery,
            $driver,
            $actor, $automatic, $method
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            if ($automatic && ! app(\App\Services\Dispatch\DriverMatcher::class)->eligible($driver, $delivery)) {
                throw ValidationException::withMessages(['driver_id' => 'Driver is no longer eligible for automatic dispatch.']);
            }
            if (! $driver->hasDeliveryCapacity()) {
                throw ValidationException::withMessages([
                    'driver_id' =>
                    'Driver is unavailable or has reached delivery capacity.',
                ]);
            }

            if ($delivery->status !== DeliveryStatus::Matching) {
                throw ValidationException::withMessages([
                    'delivery' => 'Merchant price approval is required before assignment.',
                ]);
            }

            $hasActiveAssignment = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->exists();

            if ($hasActiveAssignment) {
                throw ValidationException::withMessages([
                    'delivery' => 'Delivery already has an active assignment.',
                ]);
            }

            $metadata = ['automatic' => $automatic, 'dispatch_method' => $method];
            if ($automatic) {
                $metadata += ['pickup_distance_km' => round(app(\App\Services\Dispatch\DriverMatcher::class)->distance(
                    (float) $delivery->pickupAddress->latitude, (float) $delivery->pickupAddress->longitude,
                    (float) $driver->dispatch_latitude, (float) $driver->dispatch_longitude), 2),
                    'active_tasks_before' => $driver->activeDeliveriesCount(), 'capacity' => $driver->max_active_deliveries];
            }
            $assignment = DeliveryAssignment::create([
                'delivery_id' => $delivery->id,
                'driver_id' => $driver->id,
                'is_automatic' => $automatic,
                'status' => 'pending',
                'assigned_at' => now(),
            ]);

            $this->transitions->transition(
                $delivery,
                DeliveryStatus::Assigned,
                $actor,
                ($automatic ? 'Automatically offered to driver #' : 'Delivery assigned to driver #') . $driver->id,
                $metadata + ['assignment_id' => $assignment->id]
            );

            return $assignment->load(['driver', 'delivery']);
        }, 3);
    }
}
