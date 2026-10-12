<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverAssignmentService
{
    public function __construct(
        private readonly DeliveryTransitionService $transitions
    ) {}

    public function accept(
        DeliveryAssignment $assignment,
        Driver $driver,
        User $actor
    ): DeliveryAssignment {
        abort_unless(
            $actor->hasRole('driver') &&
                $driver->user_id === $actor->id,
            403
        );

        return DB::transaction(function () use (
            $assignment,
            $driver,
            $actor
        ) {
            $assignment = DeliveryAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            abort_unless(
                $assignment->driver_id === $driver->id,
                403
            );

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            $delivery = $assignment->delivery()
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $assignment->status !== 'pending' ||
                $driver->status !== 'active' ||
                ! $driver->is_available
            ) {
                throw ValidationException::withMessages([
                    'assignment' => 'Assignment cannot be accepted.',
                ]);
            }

            if ($delivery->status !== DeliveryStatus::Assigned) {
                throw ValidationException::withMessages([
                    'delivery' => 'Delivery is not awaiting acceptance.',
                ]);
            }

            $assignment->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $driver->update([
                'is_available' => false,
            ]);

            $this->transitions->transition(
                $delivery,
                DeliveryStatus::Accepted,
                $actor,
                'Driver accepted assignment'
            );

            return $assignment->fresh(['delivery', 'driver']);
        });
    }

    public function reject(
        DeliveryAssignment $assignment,
        Driver $driver,
        User $actor,
        string $reason
    ): DeliveryAssignment {
        abort_unless(
            $actor->hasRole('driver') &&
                $driver->user_id === $actor->id,
            403
        );

        return DB::transaction(function () use (
            $assignment,
            $driver,
            $actor,
            $reason
        ) {
            $assignment = DeliveryAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            abort_unless(
                $assignment->driver_id === $driver->id,
                403
            );

            $delivery = $assignment->delivery()
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'assignment' => 'Only pending assignments can be rejected.',
                ]);
            }

            if ($delivery->status !== DeliveryStatus::Assigned) {
                throw ValidationException::withMessages([
                    'delivery' => 'Delivery is not awaiting a driver response.',
                ]);
            }

            $assignment->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->transitions->transition(
                $delivery,
                DeliveryStatus::Matching,
                $actor,
                'Driver rejected assignment: ' . $reason
            );

            return $assignment->fresh(['delivery', 'driver']);
        });
    }
}
