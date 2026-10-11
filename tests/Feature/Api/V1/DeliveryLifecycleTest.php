<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laratrust\Models\Role;
use Tests\TestCase;

class DeliveryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $merchantUser;
    private User $otherMerchantUser;
    private User $driverUser;
    private User $otherDriverUser;
    private Merchant $merchant;
    private Driver $driver;
    private Driver $otherDriver;
    private Address $pickup;
    private Address $dropoff;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'merchant', 'driver'] as $name) {
            Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        }

        $this->admin = $this->userWithRole('admin');
        $this->merchantUser = $this->userWithRole('merchant');
        $this->otherMerchantUser = $this->userWithRole('merchant');
        $this->driverUser = $this->userWithRole('driver');
        $this->otherDriverUser = $this->userWithRole('driver');

        $this->merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'business_name' => 'Lifecycle Merchant',
            'business_email' => 'lifecycle@example.com',
            'status' => 'active',
        ]);

        Merchant::create([
            'user_id' => $this->otherMerchantUser->id,
            'business_name' => 'Other Merchant',
            'business_email' => 'other-lifecycle@example.com',
            'status' => 'active',
        ]);

        $this->pickup = $this->address('Pickup', 'Al Quoz');
        $this->dropoff = $this->address('Dropoff', 'Business Bay');

        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'phone' => '0501111111',
            'status' => 'active',
            'is_available' => true,
        ]);

        $this->otherDriver = Driver::create([
            'user_id' => $this->otherDriverUser->id,
            'phone' => '0502222222',
            'status' => 'active',
            'is_available' => true,
        ]);

        Storage::fake('local');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->addRole($role);
        return $user;
    }

    private function address(string $label, string $area): Address
    {
        $values = [
            'label' => "Test {$label}",
            'type' => strtolower($label),
            'contact_name' => $label === 'Pickup' ? 'Warehouse Manager' : 'Recipient',
            'contact_phone' => '0501111111',
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'area' => $area,
            'address_line' => "{$area}, Dubai",
            'address_line_1' => "{$area}, Dubai",
            'street_address' => "{$area}, Dubai",
            'address' => "{$area}, Dubai",
            'latitude' => 25.185,
            'longitude' => 55.275,
        ];

        $attributes = array_intersect_key($values, array_flip(Schema::getColumnListing('addresses')));
        $address = new Address();
        $address->forceFill($attributes);
        $address->merchant_id = $this->merchant->id;
        $address->save();
        return $address;
    }

    private function createDelivery(): Delivery
    {
        $response = $this->actingAs($this->merchantUser)->postJson('/api/v1/deliveries', [
            'pickup_address_id' => $this->pickup->id,
            'dropoff_address_id' => $this->dropoff->id,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0503333333',
            'package_type' => 'parcel',
            'weight' => 1.5,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', DeliveryStatus::Draft->value);
        return Delivery::findOrFail($response->json('data.id'));
    }

    private function submitAndPrice(Delivery $delivery): void
    {
        $this->actingAs($this->merchantUser)
            ->postJson("/api/v1/deliveries/{$delivery->id}/transition", ['status' => 'pending_pricing'])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/deliveries/{$delivery->id}/price", ['delivery_fee' => 25])
            ->assertOk();
    }

    private function assignAndAccept(Delivery $delivery): int
    {
        $this->submitAndPrice($delivery);

        $assignment = $this->actingAs($this->admin)
            ->postJson("/api/v1/deliveries/{$delivery->id}/assign", ['driver_id' => $this->driver->id])
            ->assertCreated();

        $assignmentId = (int) $assignment->json('data.id');

        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/assignments/{$assignmentId}/accept")
            ->assertOk();

        return $assignmentId;
    }

    public function test_merchant_creates_draft_and_history(): void
    {
        $delivery = $this->createDelivery();
        $this->assertSame(DeliveryStatus::Draft, $delivery->status);
        $this->assertSame(1, $delivery->statusHistories()->count());
        $this->assertDatabaseHas('delivery_status_histories', [
            'delivery_id' => $delivery->id,
            'to_status' => 'draft',
            'user_id' => $this->merchantUser->id,
        ]);
    }

    public function test_guest_and_driver_cannot_create_deliveries(): void
    {
        $payload = [
            'pickup_address_id' => $this->pickup->id,
            'dropoff_address_id' => $this->dropoff->id,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '0503333333',
        ];
        $this->postJson('/api/v1/deliveries', $payload)->assertUnauthorized();
        $this->actingAs($this->driverUser)->postJson('/api/v1/deliveries', $payload)->assertForbidden();
    }

    public function test_other_merchant_cannot_submit_delivery(): void
    {
        $delivery = $this->createDelivery();
        $this->actingAs($this->otherMerchantUser)
            ->postJson("/api/v1/deliveries/{$delivery->id}/transition", ['status' => 'pending_pricing'])
            ->assertForbidden();
        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
    }

    public function test_invalid_transition_is_rejected_without_history(): void
    {
        $delivery = $this->createDelivery();
        $this->actingAs($this->merchantUser)
            ->postJson("/api/v1/deliveries/{$delivery->id}/transition", ['status' => 'completed'])
            ->assertForbidden();
        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
        $this->assertSame(1, $delivery->statusHistories()->count());
    }

    public function test_admin_prices_and_assigns_delivery(): void
    {
        $delivery = $this->createDelivery();
        $this->submitAndPrice($delivery);
        $this->assertSame(DeliveryStatus::Priced, $delivery->fresh()->status);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/deliveries/{$delivery->id}/assign", ['driver_id' => $this->driver->id])
            ->assertCreated();

        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame(DeliveryStatus::Assigned, $delivery->fresh()->status);
        $this->assertSame(1, $delivery->assignments()->count());
    }

    public function test_other_driver_cannot_accept_assignment(): void
    {
        $delivery = $this->createDelivery();
        $this->submitAndPrice($delivery);
        $assignmentId = $this->actingAs($this->admin)
            ->postJson("/api/v1/deliveries/{$delivery->id}/assign", ['driver_id' => $this->driver->id])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->otherDriverUser)
            ->postJson("/api/v1/driver/assignments/{$assignmentId}/accept")
            ->assertForbidden();
        $this->assertSame(DeliveryStatus::Assigned, $delivery->fresh()->status);
    }

    public function test_assigned_driver_accepts_and_becomes_unavailable(): void
    {
        $delivery = $this->createDelivery();
        $this->assignAndAccept($delivery);
        $this->assertSame(DeliveryStatus::Accepted, $delivery->fresh()->status);
        $this->assertFalse((bool) $this->driver->fresh()->is_available);
    }

    public function test_driver_cannot_start_transit_before_pickup(): void
    {
        $delivery = $this->createDelivery();
        $this->assignAndAccept($delivery);
        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/start-transit")
            ->assertUnprocessable();
        $this->assertSame(DeliveryStatus::Accepted, $delivery->fresh()->status);
    }

    public function test_other_driver_cannot_pickup(): void
    {
        $delivery = $this->createDelivery();
        $this->assignAndAccept($delivery);
        $this->actingAs($this->otherDriverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/pickup")
            ->assertForbidden();
        $this->assertSame(DeliveryStatus::Accepted, $delivery->fresh()->status);
    }

    public function test_pickup_and_transit_record_status_and_timestamp(): void
    {
        $delivery = $this->createDelivery();
        $this->assignAndAccept($delivery);
        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/pickup")
            ->assertOk();
        $this->assertSame(DeliveryStatus::Pickup, $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->picked_up_at);

        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/start-transit")
            ->assertOk();
        $this->assertSame(DeliveryStatus::InTransit, $delivery->fresh()->status);
    }

    public function test_pod_delivers_and_admin_completes_delivery(): void
    {
        $delivery = $this->createDelivery();
        $this->assignAndAccept($delivery);
        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/pickup")
            ->assertOk();
        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/start-transit")
            ->assertOk();

        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/proof", [
                'recipient_name' => 'Test Recipient',
                'notes' => 'Received in good condition',
            ])->assertCreated();

        $this->assertSame(DeliveryStatus::Delivered, $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);
        $this->assertDatabaseHas('proof_of_deliveries', [
            'delivery_id' => $delivery->id,
            'driver_id' => $this->driver->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/deliveries/{$delivery->id}/complete")
            ->assertOk();

        $this->assertSame(DeliveryStatus::Completed, $delivery->fresh()->status);
        $this->assertTrue((bool) $this->driver->fresh()->is_available);
        $this->assertSame(10, $delivery->statusHistories()->count());
    }

    public function test_pod_cannot_be_submitted_twice(): void
    {
        $delivery = $this->createDelivery();
        $this->assignAndAccept($delivery);
        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/pickup")
            ->assertOk();
        $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/deliveries/{$delivery->id}/start-transit")
            ->assertOk();

        $url = "/api/v1/driver/deliveries/{$delivery->id}/proof";
        $this->actingAs($this->driverUser)
            ->postJson($url, ['recipient_name' => 'Recipient'])
            ->assertCreated();
        $this->actingAs($this->driverUser)
            ->postJson($url, ['recipient_name' => 'Recipient'])
            ->assertUnprocessable();
        $this->assertSame(1, \App\Models\ProofOfDelivery::where('delivery_id', $delivery->id)->count());
    }

    public function test_admin_cannot_complete_before_delivery(): void
    {
        $delivery = $this->createDelivery();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/deliveries/{$delivery->id}/complete")
            ->assertUnprocessable();
        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
    }
}
