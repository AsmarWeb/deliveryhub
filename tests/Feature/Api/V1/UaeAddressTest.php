<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
use App\Models\Merchant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UaeAddressTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $this->merchant = Merchant::factory()->create(['status' => 'active']);
        $this->merchant->user->addRole('merchant');
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['city' => 'Dubai', 'address_line' => 'Business Bay', 'latitude' => 25.185, 'longitude' => 55.275], $extra);
    }

    public function test_new_details_are_saved_for_the_authenticated_merchant(): void
    {
        $other = Merchant::factory()->create();
        $this->actingAs($this->merchant->user, 'sanctum')->postJson('/api/v1/merchant/addresses', $this->payload([
            'merchant_id' => $other->id, 'emirate' => 'dubai', 'building' => 'Bay Tower',
            'unit' => '1204', 'landmark' => 'Near metro', 'makani_number' => '٠١٢٣٤ ٥٦٧٨٩',
            'delivery_instructions' => 'Use reception',
        ]))->assertCreated()->assertJsonPath('data.emirate', 'dubai')
            ->assertJsonPath('data.makani_number', '0123456789')->assertJsonPath('data.unit', '1204');
        $this->assertDatabaseHas('addresses', ['merchant_id' => $this->merchant->id, 'landmark' => 'Near metro']);
        $this->assertDatabaseMissing('addresses', ['merchant_id' => $other->id, 'landmark' => 'Near metro']);
    }

    public function test_legacy_addresses_and_payloads_remain_usable(): void
    {
        $old = Address::factory()->create(['merchant_id' => $this->merchant->id, 'address_line' => 'Existing address']);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/addresses')
            ->assertOk()->assertJsonFragment(['id' => $old->id, 'address_line' => 'Existing address']);
        $this->postJson('/api/v1/merchant/addresses', $this->payload())
            ->assertCreated()->assertJsonPath('data.emirate', null)->assertJsonPath('data.makani_number', null);
    }

    public function test_makani_is_ten_digits_and_only_accepted_for_dubai(): void
    {
        $this->actingAs($this->merchant->user, 'sanctum');
        foreach ([['emirate' => 'dubai', 'makani_number' => '123'], ['emirate' => 'sharjah', 'makani_number' => '0123456789'], ['makani_number' => '0123456789'], ['emirate' => 'dubai', 'makani_number' => 'abcdefghij']] as $data) {
            $this->postJson('/api/v1/merchant/addresses', $this->payload($data))
                ->assertUnprocessable()->assertJsonValidationErrors('makani_number');
        }
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_invalid_emirate_and_oversized_landmark_are_rejected(): void
    {
        $this->actingAs($this->merchant->user, 'sanctum')->postJson('/api/v1/merchant/addresses', $this->payload([
            'emirate' => 'unknown', 'landmark' => str_repeat('x', 256),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['emirate', 'landmark']);
    }

    public function test_other_emirates_do_not_require_a_makani_number(): void
    {
        $this->actingAs($this->merchant->user, 'sanctum')->postJson('/api/v1/merchant/addresses', $this->payload([
            'city' => 'Al Ain', 'emirate' => 'abu_dhabi',
        ]))->assertCreated()->assertJsonPath('data.emirate', 'abu_dhabi');
    }
}
