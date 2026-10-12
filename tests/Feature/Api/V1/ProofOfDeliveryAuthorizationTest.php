<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\ProofOfDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laratrust\Models\Role;
use Tests\TestCase;

class ProofOfDeliveryAuthorizationTest extends TestCase
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

        $this->admin = $this->createUserWithRole('admin');
        $this->merchantUser = $this->createUserWithRole('merchant');
        $this->otherMerchantUser = $this->createUserWithRole('merchant');
        $this->driverUser = $this->createUserWithRole('driver');
        $this->otherDriverUser = $this->createUserWithRole('driver');

        $merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'business_name' => 'Test Merchant',
            'business_email' => 'merchant-test@example.com',
            'status' => 'active',
        ]);

        Merchant::create([
            'user_id' => $this->otherMerchantUser->id,
            'business_name' => 'Other Merchant',
            'business_email' => 'other-merchant@example.com',
            'status' => 'active',
        ]);

        $pickupAddress = $this->createAddress($merchant, 'Pickup', 'Al Quoz');
        $dropoffAddress = $this->createAddress($merchant, 'Dropoff', 'Business Bay');

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
            'pickup_address_id' => $pickupAddress->id,
            'dropoff_address_id' => $dropoffAddress->id,
            'tracking_number' => 'DH-TEST-POD-001',
            'status' => 'completed',
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0503333333',
            'package_type' => 'parcel',
            'weight' => 1.50,
            'delivery_fee' => 25,
            'currency' => 'AED',
        ]);

        DeliveryAssignment::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        
Storage::fake('local');

$photoPath = "proof-of-deliveries/{$this->delivery->id}/photos/photo.png";
$signaturePath = "proof-of-deliveries/{$this->delivery->id}/signatures/signature.png";

Storage::disk('local')->put($photoPath, 'test photo content');
Storage::disk('local')->put($signaturePath, 'test signature content');

ProofOfDelivery::create([
    'delivery_id' => $this->delivery->id,
    'driver_id' => $driver->id,
    'recipient_name' => 'Test Recipient',
    'photo_path' => $photoPath,
    'signature_path' => $signaturePath,
    'confirmed_at' => now(),
]);

    }


    private function createAddress(
        Merchant $merchant,
        string $label,
        string $area
    ): Address {
        $values = [
            'label' => "Test {$label}",
            'type' => strtolower($label),
            'contact_name' => $label === 'Pickup'
                ? 'Warehouse Manager'
                : 'Test Customer',
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

        // Keep only existing database columns.
        $columns = Schema::getColumnListing('addresses');

        $attributes = array_intersect_key(
            $values,
            array_flip($columns)
        );

        // Create an address without mass-assignment restrictions.
        // This is used only for test fixtures.
        $address = new Address();

        $address->forceFill($attributes);

        // Associate the address with its merchant.
        $address->merchant_id = $merchant->id;

        $address->save();

        return $address;
    }


    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->addRole($role);
        return $user;
    }

    private function endpoint(string $suffix = ''): string
    {
        return "/api/v1/deliveries/{$this->delivery->id}/proof{$suffix}";
    }

    public function test_guest_cannot_view_pod(): void
    {
        $this->getJson($this->endpoint())->assertUnauthorized();
    }

    public function test_admin_can_view_pod(): void
    {
        $this->actingAs($this->admin)
            ->getJson($this->endpoint())
            ->assertOk();
    }

    public function test_owner_merchant_can_view_pod(): void
    {
        $this->actingAs($this->merchantUser)
            ->getJson($this->endpoint())
            ->assertOk();
    }

    public function test_other_merchant_cannot_view_pod(): void
    {
        $this->actingAs($this->otherMerchantUser)
            ->getJson($this->endpoint())
            ->assertForbidden();
    }

    public function test_assigned_driver_can_view_pod(): void
    {
        $this->actingAs($this->driverUser)
            ->getJson($this->endpoint())
            ->assertOk();
    }

    public function test_other_driver_cannot_view_pod(): void
    {
        $this->actingAs($this->otherDriverUser)
            ->getJson($this->endpoint())
            ->assertForbidden();
    }

    public function test_admin_can_download_photo_and_signature(): void
    {
        foreach (['photo', 'signature'] as $type) {
            $this->actingAs($this->admin)
                ->get($this->endpoint("/{$type}"))
                ->assertOk();
        }
    }

    public function test_other_merchant_cannot_download_files(): void
    {
        foreach (['photo', 'signature'] as $type) {
            $this->actingAs($this->otherMerchantUser)
                ->getJson($this->endpoint("/{$type}"))
                ->assertForbidden();
        }
    }

    public function test_other_driver_cannot_download_files(): void
    {
        foreach (['photo', 'signature'] as $type) {
            $this->actingAs($this->otherDriverUser)
                ->getJson($this->endpoint("/{$type}"))
                ->assertForbidden();
        }
    }

    public function test_guest_cannot_download_files(): void
    {
        foreach (['photo', 'signature'] as $type) {
            $this->getJson($this->endpoint("/{$type}"))
                ->assertUnauthorized();
        }
    }
}
