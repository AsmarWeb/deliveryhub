<?php

namespace Tests\Feature\Web;

use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverDeliveryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): Driver
    {
        Role::firstOrCreate(['name' => 'driver'], ['display_name' => 'Driver']);
        $user = User::factory()->create();
        $user->addRole('driver');

        return Driver::factory()->create(['user_id' => $user->id]);
    }

    private function shipment(Driver $driver, string $status, string $offer = 'accepted'): Delivery
    {
        $delivery = Delivery::factory()->create(['status' => $status, 'delivered_at' => now()]);
        DeliveryAssignment::create(['driver_id' => $driver->id, 'delivery_id' => $delivery->id, 'status' => $offer, 'assigned_at' => now()]);

        return $delivery;
    }

    public function test_history_shows_only_own_delivered_and_completed_deliveries(): void
    {
        $driver = $this->driver();
        $delivered = $this->shipment($driver, 'delivered');
        $completed = $this->shipment($driver, 'completed');
        $active = $this->shipment($driver, 'in_transit');
        $declined = $this->shipment($driver, 'completed', 'rejected');
        $other = $this->shipment($this->driver(), 'completed');
        $this->actingAs($driver->user)->get(route('driver.history.index'))->assertOk()
            ->assertSee($delivered->tracking_number)->assertSee($completed->tracking_number)
            ->assertDontSee($active->tracking_number)->assertDontSee($declined->tracking_number)->assertDontSee($other->tracking_number)
            ->assertViewHas('counts', ['all' => 2, 'delivered' => 1, 'completed' => 1]);
    }

    public function test_reassigned_delivery_is_credited_to_last_accepted_driver_only(): void
    {
        $old = $this->driver();
        $current = $this->driver();
        $delivery = $this->shipment($old, 'completed');
        DeliveryAssignment::create(['driver_id' => $current->id, 'delivery_id' => $delivery->id, 'status' => 'accepted', 'assigned_at' => now()]);
        $this->actingAs($old->user)->get(route('driver.history.index'))->assertOk()->assertDontSee($delivery->tracking_number);
        $this->actingAs($current->user)->get(route('driver.history.index'))->assertOk()->assertSee($delivery->tracking_number);
    }

    public function test_filters_search_and_pagination_remain_scoped(): void
    {
        $driver = $this->driver();
        $delivery = $this->shipment($driver, 'completed');
        $this->shipment($driver, 'delivered');
        $this->actingAs($driver->user)->get(route('driver.history.index', ['status' => 'completed', 'q' => $delivery->tracking_number]))
            ->assertOk()->assertViewHas('deliveries', fn ($rows) => $rows->total() === 1);
        for ($i = 0; $i < 16; $i++) {
            $this->shipment($driver, 'completed');
        }
        $this->get(route('driver.history.index', ['status' => 'completed', 'page' => 2]))->assertOk()
            ->assertViewHas('deliveries', fn ($rows) => $rows->count() === 2 && $rows->total() === 17);
        $this->getJson(route('driver.history.index', ['status' => 'in_transit']))->assertUnprocessable();
    }

    public function test_guests_and_non_drivers_cannot_read_history(): void
    {
        $this->get(route('driver.history.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('driver.history.index'))->assertForbidden();
    }
}
