<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
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

            // The proof identifies the driver who confirmed delivery.
            $proof = ProofOfDelivery::query()
                ->where('delivery_id', $lockedDelivery->id)
                ->lockForUpdate()
                ->first();

            if (! $proof) {
                throw ValidationException::withMessages([
                    'proof' => 'Proof of delivery is required before completion.',
                ]);
            }

            // Require an accepted assignment belonging to that driver.
            $assignment = DeliveryAssignment::query()
                ->where('delivery_id', $lockedDelivery->id)
                ->where('driver_id', $proof->driver_id)
                ->where('status', 'accepted')
                ->lockForUpdate()
                ->first();

            if (! $assignment) {
                throw ValidationException::withMessages([
                    'assignment' => 'Accepted assignment does not match proof of delivery.',
                ]);
            }

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($proof->driver_id);

            $completed = $this->transitions->transition(
                $lockedDelivery,
                DeliveryStatus::Completed,
                $actor,
                'Delivery completed by admin'
            );

            $driver->update([
                'is_available' => $driver->status === 'active',
            ]);

            return [
                'delivery' => $completed->fresh(),
                'driver' => $driver->fresh(),
            ];
        });
    }
}
