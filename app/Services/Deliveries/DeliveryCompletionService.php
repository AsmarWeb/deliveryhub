<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryCompletionService
{
    public function __construct(
        private readonly DeliveryTransitionService $transitions
    ) {}

    public function complete(Delivery $delivery, User $actor): array
    {
        abort_unless($actor->hasRole('admin'), 403);

        return DB::transaction(function () use ($delivery, $actor) {
            $lockedDelivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($lockedDelivery->status !== DeliveryStatus::Delivered) {
                throw ValidationException::withMessages([
                    'status' => 'Delivery must be delivered before completion.',
                ]);
            }

            $assignment = DeliveryAssignment::query()
                ->where('delivery_id', $lockedDelivery->id)
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

            $completed = $this->transitions->transition(
                $lockedDelivery,
                DeliveryStatus::Completed,
                $actor,
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
    }
}
