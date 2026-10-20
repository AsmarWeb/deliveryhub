<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryCreationService
{
    public function create(
        Merchant $merchant,
        array $data,
        ?int $userId = null,
        string $reason = 'Delivery created'
    ): Delivery {
        foreach (['pickup_address_id', 'dropoff_address_id'] as $field) {
            $belongsToMerchant = $merchant->addresses()
                ->whereKey($data[$field] ?? null)
                ->exists();

            if (! $belongsToMerchant) {
                throw ValidationException::withMessages([
                    $field => 'Address does not belong to this merchant.',
                ]);
            }
        }

        return DB::transaction(function () use (
            $merchant,
            $data,
            $userId,
            $reason
        ) {
            $delivery = Delivery::create([
                ...$data,
                'merchant_id' => $merchant->id,
                'tracking_number' => 'DH-' . Str::upper((string) Str::ulid()),
                'status' => DeliveryStatus::Draft,
                'currency' => 'AED',
            ]);

            $delivery->statusHistories()->create([
                'user_id' => $userId,
                'from_status' => null,
                'to_status' => DeliveryStatus::Draft->value,
                'reason' => $reason,
            ]);

            return $delivery;
        });
    }
}
