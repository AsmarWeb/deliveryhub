<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Services\Deliveries\DeliveryCreationService;
use App\Services\Deliveries\RecurringDeliveryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRecurringDeliveries;
use Tests\TestCase;

class RecurringDeliveryRuntimeTest extends TestCase
{
    use RefreshDatabase, CreatesRecurringDeliveries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
    }

    public function test_scheduler_processes_recurring_deliveries_every_minute_without_overlap(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $event = collect(app(Schedule::class)->events())->first(
            fn ($event) => str_contains($event->command ?? '', 'deliveries:run-recurring')
        );
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_due_occurrence_creates_one_draft_and_advances_in_local_time(): void
    {
        $schedule = $this->schedule();
        $this->subscribe($schedule->merchant);

        $this->artisan('deliveries:run-recurring')->assertSuccessful();
        $this->artisan('deliveries:run-recurring')->assertSuccessful();

        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('recurring_delivery_runs', 1);
        $this->assertSame(DeliveryStatus::Draft, Delivery::first()->status);
        $this->assertSame('completed', $schedule->runs()->first()->status);
        $this->assertSame('2026-10-21 09:00:00', $schedule->fresh()->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_failure_is_persisted_and_same_occurrence_can_retry(): void
    {
        $schedule = $this->schedule();
        $this->artisan('deliveries:run-recurring')->assertFailed();
        $this->assertSame('failed', $schedule->runs()->first()->status);
        $this->assertTrue($schedule->fresh()->next_run_at->equalTo(now()));
        $this->assertDatabaseCount('deliveries', 0);

        $this->subscribe($schedule->merchant);
        $this->artisan('deliveries:run-recurring')->assertSuccessful();
        $this->assertDatabaseCount('recurring_delivery_runs', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertSame('completed', $schedule->runs()->first()->status);
        $this->assertNull($schedule->runs()->first()->error_message);
    }

    public function test_partial_delivery_writes_roll_back_but_failure_record_survives(): void
    {
        $schedule = $this->schedule();
        $this->subscribe($schedule->merchant);
        $real = new DeliveryCreationService();
        $this->mock(DeliveryCreationService::class)->shouldReceive('create')->once()
            ->andReturnUsing(function (...$args) use ($real) {
                $real->create(...$args);
                throw new \RuntimeException('Simulated downstream failure');
            });

        $this->artisan('deliveries:run-recurring')->assertFailed();
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_status_histories', 0);
        $this->assertSame('failed', $schedule->runs()->first()->status);
    }

    public function test_expired_occurrence_is_skipped_without_creating_a_delivery(): void
    {
        $schedule = $this->schedule(['next_run_at' => now()->subMinutes(16)]);
        $this->artisan('deliveries:run-recurring')->assertSuccessful();
        $this->assertSame('skipped', $schedule->runs()->first()->status);
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_inactive_and_future_schedules_are_ignored(): void
    {
        $this->schedule(['is_active' => false]);
        $this->schedule(['next_run_at' => now()->addHour()]);
        $this->artisan('deliveries:run-recurring')->assertSuccessful();
        $this->assertDatabaseCount('recurring_delivery_runs', 0);
    }

    public function test_disabled_plan_or_merchant_cannot_generate_deliveries(): void
    {
        $schedule = $this->schedule();
        $subscription = $this->subscribe($schedule->merchant, 0);
        $this->artisan('deliveries:run-recurring')->assertFailed();
        $subscription->plan->update(['max_active_automations' => 5]);
        $schedule->merchant->update(['status' => 'suspended']);
        $this->artisan('deliveries:run-recurring')->assertFailed();
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_weekly_schedule_preserves_local_execution_time_across_dst(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-24 07:00:00', 'UTC'));
        $schedule = $this->schedule([
            'frequency' => 'weekly',
            'timezone' => 'Europe/Berlin',
            'execution_time' => '09:00',
            'day_of_week' => 6,
        ]);
        $this->subscribe($schedule->merchant);
        app(RecurringDeliveryService::class)->execute($schedule->id);
        $this->assertSame('2026-10-31 08:00:00', $schedule->fresh()->next_run_at->format('Y-m-d H:i:s'));
    }
}
