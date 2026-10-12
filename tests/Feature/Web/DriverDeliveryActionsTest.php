<?php

namespace Tests\Feature\Web;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverDeliveryActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['name' => 'driver'],
            [
                'display_name' => 'Driver',
                'description' => 'Delivery driver',
            ]
        );

        $this->user = User::factory()->create();
        $this->user->addRole('driver');

        $this->driver = Driver::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'is_available' => false,
        ]);
    }

    private function assignment(
        DeliveryStatus $status,
        ?Driver $driver = null
    ): DeliveryAssignment {
        $delivery = Delivery::factory()->create([
            'status' => $status,
        ]);

        return DeliveryAssignment::create([
            'delivery_id' => $delivery->id,
            'driver_id' => ($driver ?? $this->driver)->id,
            'status' => 'accepted',
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    public function test_driver_can_confirm_pickup(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Accepted);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.pickup', $assignment))
            ->assertRedirect(
                route('driver.assignments.show', $assignment)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('deliveries', [
            'id' => $assignment->delivery_id,
            'status' => DeliveryStatus::Pickup->value,
        ]);

        $this->assertNotNull(
            $assignment->delivery->fresh()->picked_up_at
        );
    }

    public function test_driver_can_start_transit_after_pickup(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Pickup);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.start-transit', $assignment))
            ->assertRedirect(
                route('driver.assignments.show', $assignment)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('deliveries', [
            'id' => $assignment->delivery_id,
            'status' => DeliveryStatus::InTransit->value,
        ]);
    }

    public function test_driver_cannot_start_transit_before_pickup(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Accepted);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.start-transit', $assignment))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('deliveries', [
            'id' => $assignment->delivery_id,
            'status' => DeliveryStatus::Accepted->value,
        ]);
    }

    public function test_driver_cannot_pickup_twice(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Accepted);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.pickup', $assignment))
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.pickup', $assignment))
            ->assertSessionHasErrors('status');
    }

    public function test_driver_cannot_pickup_another_drivers_delivery(): void
    {
        $otherDriver = Driver::factory()->create();
        $assignment = $this->assignment(
            DeliveryStatus::Accepted,
            $otherDriver
        );

        $this->actingAs($this->user)
            ->post(route('driver.assignments.pickup', $assignment))
            ->assertNotFound();
    }

    public function test_guest_cannot_confirm_pickup(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Accepted);

        $this->post(route('driver.assignments.pickup', $assignment))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_driver_role_cannot_pickup(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Accepted);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->post(route('driver.assignments.pickup', $assignment))
            ->assertForbidden();
    }

    public function test_pending_assignment_cannot_confirm_pickup(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Accepted);
        $assignment->update(['status' => 'pending']);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.pickup', $assignment))
            ->assertForbidden();
    }
}
