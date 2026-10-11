<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
use Illuminate\Support\Facades\Schema;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use App\Models\DeliveryAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role)]
        );

        $user = User::factory()->create();
        $user->addRole($role);

        return $user;
    }

    private function merchantFor(User $user): Merchant
    {
        return Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Merchant ' . $user->id,
            'business_email' => 'merchant' . $user->id . '@example.com',
            'status' => 'active',
        ]);
    }


    private function deliveryFor(
        Merchant $merchant,
        DeliveryStatus $status
    ): Delivery {
        // Create an address owned by the merchant.
        $address = new Address();

        $values = [
            'label' => 'Test Address',
            'type' => 'pickup',
            'contact_name' => 'Test Contact',
            'contact_phone' => '0501111111',
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'area' => 'Al Quoz',
            'address_line' => 'Al Quoz, Dubai',
            'address_line_1' => 'Al Quoz, Dubai',
            'street_address' => 'Al Quoz, Dubai',
            'address' => 'Al Quoz, Dubai',
            'latitude' => 25.185,
            'longitude' => 55.275,
        ];

        // Keep only columns that exist in the current schema.
        $attributes = array_intersect_key(
            $values,
            array_flip(Schema::getColumnListing('addresses'))
        );

        $address->forceFill($attributes);
        $address->merchant_id = $merchant->id;
        $address->save();

        return Delivery::create([
            'merchant_id' => $merchant->id,
            'pickup_address_id' => $address->id,
            'dropoff_address_id' => $address->id,
            'tracking_number' => 'DH-TEST-' . uniqid(),
            'status' => $status,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0501234567',
            'package_type' => 'parcel',
        ]);
    }


    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertUnauthorized();
    }

    public function test_user_without_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    }

    public function test_admin_sees_global_statistics(): void
    {
        $admin = $this->userWithRole('admin');

        $merchant = $this->merchantFor(
            $this->userWithRole('merchant')
        );

        $this->deliveryFor($merchant, DeliveryStatus::Draft);
        $this->deliveryFor($merchant, DeliveryStatus::Completed);

        $this->actingAs($admin)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'admin')
            ->assertJsonPath('data.total_deliveries', 2)
            ->assertJsonPath('data.completed_deliveries', 1)
            ->assertJsonPath('data.total_merchants', 1);
    }

    public function test_merchant_sees_only_own_deliveries(): void
    {
        $owner = $this->userWithRole('merchant');
        $other = $this->userWithRole('merchant');

        $ownMerchant = $this->merchantFor($owner);
        $otherMerchant = $this->merchantFor($other);

        $this->deliveryFor(
            $ownMerchant,
            DeliveryStatus::Draft
        );

        $this->deliveryFor(
            $ownMerchant,
            DeliveryStatus::Completed
        );

        $this->deliveryFor(
            $otherMerchant,
            DeliveryStatus::Completed
        );

        $this->actingAs($owner)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'merchant')
            ->assertJsonPath('data.total_deliveries', 2)
            ->assertJsonPath('data.completed_deliveries', 1)
            ->assertJsonCount(2, 'data.recent_deliveries');
    }

    public function test_driver_sees_own_dashboard(): void
    {
        $driverUser = $this->userWithRole('driver');

        Driver::create([
            'user_id' => $driverUser->id,
            'phone' => '0501111111',
            'status' => 'active',
            'is_available' => true,
        ]);

        $this->actingAs($driverUser)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'driver')
            ->assertJsonPath('data.pending_assignments', 0)
            ->assertJsonPath('data.accepted_assignments', 0)
            ->assertJsonPath('data.completed_deliveries', 0)
            ->assertJsonPath('data.is_available', true);
    }

    public function test_merchant_without_profile_returns_not_found(): void
    {
        $user = $this->userWithRole('merchant');

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard')
            ->assertNotFound();
    }

    public function test_driver_without_profile_returns_not_found(): void
    {
        $user = $this->userWithRole('driver');

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard')
            ->assertNotFound();
    }


    public function test_driver_sees_only_own_assignments(): void
    {
        $driverUser = $this->userWithRole('driver');
        $otherDriverUser = $this->userWithRole('driver');

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'phone' => '0501111111',
            'status' => 'active',
            'is_available' => true,
        ]);

        $otherDriver = Driver::create([
            'user_id' => $otherDriverUser->id,
            'phone' => '0502222222',
            'status' => 'active',
            'is_available' => true,
        ]);

        $merchant = $this->merchantFor(
            $this->userWithRole('merchant')
        );

        $ownDelivery = $this->deliveryFor(
            $merchant,
            DeliveryStatus::Assigned
        );

        $otherDelivery = $this->deliveryFor(
            $merchant,
            DeliveryStatus::Assigned
        );

        DeliveryAssignment::create([
            'delivery_id' => $ownDelivery->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        DeliveryAssignment::create([
            'delivery_id' => $otherDelivery->id,
            'driver_id' => $otherDriver->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        $this->actingAs($driverUser)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'driver')
            ->assertJsonPath('data.pending_assignments', 1)
            ->assertJsonPath('data.accepted_assignments', 0);

        $this->actingAs($otherDriverUser)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_assignments', 1);
    }
}
