<?php

namespace Tests\Feature\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Services\Dashboard\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::parse('2026-10-13 12:00:00')
        );

        $this->analytics = app(AdminAnalyticsService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function delivery(
        DeliveryStatus $status,
        int $daysAgo = 0
    ): Delivery {
        $delivery = Delivery::factory()->create([
            'status' => $status,
        ]);

        $date = Carbon::today()
            ->subDays($daysAgo)
            ->setTime(10, 0);

        // Update timestamps directly without triggering
        // model events or automatic timestamp changes.
        Delivery::query()
            ->whereKey($delivery->id)
            ->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);

        return $delivery->fresh();
    }

    private function recordCompletion(
        Delivery $delivery,
        int $daysAgo = 0
    ): void {
        $history = DeliveryStatusHistory::create([
            'delivery_id' => $delivery->id,
            'from_status' => DeliveryStatus::Delivered,
            'to_status' => DeliveryStatus::Completed,
        ]);

        $date = Carbon::today()
            ->subDays($daysAgo)
            ->setTime(11, 0);

        DeliveryStatusHistory::query()
            ->whereKey($history->id)
            ->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
    }

    public function test_empty_database_returns_zero_statistics(): void
    {
        $result = $this->analytics->get();

        $this->assertSame(30, $result['days']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['completed']);
        $this->assertEquals(0, $result['success_rate']);
        $this->assertCount(30, $result['trends']);
        $this->assertCount(0, $result['driver_performance']);
    }

    public function test_supported_date_filters(): void
    {
        foreach ([7, 30, 90] as $days) {
            $result = $this->analytics->get($days);

            $this->assertSame($days, $result['days']);
            $this->assertCount($days, $result['trends']);
        }
    }

    public function test_invalid_date_filter_defaults_to_30(): void
    {
        $result = $this->analytics->get(999);

        $this->assertSame(30, $result['days']);
        $this->assertCount(30, $result['trends']);
    }

    public function test_completion_rate_uses_created_deliveries(): void
    {
        $this->delivery(DeliveryStatus::Completed);
        $this->delivery(DeliveryStatus::Completed);
        $this->delivery(DeliveryStatus::InTransit);
        $this->delivery(DeliveryStatus::Draft);

        $result = $this->analytics->get(7);

        $this->assertSame(4, $result['total']);
        $this->assertSame(2, $result['completed']);
        $this->assertEquals(50.0, $result['success_rate']);
    }

    public function test_deliveries_outside_period_are_excluded(): void
    {
        $this->delivery(DeliveryStatus::Draft, 10);
        $this->delivery(DeliveryStatus::Completed, 2);

        $result = $this->analytics->get(7);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['completed']);
        $this->assertEquals(100.0, $result['success_rate']);
    }

    public function test_completion_trend_uses_status_history(): void
    {
        $delivery = $this->delivery(
            DeliveryStatus::Completed,
            5
        );

        $this->recordCompletion($delivery, 2);

        // A later edit must not change the completion date.
        $delivery->update([
            'special_instructions' => 'Updated after completion',
        ]);

        $result = $this->analytics->get(7);

        $completionDate = Carbon::today()
            ->subDays(2)
            ->toDateString();

        $row = collect($result['trends'])
            ->firstWhere('date', $completionDate);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['completed']);
    }

    public function test_duplicate_completion_history_is_counted_once(): void
    {
        $delivery = $this->delivery(
            DeliveryStatus::Completed
        );

        $this->recordCompletion($delivery);
        $this->recordCompletion($delivery);

        $result = $this->analytics->get(7);

        $this->assertSame(
            1,
            array_sum(array_column(
                $result['trends'],
                'completed'
            ))
        );
    }
}
