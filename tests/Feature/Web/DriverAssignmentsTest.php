<?php

namespace Tests\Feature\Web;

use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAssignmentsTest extends TestCase
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
        ]);
    }

    private function createAssignment(
        Driver $driver,
        string $status = 'pending'
    ): DeliveryAssignment {
        $delivery = Delivery::factory()->create();

        return DeliveryAssignment::query()->create([
            'delivery_id' => $delivery->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'assigned_at' => now(),
        ]);
    }

    public function test_guest_cannot_view_assignments(): void
    {
        $this->get(route('driver.assignments.index'))
            ->assertRedirect(route('login'));
    }

    public function test_driver_can_view_assignments_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('driver.assignments.index'))
            ->assertOk()
            ->assertViewIs('driver.assignments.index');
    }

    public function test_driver_sees_only_own_assignments(): void
    {
        $own = $this->createAssignment($this->driver);

        $otherDriver = Driver::factory()->create();
        $other = $this->createAssignment($otherDriver);

        $this->actingAs($this->user)
            ->get(route('driver.assignments.index'))
            ->assertOk()
            ->assertViewHas('assignments', function ($assignments) use ($own, $other) {
                return $assignments->contains('id', $own->id)
                    && ! $assignments->contains('id', $other->id);
            });
    }

    public function test_driver_can_view_own_assignment_details(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->get(route('driver.assignments.show', $assignment))
            ->assertOk()
            ->assertViewIs('driver.assignments.show');
    }

    public function test_driver_cannot_view_another_drivers_assignment(): void
    {
        $otherDriver = Driver::factory()->create();
        $assignment = $this->createAssignment($otherDriver);

        $this->actingAs($this->user)
            ->get(route('driver.assignments.show', $assignment))
            ->assertNotFound();
    }

    public function test_driver_can_filter_pending_assignments(): void
    {
        $pending = $this->createAssignment($this->driver, 'pending');
        $accepted = $this->createAssignment($this->driver, 'accepted');

        $this->actingAs($this->user)
            ->get(route('driver.assignments.index', [
                'status' => 'pending',
            ]))
            ->assertOk()
            ->assertViewHas('assignments', function ($assignments) use ($pending, $accepted) {
                return $assignments->contains('id', $pending->id)
                    && ! $assignments->contains('id', $accepted->id);
            });
    }

    public function test_invalid_status_filter_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get(route('driver.assignments.index', [
                'status' => 'invalid',
            ]))
            ->assertNotFound();
    }

    public function test_user_without_driver_role_is_forbidden(): void
    {
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('driver.assignments.index'))
            ->assertForbidden();
    }

    public function test_driver_without_profile_returns_404(): void
    {
        $user = User::factory()->create();
        $user->addRole('driver');

        $this->actingAs($user)
            ->get(route('driver.assignments.index'))
            ->assertNotFound();
    }

    public function test_driver_can_see_assignment_addresses(): void
    {
        $assignment = $this->createAssignment($this->driver);

        $this->actingAs($this->user)
            ->get(route('driver.assignments.show', $assignment))
            ->assertOk()
            ->assertViewHas('assignment', function ($loaded) {
                return $loaded->relationLoaded('delivery')
                    && $loaded->delivery->relationLoaded('pickupAddress')
                    && $loaded->delivery->relationLoaded('dropoffAddress');
            });
    }
}
