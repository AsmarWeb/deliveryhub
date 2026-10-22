<?php

namespace App\Services\Pricing;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryPricingSetting;
use App\Models\User;
use App\Services\Deliveries\DeliveryTransitionService;
use App\Support\UaeAddressFields;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AutomaticDeliveryPricingService
{
    public function __construct(
        private readonly DeliveryPricingService $calculator,
        private readonly DeliveryTransitionService $transitions,
    ) {}

    public function price(Delivery $delivery, User $actor): ?Delivery
    {
        return DB::transaction(function () use ($delivery, $actor) {
            $locked = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($locked->status !== DeliveryStatus::PendingPricing) {
                return null;
            }

            $settings = DeliveryPricingSetting::query()->find(1);

            if (! $settings || ! $settings->auto_pricing_enabled) {
                return null;
            }

            $locked->load(['pickupAddress', 'dropoffAddress']);

            if (! $locked->pickupAddress || ! $locked->dropoffAddress) {
                return null;
            }

            $hasCoordinates = $locked->pickupAddress->latitude !== null
                && $locked->pickupAddress->longitude !== null
                && $locked->dropoffAddress->latitude !== null
                && $locked->dropoffAddress->longitude !== null;

            if ($hasCoordinates) {
                try {
                    $quote = $this->calculator->calculate(
                        $locked->pickupAddress, $locked->dropoffAddress,
                        (float) $settings->base_fee, (float) $settings->price_per_km,
                    );
                } catch (InvalidArgumentException) {
                    return null;
                }
                if ($quote['distance_km'] > (float) $settings->maximum_distance_km) {
                    return null;
                }
                $fee = max($quote['delivery_fee'], (float) $settings->minimum_fee);
                $metadata = [
                    'pricing_method' => 'haversine', 'distance_km' => $quote['distance_km'],
                    'base_fee' => $quote['base_fee'], 'price_per_km' => $quote['price_per_km'],
                ];
            } else {
                if (! $settings->emirate_pricing_enabled) {
                    return null;
                }
                $from = $locked->pickupAddress->emirate;
                $to = $locked->dropoffAddress->emirate;
                if (! in_array($from, UaeAddressFields::EMIRATES, true)
                    || ! in_array($to, UaeAddressFields::EMIRATES, true)) {
                    return null;
                }
                $rate = $settings->emirate_rates[$from][$to] ?? null;
                if ($rate === null || ! is_numeric($rate) || (float) $rate < 0) {
                    return null;
                }
                $fee = max((float) $rate, (float) $settings->minimum_fee);
                $metadata = [
                    'pricing_method' => 'emirate', 'pickup_emirate' => $from,
                    'dropoff_emirate' => $to, 'emirate_rate' => (float) $rate,
                ];
            }

            $locked->delivery_fee = round($fee, 2);
            $locked->currency = 'AED';
            $locked->save();

            return $this->transitions->transition(
                $locked,
                DeliveryStatus::Priced,
                $actor,
                'Automatically calculated delivery price',
                array_merge($metadata, [
                    'automation' => true,
                    'minimum_fee' => (float) $settings->minimum_fee,
                    'calculated_fee' => $locked->delivery_fee,
                    'currency' => 'AED',
                ])
            );
        });
    }
}
