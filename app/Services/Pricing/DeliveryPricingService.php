<?php

namespace App\Services\Pricing;

use App\Models\Address;
use InvalidArgumentException;

class DeliveryPricingService
{
    public function calculate(
        Address $pickup,
        Address $dropoff,
        float $baseFee = 10.0,
        float $pricePerKm = 1.5
    ): array {
        if (
            $pickup->latitude === null ||
            $pickup->longitude === null ||
            $dropoff->latitude === null ||
            $dropoff->longitude === null
        ) {
            throw new InvalidArgumentException(
                'Both addresses must have coordinates.'
            );
        }

        if ($baseFee < 0 || $pricePerKm < 0) {
            throw new InvalidArgumentException(
                'Pricing rates cannot be negative.'
            );
        }

        $lat1 = (float) $pickup->latitude;
        $lon1 = (float) $pickup->longitude;
        $lat2 = (float) $dropoff->latitude;
        $lon2 = (float) $dropoff->longitude;

        if (
            abs($lat1) > 90 ||
            abs($lat2) > 90 ||
            abs($lon1) > 180 ||
            abs($lon2) > 180
        ) {
            throw new InvalidArgumentException(
                'Invalid address coordinates.'
            );
        }

        $earthRadiusKm = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lonDelta / 2) ** 2;

        $a = max(0.0, min(1.0, $a));

        $distanceKm = $earthRadiusKm
            * 2
            * atan2(sqrt($a), sqrt(1 - $a));

        $fee = round(
            $baseFee + ($distanceKm * $pricePerKm),
            2
        );

        return [
            'distance_km' => round($distanceKm, 2),
            'distance_method' => 'haversine',
            'base_fee' => round($baseFee, 2),
            'price_per_km' => round($pricePerKm, 2),
            'delivery_fee' => $fee,
            'currency' => 'AED',
        ];
    }
}
