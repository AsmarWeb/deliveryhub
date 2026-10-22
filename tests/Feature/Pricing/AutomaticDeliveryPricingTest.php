<?php

namespace Tests\Feature\Pricing;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryPricingSetting;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Pricing\AutomaticDeliveryPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticDeliveryPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();

        $merchant = Merchant::factory()->create();

        $pickup = Address::factory()->create([
            'merchant_id' => $merchant->id,
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ]);

        $dropoff = Address::factory()->create([
            'merchant_id' => $merchant->id,
            'latitude' => 25.1972,
            'longitude' => 55.2744,
        ]);

        $this->delivery = Delivery::factory()->create([
            'merchant_id' => $merchant->id,
            'pickup_address_id' => $pickup->id,
            'dropoff_address_id' => $dropoff->id,
            'status' => DeliveryStatus::PendingPricing,
            'delivery_fee' => null,
            'currency' => 'AED',
        ]);
    }

    private function settings(array $attributes = []): void
    {
        DeliveryPricingSetting::create(array_merge([
            'base_fee' => 10,
            'price_per_km' => 1.5,
            'minimum_fee' => 10,
            'maximum_distance_km' => 50,
            'auto_pricing_enabled' => true,
        ], $attributes));
    }

    private function price(): ?Delivery
    {
        return app(AutomaticDeliveryPricingService::class)
            ->price($this->delivery, $this->actor);
    }

    public function test_does_not_price_when_disabled(): void
    {
        $this->settings(['auto_pricing_enabled' => false]);

        $this->assertNull($this->price());

        $this->assertSame(
            DeliveryStatus::PendingPricing,
            $this->delivery->fresh()->status
        );

        $this->assertNull($this->delivery->fresh()->delivery_fee);
    }

    public function test_automatically_prices_delivery(): void
    {
        $this->settings();

        $result = $this->price();

        $this->assertNotNull($result);

        $this->assertSame(
            DeliveryStatus::Priced,
            $this->delivery->fresh()->status
        );

        $this->assertGreaterThan(
            10,
            (float) $this->delivery->fresh()->delivery_fee
        );

        $this->assertDatabaseHas('delivery_status_histories', [
            'delivery_id' => $this->delivery->id,
            'from_status' => DeliveryStatus::PendingPricing->value,
            'to_status' => DeliveryStatus::Priced->value,
        ]);

        $history = $this->delivery->statusHistories()
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($history->metadata['automation']);
        $this->assertSame(
            'haversine',
            $history->metadata['pricing_method']
        );
    }

    public function test_applies_minimum_fee(): void
    {
        $this->settings([
            'minimum_fee' => 30,
        ]);

        $this->price();

        $this->assertEquals(
            30,
            (float) $this->delivery->fresh()->delivery_fee
        );
    }

    public function test_rejects_distance_above_limit(): void
    {
        $this->settings([
            'maximum_distance_km' => 0.10,
        ]);

        $this->assertNull($this->price());

        $this->assertSame(
            DeliveryStatus::PendingPricing,
            $this->delivery->fresh()->status
        );
    }

    public function test_does_not_price_twice(): void
    {
        $this->settings();

        $this->assertNotNull($this->price());

        $historyCount = $this->delivery
            ->statusHistories()
            ->count();

        $this->assertNull($this->price());

        $this->assertSame(
            $historyCount,
            $this->delivery->statusHistories()->count()
        );
    }

    private function emirateRoute(string $from = 'dubai', string $to = 'sharjah'): void
    {
        $this->delivery->pickupAddress->update(['emirate' => $from]);
        $this->delivery->dropoffAddress->update(['emirate' => $to, 'latitude' => null, 'longitude' => null]);
    }

    public function test_emirate_rate_prices_missing_coordinates_and_records_snapshot(): void
    {
        $this->emirateRoute();
        $this->settings(['emirate_pricing_enabled' => true, 'emirate_rates' => ['dubai' => ['sharjah' => '25.00']]]);
        $this->assertNotNull($this->price());
        $this->assertEquals(25, $this->delivery->fresh()->delivery_fee);
        $this->assertSame(DeliveryStatus::Priced, $this->delivery->fresh()->status);
        $metadata = $this->delivery->statusHistories()->latest('id')->first()->metadata;
        $this->assertSame('emirate', $metadata['pricing_method']);
        $this->assertSame('dubai', $metadata['pickup_emirate']);
        $this->assertSame('sharjah', $metadata['dropoff_emirate']);
        $this->assertNull($this->price());
    }

    public function test_emirate_rates_require_both_switches_and_exact_direction(): void
    {
        $this->emirateRoute();
        $this->settings(['emirate_rates' => ['dubai' => ['sharjah' => 25]]]);
        $this->assertNull($this->price());
        DeliveryPricingSetting::find(1)->update(['emirate_pricing_enabled' => true, 'auto_pricing_enabled' => false]);
        $this->assertNull($this->price());
        DeliveryPricingSetting::find(1)->update(['auto_pricing_enabled' => true, 'emirate_rates' => ['sharjah' => ['dubai' => 25]]]);
        $this->assertNull($this->price());
        $this->assertSame(DeliveryStatus::PendingPricing, $this->delivery->fresh()->status);
    }

    public function test_zero_rate_is_explicit_and_minimum_applies(): void
    {
        $this->emirateRoute('dubai', 'dubai');
        $this->settings(['emirate_pricing_enabled' => true, 'emirate_rates' => ['dubai' => ['dubai' => 0]]]);
        $this->assertNotNull($this->price());
        $this->assertEquals(10, $this->delivery->fresh()->delivery_fee);
    }

    public function test_coordinates_take_priority_and_distance_limit_does_not_fall_back(): void
    {
        $this->delivery->pickupAddress->update(['emirate' => 'dubai']);
        $this->delivery->dropoffAddress->update(['emirate' => 'dubai']);
        $this->settings(['emirate_pricing_enabled' => true, 'emirate_rates' => ['dubai' => ['dubai' => 200]]]);
        $this->assertNotNull($this->price());
        $this->assertLessThan(200, (float) $this->delivery->fresh()->delivery_fee);
    }

    public function test_distance_limit_still_requires_manual_pricing_with_emirate_rates(): void
    {
        $this->settings(['maximum_distance_km' => 0.10, 'emirate_pricing_enabled' => true, 'emirate_rates' => ['dubai' => ['dubai' => 25]]]);
        $this->assertNull($this->price());
    }

    public function test_missing_emirate_and_blank_rate_do_not_produce_a_quote(): void
    {
        $this->emirateRoute();
        $this->settings(['emirate_pricing_enabled' => true, 'emirate_rates' => ['dubai' => ['sharjah' => null]]]);
        $this->assertNull($this->price());
        $this->delivery->pickupAddress->update(['emirate' => null]);
        $this->assertNull($this->price());
    }
}
