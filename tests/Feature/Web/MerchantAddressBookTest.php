<?php

namespace Tests\Feature\Web;

use App\Models\Address;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantAddressBookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['name' => 'merchant'],
            [
                'display_name' => 'Merchant',
                'description' => 'Merchant account',
            ]
        );

        $this->user = User::factory()->create();
        $this->user->addRole('merchant');

        $this->merchant = Merchant::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    private function addressData(): array
    {
        return [
            'label' => 'Dubai Warehouse',
            'contact_name' => 'Test Contact',
            'contact_phone' => '0501234567',
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'area' => 'Business Bay',
            'street' => 'Marasi Drive',
            'building' => 'Business Tower',
            'unit' => '101',
            'address_line' => 'Business Bay, Dubai, UAE',
            'latitude' => 25.185,
            'longitude' => 55.275,
            'delivery_instructions' => 'Call on arrival',
        ];
    }

    public function test_guest_cannot_access_addresses(): void
    {
        $this->get(route('merchant.addresses.index'))
            ->assertRedirect(route('login'));
    }

    public function test_merchant_can_view_only_own_addresses(): void
    {
        $own = Address::factory()->create([
            'merchant_id' => $this->merchant->id,
            'label' => 'My Warehouse',
        ]);

        $other = Address::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
            'label' => 'Other Warehouse',
        ]);

        $this->actingAs($this->user)
            ->get(route('merchant.addresses.index'))
            ->assertOk()
            ->assertSee($own->label)
            ->assertDontSee($other->label);
    }

    public function test_merchant_can_create_address(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('merchant.addresses.store'),
                $this->addressData()
            )
            ->assertRedirect(route('merchant.addresses.index'));

        $this->assertDatabaseHas('addresses', [
            'merchant_id' => $this->merchant->id,
            'label' => 'Dubai Warehouse',
            'city' => 'Dubai',
        ]);
    }

    public function test_merchant_can_update_own_address(): void
    {
        $address = Address::factory()->create([
            'merchant_id' => $this->merchant->id,
        ]);

        $this->actingAs($this->user)
            ->put(
                route('merchant.addresses.update', $address),
                array_merge($this->addressData(), [
                    'label' => 'Updated Warehouse',
                ])
            )
            ->assertRedirect(route('merchant.addresses.index'));

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'label' => 'Updated Warehouse',
        ]);
    }

    public function test_merchant_can_delete_unused_address(): void
    {
        $address = Address::factory()->create([
            'merchant_id' => $this->merchant->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('merchant.addresses.destroy', $address))
            ->assertRedirect(route('merchant.addresses.index'));

        $this->assertDatabaseMissing('addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_merchant_cannot_update_other_merchants_address(): void
    {
        $address = Address::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->put(
                route('merchant.addresses.update', $address),
                $this->addressData()
            )
            ->assertNotFound();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'label' => $address->label,
        ]);
    }

    public function test_merchant_cannot_delete_other_merchants_address(): void
    {
        $address = Address::factory()->create([
            'merchant_id' => Merchant::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('merchant.addresses.destroy', $address))
            ->assertNotFound();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_merchant_cannot_delete_address_used_by_delivery(): void
    {
        $address = Address::factory()->create([
            'merchant_id' => $this->merchant->id,
        ]);

        Delivery::factory()->create([
            'merchant_id' => $this->merchant->id,
            'pickup_address_id' => $address->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('merchant.addresses.destroy', $address))
            ->assertRedirect(route('merchant.addresses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_user_without_merchant_role_is_forbidden(): void
    {
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('merchant.addresses.index'))
            ->assertForbidden();
    }

    public function test_invalid_address_data_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('merchant.addresses.store'), [
                'country' => 'United Arab Emirates',
                'city' => '',
                'address_line' => '',
                'latitude' => 100,
            ])
            ->assertSessionHasErrors([
                'city',
                'address_line',
                'latitude',
            ]);
    }
}
