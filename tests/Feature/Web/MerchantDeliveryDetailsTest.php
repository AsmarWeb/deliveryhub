<?php

namespace Tests\Feature\Web;

use App\Models\Delivery;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantDeliveryDetailsTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_guest_cannot_view_delivery_details(): void
    {
        $delivery = Delivery::factory()->create();

        $this->get(route('merchant.deliveries.show', $delivery))
            ->assertRedirect(route('login'));
    }

    public function test_merchant_can_view_own_delivery(): void
    {
        $user = User::factory()->create();
        $user->addRole('merchant');

        $merchant = Merchant::factory()->create([
            'user_id' => $user->id,
        ]);

        $delivery = Delivery::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $this->actingAs($user)
            ->get(route('merchant.deliveries.show', $delivery))
            ->assertOk()
            ->assertSee($delivery->tracking_number);
    }

    public function test_merchant_cannot_view_another_merchants_delivery(): void
    {
        $user = User::factory()->create();
        $user->addRole('merchant');

        $merchant = Merchant::factory()->create([
            'user_id' => $user->id,
        ]);

        $otherMerchant = Merchant::factory()->create();

        $delivery = Delivery::factory()->create([
            'merchant_id' => $otherMerchant->id,
        ]);

        $this->actingAs($user)
            ->get(route('merchant.deliveries.show', $delivery))
            ->assertNotFound();
    }
}
