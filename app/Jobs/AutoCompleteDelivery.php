<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AutoCompleteDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $deliveryId
    ) {}

    public function handle(
        DeliveryTransitionService $transitions
    ): void {
        DB::transaction(function () use ($transitions) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($this->deliveryId);

            if ($delivery->status === DeliveryStatus::Completed) {
                return;
            }

            if ($delivery->status !== DeliveryStatus::Delivered) {
                return;
            }

            $proof = ProofOfDelivery::query()
                ->where('delivery_id', $delivery->id)
                ->first();

            if (! $proof || ! $proof->confirmed_at) {
                throw new RuntimeException(
                    'Confirmed proof of delivery is required.'
                );
            }

            $assignment = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->where('driver_id', $proof->driver_id)
                ->where('status', 'accepted')
                ->first();

            if (! $assignment) {
                throw new RuntimeException(
                    'Accepted driver assignment does not match proof.'
                );
            }

            $driver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($proof->driver_id);

            $actor = $driver->user()->firstOrFail();

            $transitions->transition(
                $delivery,
                DeliveryStatus::Completed,
                $actor,
                'Automatically completed after verified proof of delivery',
                [
                    'automation' => true,
                    'automation_type' => 'proof_of_delivery',
                    'proof_id' => $proof->id,
                ]
            );

            $driver->update([
                'work_status' => $driver->activeDeliveriesCount() > 0
                    ? 'busy'
                    : 'idle',
            ]);
        });
    }
}
