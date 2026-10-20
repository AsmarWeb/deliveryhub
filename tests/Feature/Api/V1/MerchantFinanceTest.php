<?php

namespace Tests\Feature\Api\V1;

use App\Models\Delivery;
use App\Models\DeliveryCommission;
use App\Models\Merchant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantFinanceTest extends TestCase
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

    private function delivery(array $attributes = []): Delivery
    {
        return Delivery::factory()->create(array_merge([
            'merchant_id' => $this->merchant->id, 'delivery_fee' => '25.50', 'currency' => 'AED', 'status' => 'completed',
        ], $attributes));
    }

    public function test_only_own_priced_deliveries_are_visible_and_read_does_not_calculate_commissions(): void
    {
        $own = $this->delivery();
        $this->delivery(['delivery_fee' => null]);
        Delivery::factory()->create(['delivery_fee' => '99.00']);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/finance')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.delivery_fee', '25.50')->assertJsonPath('data.0.commission', null);
        $this->assertDatabaseCount('delivery_commissions', 0);
    }

    public function test_existing_commission_amounts_are_returned_without_recalculation(): void
    {
        $delivery = $this->delivery();
        DeliveryCommission::create([
            'delivery_id' => $delivery->id, 'merchant_id' => $this->merchant->id,
            'gross_amount' => '25.50', 'commission_rate' => '10.00',
            'platform_amount' => '2.55', 'partner_amount' => '22.95', 'currency' => 'AED', 'status' => 'pending',
        ]);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/finance')
            ->assertOk()->assertJsonPath('data.0.commission.platform_amount', '2.55')
            ->assertJsonPath('data.0.commission.partner_amount', '22.95')
            ->assertJsonPath('data.0.commission.status', 'pending');
        $this->assertDatabaseCount('delivery_commissions', 1);
    }

    public function test_filters_keep_currencies_and_cancelled_deliveries_explicit(): void
    {
        $this->delivery(['status' => 'cancelled', 'currency' => 'AED']);
        $this->delivery(['status' => 'completed', 'currency' => 'USD']);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/finance?status=cancelled&currency=AED')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'cancelled')
            ->assertJsonPath('data.0.currency', 'AED');
    }

    public function test_pagination_is_bounded_and_invalid_filters_are_rejected(): void
    {
        for ($i = 0; $i < 21; $i++) $this->delivery();
        $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/finance')
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/merchant/finance?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/merchant/finance?status=invalid&page=0&currency=INVALID')
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'page', 'currency']);
    }

    public function test_guest_and_pending_accounts_cannot_read_finance(): void
    {
        $this->getJson('/api/v1/merchant/finance')->assertUnauthorized();
        $this->merchant->update(['status' => 'pending']);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/finance')
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_NOT_ACTIVE');
    }
}
