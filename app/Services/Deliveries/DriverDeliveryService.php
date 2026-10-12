<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverDeliveryService
{
    public function __construct(
        private readonly DeliveryTransitionService $transitions
    ) {}

    public function pickup(Delivery $delivery, User $actor): Delivery
    {
        return $this->execute(
            $delivery,
            $actor,
            DeliveryStatus::Accepted,
            DeliveryStatus::Pickup,
            'Driver picked up the shipment'
        );
    }

    public function startTransit(Delivery $delivery, User $actor): Delivery
    {
        return $this->execute(
            $delivery,
            $actor,
            DeliveryStatus::Pickup,
            DeliveryStatus::InTransit,
            'Shipment is in transit'
        );
    }

    private function execute(
        Delivery $delivery,
        User $actor,
        DeliveryStatus $expected,
        DeliveryStatus $next,
        string $reason
    ): Delivery {
        abort_unless($actor->hasRole('driver'), 403);

        return DB::transaction(function () use (
            $delivery,
            $actor,
            $expected,
            $next,
            $reason
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $driver = Driver::query()
                ->where('user_id', $actor->id)
                ->firstOrFail();

            $hasAcceptedAssignment = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->where('driver_id', $driver->id)
                ->where('status', 'accepted')
                ->exists();

            abort_unless($hasAcceptedAssignment, 403);

            if ($delivery->status !== $expected) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid delivery status transition.',
                ]);
            }

            return $this->transitions->transition(
                $delivery,
                $next,
                $actor,
                $reason
            );
        });
    }
}
