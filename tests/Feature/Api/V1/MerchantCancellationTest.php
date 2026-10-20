<?php

namespace Tests\Feature\Api\V1;

use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryCommission;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantCancellationTest extends TestCase
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
        return Delivery::factory()->create(array_merge(['merchant_id' => $this->merchant->id, 'status' => 'matching'], $attributes));
    }

    private function cancel(Delivery $delivery, string $reason = 'Customer requested cancellation')
    {
        return $this->actingAs($this->merchant->user, 'sanctum')->postJson('/api/v1/deliveries/'.$delivery->id.'/cancel', ['reason' => $reason]);
    }

    public function test_cancellation_updates_snapshot_and_history_once_and_preserves_original_reason(): void
    {
        $delivery = $this->delivery(['delivery_fee' => '40.00']);
        DeliveryCommission::create(['delivery_id' => $delivery->id, 'merchant_id' => $this->merchant->id,
            'gross_amount' => '40.00', 'commission_rate' => '10.00', 'platform_amount' => '4.00',
            'partner_amount' => '36.00', 'currency' => 'AED', 'status' => 'pending']);
        $this->cancel($delivery)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->cancel($delivery, 'Different reason on retry')->assertOk();
        $this->assertSame(1, $delivery->statusHistories()->count());
        $history = $delivery->statusHistories()->firstOrFail();
        $this->assertSame('Customer requested cancellation', $history->reason);
        $this->assertSame($this->merchant->user_id, $history->user_id);
        $this->assertDatabaseHas('delivery_commissions', ['delivery_id' => $delivery->id, 'status' => 'cancelled', 'platform_amount' => 4, 'earned_at' => null]);
    }

    public function test_allowed_pre_assignment_states_can_be_cancelled_without_creating_commissions(): void
    {
        foreach (['draft', 'pending_pricing', 'priced', 'matching', 'expired'] as $status) {
            $this->cancel($this->delivery(['status' => $status]))->assertOk()->assertJsonPath('data.status', 'cancelled');
        }
        $this->assertDatabaseCount('delivery_commissions', 0);
    }

    public function test_other_merchant_and_guest_cannot_cancel(): void
    {
        $other = Delivery::factory()->create(['status' => 'matching']);
        $this->postJson('/api/v1/deliveries/'.$other->id.'/cancel', ['reason' => 'Test cancellation'])->assertUnauthorized();
        $this->cancel($other)->assertNotFound();
        $this->assertSame('matching', $other->fresh()->status->value);
    }

    public function test_reason_is_required_and_operational_states_are_protected(): void
    {
        $delivery = $this->delivery();
        $this->cancel($delivery, '  ')->assertUnprocessable()->assertJsonValidationErrors('reason');
        foreach (['assigned', 'accepted', 'pickup', 'in_transit', 'delivered', 'completed'] as $status) {
            $protected = $this->delivery(['status' => $status]);
            $this->cancel($protected)->assertUnprocessable()->assertJsonValidationErrors('delivery');
            $this->assertSame($status, $protected->fresh()->status->value);
        }
    }

    public function test_active_assignment_or_previous_pickup_prevents_cancellation(): void
    {
        $delivery = $this->delivery();
        DeliveryAssignment::create(['delivery_id' => $delivery->id, 'driver_id' => Driver::factory()->create()->id, 'status' => 'pending']);
        $this->cancel($delivery)->assertUnprocessable();
        $this->assertSame('pending', $delivery->assignments()->first()->status);
        $pickedUp = $this->delivery(['picked_up_at' => now()]);
        $this->cancel($pickedUp)->assertUnprocessable();
    }
}
