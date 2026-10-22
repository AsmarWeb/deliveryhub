<?php

namespace Tests\Feature\Pricing;

use App\Models\DeliveryPricingSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmiratePricingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user = User::factory()->create();
        $user->addRole('admin');
        $this->actingAs($user);
    }

    private function input(array $extra = []): array
    {
        return array_merge(['base_fee' => 10, 'price_per_km' => 1.5, 'minimum_fee' => 10,
            'maximum_distance_km' => 50, 'auto_pricing_enabled' => 1,
            'emirate_pricing_enabled' => 1, 'emirate_rates' => ['dubai' => ['dubai' => '0', 'sharjah' => '25.50', 'ajman' => '']]], $extra);
    }

    public function test_admin_can_save_rates_clear_cells_and_render_both_languages(): void
    {
        $this->admin();
        $this->put(route('admin.pricing.update'), $this->input())->assertSessionHasNoErrors()->assertRedirect();
        $settings = DeliveryPricingSetting::findOrFail(1);
        $this->assertTrue($settings->emirate_pricing_enabled);
        $this->assertSame(['dubai' => ['dubai' => '0.00', 'sharjah' => '25.50']], $settings->emirate_rates);
        foreach (['ar', 'en'] as $locale) {
            $this->withSession(['locale' => $locale])->get(route('admin.pricing.edit'))->assertOk()
                ->assertSee('emirate_rates[dubai][sharjah]', false)->assertSee('25.50');
        }
        $this->put(route('admin.pricing.update'), $this->input(['emirate_rates' => ['dubai' => ['sharjah' => '']]]))->assertSessionHasNoErrors();
        $this->assertSame(['dubai' => []], $settings->fresh()->emirate_rates);
    }

    public function test_invalid_rates_and_unknown_emirates_are_rejected(): void
    {
        $this->admin();
        foreach ([['dubai' => ['sharjah' => -1]], ['dubai' => ['sharjah' => '1.234']], ['dubai' => ['sharjah' => 1000000]], ['unknown' => ['dubai' => 25]], ['dubai' => ['unknown' => 25]]] as $rates) {
            $this->putJson(route('admin.pricing.update'), $this->input(['emirate_rates' => $rates]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('delivery_pricing_settings', 0);
    }

    public function test_non_admin_cannot_change_rates(): void
    {
        $this->actingAs(User::factory()->create())->putJson(route('admin.pricing.update'), $this->input())->assertForbidden();
        $this->assertDatabaseCount('delivery_pricing_settings', 0);
    }

    public function test_migration_rolls_back_and_reapplies_preserving_existing_rates(): void
    {
        DeliveryPricingSetting::create(['id' => 1, 'base_fee' => 17, 'price_per_km' => 2, 'minimum_fee' => 20, 'maximum_distance_km' => 50, 'auto_pricing_enabled' => true]);
        $migration = require database_path('migrations/2026_10_22_220000_add_emirate_delivery_pricing.php');
        $migration->down();
        $migration->up();
        $settings = DeliveryPricingSetting::findOrFail(1);
        $this->assertEquals(17, $settings->base_fee);
        $this->assertTrue($settings->auto_pricing_enabled);
        $this->assertFalse($settings->emirate_pricing_enabled);
        $this->assertNull($settings->emirate_rates);
    }
}
