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

class DriverAssignmentActionsTest extends TestCase
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
            'is_available' => true,
        ]);
    }

    private function createAssignment(
        Driver $driver,
        string $status = 'pending'
    ): DeliveryAssignment {
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Assigned,
        ]);

        return DeliveryAssignment::query()->create([
            'delivery_id' => $delivery->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'assigned_at' => now(),
        ]);
    }

    public function test_driver_can_accept_pending_assignment(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertRedirect(
                route('driver.assignments.show', $assignment)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('deliveries', [
            'id' => $assignment->delivery_id,
            'status' => DeliveryStatus::Accepted->value,
        ]);
    }

    public function test_accepting_assignment_makes_driver_unavailable(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertRedirect();

        $this->assertDatabaseHas('drivers', [
            'id' => $this->driver->id,
            'is_available' => false,
        ]);
    }

    public function test_driver_can_reject_pending_assignment(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.reject', $assignment), [
                'reason' => 'Vehicle unavailable',
            ])
            ->assertRedirect(
                route('driver.assignments.show', $assignment)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'status' => 'rejected',
            'rejection_reason' => 'Vehicle unavailable',
        ]);

        $this->assertDatabaseHas('deliveries', [
            'id' => $assignment->delivery_id,
            'status' => DeliveryStatus::Matching->value,
        ]);
    }

    public function test_rejection_reason_is_required(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.reject', $assignment), [
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'status' => 'pending',
        ]);
    }

    public function test_driver_cannot_accept_another_drivers_assignment(): void
    {
        $otherDriver = Driver::factory()->create([
            'status' => 'active',
            'is_available' => true,
        ]);

        $assignment = $this->createAssignment($otherDriver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertNotFound();

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'status' => 'pending',
        ]);
    }

    public function test_driver_cannot_reject_another_drivers_assignment(): void
    {
        $otherDriver = Driver::factory()->create();

        $assignment = $this->createAssignment($otherDriver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.reject', $assignment), [
                'reason' => 'Not available',
            ])
            ->assertNotFound();
    }

    public function test_driver_cannot_accept_assignment_twice(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertSessionHasErrors('assignment');

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'status' => 'accepted',
        ]);
    }

    public function test_guest_cannot_accept_assignment(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->post(route('driver.assignments.accept', $assignment))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_driver_role_cannot_accept(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertForbidden();
    }

    public function test_driver_without_profile_cannot_accept(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $user = User::factory()->create();
        $user->addRole('driver');

        $this->actingAs($user)
            ->post(route('driver.assignments.accept', $assignment))
            ->assertNotFound();
    }
}
