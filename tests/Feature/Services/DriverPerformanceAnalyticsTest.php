<?php

namespace Tests\Feature\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryStatusHistory;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\User;
use App\Services\Dashboard\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DriverPerformanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function driver(): Driver
    {
        return Driver::create([
            'user_id' => User::factory()->create()->id,
            'status' => 'active',
            'is_available' => true,
        ]);
    }

    private function delivery(
        Driver $driver,
        bool $completed = true,
        int $daysAgo = 0
    ): Delivery {
        $delivery = Delivery::factory()->create([
            'status' => $completed
                ? DeliveryStatus::Completed
                : DeliveryStatus::InTransit,
        ]);

        $date = Carbon::today()->subDays($daysAgo);

        if ($completed) {
            $history = DeliveryStatusHistory::create([
                'delivery_id' => $delivery->id,
                'from_status' => DeliveryStatus::Delivered,
                'to_status' => DeliveryStatus::Completed,
            ]);

            DeliveryStatusHistory::query()
                ->whereKey($history->id)
                ->update([
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

            ProofOfDelivery::create([
                'delivery_id' => $delivery->id,
                'driver_id' => $driver->id,
                'recipient_name' => 'Test Recipient',
                'confirmed_at' => $date,
            ]);
        }

        return $delivery;
    }

    private function results(int $days = 30)
    {
        return app(AdminAnalyticsService::class)
            ->get($days)['driver_performance'];
    }

    public function test_driver_receives_credit_for_completed_deliveries(): void
    {
        $driver = $this->driver();

        $this->delivery($driver);
        $this->delivery($driver);
        $this->delivery($driver);

        $results = $this->results();

        $this->assertCount(1, $results);
        $this->assertSame($driver->id, (int) $results[0]->id);
        $this->assertSame(
            3,
            (int) $results[0]->completed_deliveries
        );
    }

    public function test_incomplete_delivery_is_not_counted(): void
    {
        $driver = $this->driver();

        $this->delivery($driver, false);

        $this->assertCount(0, $this->results());
    }

    public function test_delivery_outside_period_is_not_counted(): void
    {
        $driver = $this->driver();

        $this->delivery($driver, true, 20);

        $this->assertCount(0, $this->results(7));
    }

    public function test_duplicate_completion_events_count_once(): void
    {
        $driver = $this->driver();
        $delivery = $this->delivery($driver);

        DeliveryStatusHistory::create([
            'delivery_id' => $delivery->id,
            'from_status' => DeliveryStatus::Delivered,
            'to_status' => DeliveryStatus::Completed,
        ]);

        $results = $this->results();

        $this->assertSame(
            1,
            (int) $results[0]->completed_deliveries
        );
    }

    public function test_reassignment_does_not_credit_wrong_driver(): void
    {
        $originalDriver = $this->driver();
        $actualDriver = $this->driver();

        $delivery = $this->delivery($actualDriver);

        DeliveryAssignment::create([
            'delivery_id' => $delivery->id,
            'driver_id' => $originalDriver->id,
            'status' => 'accepted',
            'assigned_at' => now()->subHours(3),
            'accepted_at' => now()->subHours(2),
        ]);

        DeliveryAssignment::create([
            'delivery_id' => $delivery->id,
            'driver_id' => $actualDriver->id,
            'status' => 'accepted',
            'assigned_at' => now()->subHour(),
            'accepted_at' => now(),
        ]);

        $results = $this->results();

        $this->assertCount(1, $results);
        $this->assertSame(
            $actualDriver->id,
            (int) $results[0]->id
        );
    }

    public function test_completed_delivery_without_proof_is_not_attributed(): void
    {
        Delivery::factory()->create([
            'status' => DeliveryStatus::Completed,
        ]);

        $this->assertCount(0, $this->results());
    }
}
