<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryStatusChanged;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTransitionService
{
    public function transition(
        Delivery $delivery,
        DeliveryStatus $to,
        User $actor,
        ?string $reason = null,
        array $metadata = []
    ): Delivery {
        return DB::transaction(function () use (
            $delivery,
            $to,
            $actor,
            $reason,
            $metadata
        ) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            $from = $delivery->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Invalid transition: {$from->value} -> {$to->value}",
                    ],
                ]);
            }

            $delivery->status = $to;

            if ($to === DeliveryStatus::Pickup) {
                $delivery->picked_up_at = now();
            }

            if ($to === DeliveryStatus::Delivered) {
                $delivery->delivered_at = now();
            }

            $delivery->save();

            $history = $delivery->statusHistories()->create([
                'user_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            DB::afterCommit(function () use ($delivery, $history) {
                event(new DeliveryStatusChanged(
                    $delivery,
                    $history
                ));
            });

            return $delivery->refresh();
        });
    }
}
