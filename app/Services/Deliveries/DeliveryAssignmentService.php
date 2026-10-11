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

        return DB::transaction(function () use (
            $delivery,
            $driver,
            $actor
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            if (
                $driver->status !== 'active' ||
                ! $driver->is_available
            ) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Driver is not available.',
                ]);
            }

            if (
                ! in_array($delivery->status, [
                    DeliveryStatus::Priced,
                    DeliveryStatus::Matching,
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'delivery' => 'Delivery is not ready for assignment.',
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

            if ($delivery->status === DeliveryStatus::Priced) {
                $delivery = $this->transitions->transition(
                    $delivery,
                    DeliveryStatus::Matching,
                    $actor,
                    'Driver matching started'
                );
            }

            $assignment = DeliveryAssignment::create([
                'delivery_id' => $delivery->id,
                'driver_id' => $driver->id,
                'status' => 'pending',
                'assigned_at' => now(),
            ]);

            $this->transitions->transition(
                $delivery,
                DeliveryStatus::Assigned,
                $actor,
                'Delivery assigned to driver #' . $driver->id
            );

            return $assignment->load(['driver', 'delivery']);
        });
    }
}
