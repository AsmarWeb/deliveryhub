<?php

namespace Tests\Feature\Web;

use App\Models\DeliveryPricingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class AdminPricingSettingsTest extends TestCase
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

    public function test_admin_can_open_pricing_settings(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.pricing.edit'))
            ->assertOk();

        $this->assertDatabaseHas('delivery_pricing_settings', [
            'id' => 1,
            'auto_pricing_enabled' => 0,
        ]);
    }

    public function test_merchant_cannot_access_pricing_settings(): void
    {
        $merchant = $this->userWithRole('merchant');

        $this->actingAs($merchant)
            ->get(route('admin.pricing.edit'))
            ->assertForbidden();

        $this->actingAs($merchant)
            ->put(route('admin.pricing.update'), [
                'base_fee' => 100,
                'price_per_km' => 10,
                'minimum_fee' => 100,
                'maximum_distance_km' => 50,
                'auto_pricing_enabled' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_pricing_settings(): void
    {
        $admin = $this->userWithRole('admin');

        DeliveryPricingSetting::query()->firstOrCreate(
            ['id' => 1],
            ['auto_pricing_enabled' => false]
        );

        $this->actingAs($admin)
            ->put(route('admin.pricing.update'), [
                'base_fee' => 15,
                'price_per_km' => 2.50,
                'minimum_fee' => 20,
                'maximum_distance_km' => 60,
                'auto_pricing_enabled' => 1,
            ])
            ->assertRedirect(route('admin.pricing.edit'));

        $this->assertDatabaseHas('delivery_pricing_settings', [
            'id' => 1,
            'base_fee' => 15,
            'price_per_km' => 2.50,
            'minimum_fee' => 20,
            'maximum_distance_km' => 60,
            'auto_pricing_enabled' => 1,
        ]);
    }

    public function test_invalid_pricing_settings_are_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.pricing.update'), [
                'base_fee' => -10,
                'price_per_km' => -1,
                'minimum_fee' => -5,
                'maximum_distance_km' => 0,
                'auto_pricing_enabled' => 1,
            ])
            ->assertSessionHasErrors([
                'base_fee',
                'price_per_km',
                'minimum_fee',
                'maximum_distance_km',
            ]);
    }
}
