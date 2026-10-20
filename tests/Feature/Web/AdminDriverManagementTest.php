<?php

namespace Tests\Feature\Web;

use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class AdminDriverManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $merchant;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'merchant', 'driver'] as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => ucfirst($name)]
            );
        }

        $this->admin = User::factory()->create();
        $this->admin->addRole('admin');

        $this->merchant = User::factory()->create();
        $this->merchant->addRole('merchant');

        $this->driver = $this->createDriver(
            'Test Driver',
            'active'
        );
    }

    private function createDriver(
        string $name,
        string $status = 'active'
    ): Driver {
        $user = User::factory()->create([
            'name' => $name,
        ]);

        $user->addRole('driver');

        return Driver::create([
            'user_id' => $user->id,
            'phone' => '0501234567',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'Dubai A 12345',
            'license_number' => 'TEST-LIC-12345',
            'status' => $status,
            'is_available' => $status === 'active',
        ]);
    }

    private function assign(
        Driver $driver,
        string $status
    ): DeliveryAssignment {
        $delivery = Delivery::factory()->create();

        return DeliveryAssignment::create([
            'delivery_id' => $delivery->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'assigned_at' => now(),
            'accepted_at' => $status === 'accepted'
                ? now()
                : null,
        ]);
    }

    private function statusUrl(Driver $driver): string
    {
        return route('admin.drivers.status', $driver);
    }

    public function test_admin_can_view_drivers(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.drivers.index'))
            ->assertOk()
            ->assertSee('Test Driver');
    }

    public function test_guest_cannot_view_drivers(): void
    {
        $this->get(route('admin.drivers.index'))
            ->assertRedirect(route('login'));
    }

    public function test_merchant_cannot_view_drivers(): void
    {
        $this->actingAs($this->merchant)
            ->get(route('admin.drivers.index'))
            ->assertForbidden();
    }

    public function test_admin_can_search_drivers_by_name(): void
    {
        $this->createDriver('Ahmed Driver');
        $this->createDriver('Mohamed Driver');

        $this->actingAs($this->admin)
            ->get(route('admin.drivers.index', [
                'search' => 'Ahmed Driver',
            ]))
            ->assertOk()
            ->assertSee('Ahmed Driver')
            ->assertDontSee('Mohamed Driver');
    }

    public function test_admin_can_filter_drivers_by_status(): void
    {
        $this->createDriver('Inactive Driver', 'inactive');

        $this->actingAs($this->admin)
            ->get(route('admin.drivers.index', [
                'status' => 'inactive',
            ]))
            ->assertOk()
            ->assertSee('Inactive Driver')
            ->assertDontSee('Test Driver');
    }

    public function test_admin_can_deactivate_driver_without_active_assignments(): void
    {
        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->driver), [
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.drivers.index'));

        $this->assertSame(
            'inactive',
            $this->driver->fresh()->status
        );

        $this->assertFalse(
            (bool) $this->driver->fresh()->is_available
        );
    }

    public function test_admin_can_activate_inactive_driver(): void
    {
        $this->driver->update([
            'status' => 'inactive',
            'is_available' => false,
        ]);

        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->driver), [
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.drivers.index'));

        $this->assertSame(
            'active',
            $this->driver->fresh()->status
        );

        // Activation must not automatically make driver available.
        $this->assertFalse(
            (bool) $this->driver->fresh()->is_available
        );
    }

    public function test_admin_cannot_deactivate_driver_with_pending_assignment(): void
    {
        $this->assign($this->driver, 'pending');

        $this->actingAs($this->admin)
            ->from(route('admin.drivers.index'))
            ->patch($this->statusUrl($this->driver), [
                'status' => 'inactive',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            'active',
            $this->driver->fresh()->status
        );
    }

    public function test_admin_cannot_deactivate_driver_with_accepted_assignment(): void
    {
        $this->assign($this->driver, 'accepted');

        $this->actingAs($this->admin)
            ->from(route('admin.drivers.index'))
            ->patch($this->statusUrl($this->driver), [
                'status' => 'inactive',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            'active',
            $this->driver->fresh()->status
        );
    }

    public function test_rejected_assignment_does_not_block_deactivation(): void
    {
        $this->assign($this->driver, 'rejected');

        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->driver), [
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.drivers.index'));

        $this->assertSame(
            'inactive',
            $this->driver->fresh()->status
        );
    }

    public function test_merchant_cannot_change_driver_status(): void
    {
        $this->actingAs($this->merchant)
            ->patch($this->statusUrl($this->driver), [
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->assertSame(
            'active',
            $this->driver->fresh()->status
        );
    }

    public function test_guest_cannot_change_driver_status(): void
    {
        $this->patch($this->statusUrl($this->driver), [
            'status' => 'inactive',
        ])
            ->assertRedirect(route('login'));

        $this->assertSame(
            'active',
            $this->driver->fresh()->status
        );
    }

    public function test_invalid_driver_status_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.drivers.index'))
            ->patch($this->statusUrl($this->driver), [
                'status' => 'banned',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            'active',
            $this->driver->fresh()->status
        );
    }
}
