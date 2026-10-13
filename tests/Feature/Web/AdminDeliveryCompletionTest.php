<?php

namespace Tests\Feature\Web;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use App\Models\ProofOfDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class AdminDeliveryCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $merchant;
    private Driver $driver;
    private Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'merchant'] as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => ucfirst($name)]
            );
        }

        $this->admin = User::factory()->create();
        $this->admin->addRole('admin');

        $this->merchant = User::factory()->create();
        $this->merchant->addRole('merchant');

        $driverUser = User::factory()->create();

        $this->driver = Driver::create([
            'user_id' => $driverUser->id,
            'phone' => '0502222222',
            'status' => 'active',
            'is_available' => false,
        ]);

        $this->delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Delivered,
            'delivered_at' => now(),
        ]);

        DeliveryAssignment::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $this->driver->id,
            'status' => 'accepted',
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        ProofOfDelivery::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $this->driver->id,
            'recipient_name' => 'Test Recipient',
            'confirmed_at' => now(),
        ]);
    }

    private function url(): string
    {
        return route('admin.deliveries.complete', $this->delivery);
    }

    public function test_admin_can_complete_delivered_delivery(): void
    {
        $this->actingAs($this->admin)
            ->post($this->url())
            ->assertRedirect(
                route('admin.deliveries.show', $this->delivery)
            );

        $this->assertSame(
            DeliveryStatus::Completed,
            $this->delivery->fresh()->status
        );

        $this->assertTrue(
            (bool) $this->driver->fresh()->is_available
        );

        $this->assertDatabaseHas('delivery_status_histories', [
            'delivery_id' => $this->delivery->id,
            'user_id' => $this->admin->id,
            'from_status' => 'delivered',
            'to_status' => 'completed',
        ]);
    }

    public function test_merchant_cannot_complete_delivery(): void
    {
        $this->actingAs($this->merchant)
            ->post($this->url())
            ->assertForbidden();

        $this->assertSame(
            DeliveryStatus::Delivered,
            $this->delivery->fresh()->status
        );

        $this->assertFalse(
            (bool) $this->driver->fresh()->is_available
        );
    }

    public function test_guest_cannot_complete_delivery(): void
    {
        $this->post($this->url())
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_complete_before_delivery(): void
    {
        $this->delivery->update([
            'status' => DeliveryStatus::InTransit,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.deliveries.show', $this->delivery))
            ->post($this->url())
            ->assertSessionHasErrors('status');

        $this->assertSame(
            DeliveryStatus::InTransit,
            $this->delivery->fresh()->status
        );

        $this->assertFalse(
            (bool) $this->driver->fresh()->is_available
        );
    }

    public function test_admin_cannot_complete_without_accepted_assignment(): void
    {
        DeliveryAssignment::query()
            ->where('delivery_id', $this->delivery->id)
            ->update(['status' => 'rejected']);

        $this->actingAs($this->admin)
            ->from(route('admin.deliveries.show', $this->delivery))
            ->post($this->url())
            ->assertSessionHasErrors('assignment');

        $this->assertSame(
            DeliveryStatus::Delivered,
            $this->delivery->fresh()->status
        );
    }

    public function test_delivery_cannot_be_completed_twice(): void
    {
        $this->actingAs($this->admin)
            ->post($this->url())
            ->assertRedirect(
                route('admin.deliveries.show', $this->delivery)
            );

        $this->actingAs($this->admin)
            ->from(route('admin.deliveries.show', $this->delivery))
            ->post($this->url())
            ->assertSessionHasErrors('status');

        $this->assertSame(
            1,
            $this->delivery->statusHistories()
                ->where('to_status', 'completed')
                ->count()
        );
    }


    public function test_admin_cannot_complete_without_proof(): void
    {
        ProofOfDelivery::query()
            ->where('delivery_id', $this->delivery->id)
            ->delete();

        $this->actingAs($this->admin)
            ->from(route('admin.deliveries.show', $this->delivery))
            ->post($this->url())
            ->assertSessionHasErrors('proof');

        $this->assertSame(
            DeliveryStatus::Delivered,
            $this->delivery->fresh()->status
        );

        $this->assertFalse(
            (bool) $this->driver->fresh()->is_available
        );
    }

    public function test_admin_cannot_complete_when_proof_driver_differs(): void
    {
        $anotherDriver = Driver::create([
            'user_id' => User::factory()->create()->id,
            'phone' => '0503333333',
            'status' => 'active',
            'is_available' => false,
        ]);

        ProofOfDelivery::query()
            ->where('delivery_id', $this->delivery->id)
            ->update([
                'driver_id' => $anotherDriver->id,
            ]);

        $this->actingAs($this->admin)
            ->from(route('admin.deliveries.show', $this->delivery))
            ->post($this->url())
            ->assertSessionHasErrors('assignment');

        $this->assertSame(
            DeliveryStatus::Delivered,
            $this->delivery->fresh()->status
        );

        $this->assertFalse(
            (bool) $this->driver->fresh()->is_available
        );

        $this->assertFalse(
            (bool) $anotherDriver->fresh()->is_available
        );
    }

    public function test_failed_completion_does_not_create_completion_history(): void
    {
        ProofOfDelivery::query()
            ->where('delivery_id', $this->delivery->id)
            ->delete();

        $this->actingAs($this->admin)
            ->from(route('admin.deliveries.show', $this->delivery))
            ->post($this->url())
            ->assertSessionHasErrors('proof');

        $this->assertDatabaseMissing('delivery_status_histories', [
            'delivery_id' => $this->delivery->id,
            'to_status' => DeliveryStatus::Completed->value,
        ]);

        $this->assertSame(
            DeliveryStatus::Delivered,
            $this->delivery->fresh()->status
        );
    }
}
