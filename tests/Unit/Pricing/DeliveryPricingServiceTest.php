<?php

namespace Tests\Unit\Pricing;

use App\Models\Address;
use App\Services\Pricing\DeliveryPricingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeliveryPricingServiceTest extends TestCase
{
    public function test_calculates_price_for_same_location(): void
    {
        $pickup = new Address([
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ]);

        $dropoff = new Address([
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ]);

        $result = (new DeliveryPricingService())
            ->calculate($pickup, $dropoff);

        $this->assertSame(0.0, $result['distance_km']);
        $this->assertSame(10.0, $result['delivery_fee']);
        $this->assertSame('AED', $result['currency']);
    }

    public function test_calculates_price_between_coordinates(): void
    {
        $pickup = new Address([
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ]);

        $dropoff = new Address([
            'latitude' => 25.1972,
            'longitude' => 55.2744,
        ]);

        $result = (new DeliveryPricingService())
            ->calculate($pickup, $dropoff);

        $this->assertGreaterThan(0, $result['distance_km']);
        $this->assertGreaterThan(10, $result['delivery_fee']);
        $this->assertSame('haversine', $result['distance_method']);
    }

    public function test_rejects_missing_coordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DeliveryPricingService())->calculate(
            new Address(['latitude' => 25.2048]),
            new Address([
                'latitude' => 25.1972,
                'longitude' => 55.2744,
            ])
        );
    }

    public function test_rejects_negative_rates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DeliveryPricingService())->calculate(
            new Address([
                'latitude' => 25.2048,
                'longitude' => 55.2708,
            ]),
            new Address([
                'latitude' => 25.1972,
                'longitude' => 55.2744,
            ]),
            -10
        );
    }
}
