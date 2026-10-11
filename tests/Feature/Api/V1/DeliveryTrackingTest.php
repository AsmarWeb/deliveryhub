<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laratrust\Models\Role;
use Tests\TestCase;

class DeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Delivery $delivery;
    private User $admin;
    private User $merchantUser;
    private User $otherMerchantUser;
    private User $driverUser;
    private User $otherDriverUser;

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

        $merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'business_name' => 'Tracking Merchant',
            'business_email' => 'tracking@example.com',
            'status' => 'active',
        ]);

        Merchant::create([
            'user_id' => $this->otherMerchantUser->id,
            'business_name' => 'Other Tracking Merchant',
            'business_email' => 'other-tracking@example.com',
            'status' => 'active',
        ]);

        $pickup = $this->address($merchant, 'Pickup', 'Al Quoz');
        $dropoff = $this->address($merchant, 'Dropoff', 'Business Bay');

        $driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'phone' => '0501111111',
            'status' => 'active',
            'is_available' => false,
        ]);

        Driver::create([
            'user_id' => $this->otherDriverUser->id,
            'phone' => '0502222222',
            'status' => 'active',
            'is_available' => true,
        ]);

        $this->delivery = Delivery::create([
            'merchant_id' => $merchant->id,
            'pickup_address_id' => $pickup->id,
            'dropoff_address_id' => $dropoff->id,
            'tracking_number' => 'DH-TRACKING-TEST-001',
            'status' => 'accepted',
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0503333333',
            'currency' => 'AED',
        ]);

        DeliveryAssignment::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->delivery->statusHistories()->create([
            'user_id' => $this->merchantUser->id,
            'from_status' => null,
            'to_status' => 'draft',
            'reason' => 'Delivery created',
        ]);
        $this->delivery->statusHistories()->create([
            'user_id' => $this->driverUser->id,
            'from_status' => 'assigned',
            'to_status' => 'accepted',
            'reason' => 'Driver accepted assignment',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->addRole($role);
        return $user;
    }

    private function address(Merchant $merchant, string $label, string $area): Address
    {
        $values = [
            'label' => "Test {$label}",
            'type' => strtolower($label),
            'contact_name' => 'Test Contact',
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

        $address = new Address();
        $address->forceFill(array_intersect_key($values, array_flip(Schema::getColumnListing('addresses'))));
        $address->merchant_id = $merchant->id;
        $address->save();
        return $address;
    }

    private function endpoint(): string
    {
        return "/api/v1/deliveries/{$this->delivery->id}/tracking";
    }

    public function test_guest_cannot_track_delivery(): void
    {
        $this->getJson($this->endpoint())->assertUnauthorized();
    }

    public function test_admin_can_track_delivery_and_see_ordered_timeline(): void
    {
        $this->actingAs($this->admin)->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.tracking_number', 'DH-TRACKING-TEST-001')
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.timeline.0.status', 'draft')
            ->assertJsonPath('data.timeline.1.status', 'accepted')
            ->assertJsonCount(2, 'data.timeline');
    }

    public function test_owner_merchant_can_track_delivery(): void
    {
        $this->actingAs($this->merchantUser)->getJson($this->endpoint())->assertOk();
    }

    public function test_other_merchant_cannot_track_delivery(): void
    {
        $this->actingAs($this->otherMerchantUser)->getJson($this->endpoint())->assertForbidden();
    }

    public function test_accepted_driver_can_track_delivery(): void
    {
        $this->actingAs($this->driverUser)->getJson($this->endpoint())->assertOk();
    }

    public function test_other_driver_cannot_track_delivery(): void
    {
        $this->actingAs($this->otherDriverUser)->getJson($this->endpoint())->assertForbidden();
    }

    public function test_pending_assignment_does_not_grant_tracking_access(): void
    {
        $otherDriver = Driver::where('user_id', $this->otherDriverUser->id)->firstOrFail();
        DeliveryAssignment::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $otherDriver->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->otherDriverUser)->getJson($this->endpoint())->assertForbidden();
    }

    public function test_tracking_response_excludes_private_delivery_details(): void
    {
        $this->actingAs($this->merchantUser)->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonMissingPath('data.recipient_phone')
            ->assertJsonMissingPath('data.driver.phone')
            ->assertJsonMissingPath('data.timeline.0.metadata');
    }
}
