<?php

namespace Tests\Feature\Billing;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryCommission;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Services\Billing\CommissionService;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;
    private SubscriptionPlan $plan;
    private MerchantSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $this->merchant = Merchant::factory()->create(['status' => 'active']);
        $this->merchant->user->addRole('merchant');
        $this->plan = SubscriptionPlan::create([
            'name' => 'Runtime plan', 'slug' => 'runtime', 'monthly_price' => '99.00',
            'currency' => 'AED', 'commission_rate' => '10.00', 'is_active' => true,
        ]);
        $this->subscription = MerchantSubscription::create([
            'merchant_id' => $this->merchant->id, 'subscription_plan_id' => $this->plan->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
    }

    private function delivery(array $attributes = []): Delivery
    {
        return Delivery::factory()->create(array_merge([
            'merchant_id' => $this->merchant->id, 'status' => 'priced', 'delivery_fee' => '40.00', 'currency' => 'AED',
        ], $attributes));
    }

    private function approve(Delivery $delivery)
    {
        return $this->actingAs($this->merchant->user, 'sanctum')
            ->postJson('/api/v1/deliveries/'.$delivery->id.'/approve-price');
    }

    public function test_approval_creates_one_pending_snapshot_and_repeat_does_not_duplicate_it(): void
    {
        $delivery = $this->delivery();
        $this->approve($delivery)->assertOk();
        $this->assertDatabaseHas('delivery_commissions', [
            'delivery_id' => $delivery->id, 'status' => 'pending', 'gross_amount' => 40,
            'platform_amount' => 4, 'partner_amount' => 36,
        ]);
        $this->approve($delivery)->assertUnprocessable();
        $this->assertDatabaseCount('delivery_commissions', 1);
        $this->assertSame(DeliveryStatus::Matching, $delivery->fresh()->status);
    }

    public function test_missing_valid_subscription_rolls_back_approval_and_history(): void
    {
        $this->subscription->update(['ends_at' => now()->subMinute()]);
        $delivery = $this->delivery();
        $this->approve($delivery)->assertUnprocessable()->assertJsonValidationErrors('subscription');
        $this->assertSame(DeliveryStatus::Priced, $delivery->fresh()->status);
        $this->assertSame(0, $delivery->statusHistories()->count());
        $this->assertDatabaseCount('delivery_commissions', 0);
    }

    public function test_completion_earns_the_original_snapshot_after_the_plan_changes(): void
    {
        $delivery = $this->delivery();
        $this->approve($delivery)->assertOk();
        $this->plan->update(['commission_rate' => '25.00']);
        $this->subscription->update(['ends_at' => now()->subMinute()]);
        $delivery->update(['status' => DeliveryStatus::Delivered]);
        app(DeliveryTransitionService::class)->transition($delivery, DeliveryStatus::Completed, $this->merchant->user);
        $commission = DeliveryCommission::where('delivery_id', $delivery->id)->firstOrFail();
        $this->assertSame('earned', $commission->status);
        $this->assertNotNull($commission->earned_at);
        $this->assertSame('10.00', $commission->commission_rate);
        $this->assertSame('4.00', $commission->platform_amount);
        $this->assertSame($commission->id, app(CommissionService::class)->calculate($delivery)->id);
    }

    public function test_cancellation_keeps_amounts_but_never_earns_the_snapshot(): void
    {
        $delivery = $this->delivery();
        $this->approve($delivery)->assertOk();
        app(DeliveryTransitionService::class)->transition($delivery, DeliveryStatus::Cancelled, $this->merchant->user);
        $commission = DeliveryCommission::where('delivery_id', $delivery->id)->firstOrFail();
        $this->assertSame('cancelled', $commission->status);
        $this->assertNull($commission->earned_at);
        $this->assertSame('4.00', $commission->platform_amount);
    }

    public function test_zero_fee_is_supported_by_the_existing_pricing_contract(): void
    {
        $delivery = $this->delivery(['delivery_fee' => '0.00']);
        $this->approve($delivery)->assertOk();
        $commission = DeliveryCommission::where('delivery_id', $delivery->id)->firstOrFail();
        $this->assertSame('0.00', $commission->platform_amount);
        $this->assertSame('0.00', $commission->partner_amount);
    }

    public function test_fractional_commission_rounds_to_cents_without_losing_the_fee(): void
    {
        $delivery = $this->delivery(['delivery_fee' => '0.05']);
        $this->approve($delivery)->assertOk();
        $commission = DeliveryCommission::where('delivery_id', $delivery->id)->firstOrFail();
        $this->assertSame('0.01', $commission->platform_amount);
        $this->assertSame('0.04', $commission->partner_amount);
    }

    public function test_legacy_completion_does_not_fabricate_a_historical_commission(): void
    {
        $delivery = $this->delivery(['status' => DeliveryStatus::Delivered]);
        app(DeliveryTransitionService::class)->transition($delivery, DeliveryStatus::Completed, $this->merchant->user);
        $this->assertDatabaseCount('delivery_commissions', 0);
    }

    public function test_mismatched_existing_snapshot_blocks_price_approval(): void
    {
        $delivery = $this->delivery();
        app(CommissionService::class)->calculate($delivery);
        $delivery->update(['delivery_fee' => '50.00']);
        $this->approve($delivery)->assertUnprocessable()->assertJsonValidationErrors('commission');
        $this->assertSame(DeliveryStatus::Priced, $delivery->fresh()->status);
        $this->assertSame(0, $delivery->statusHistories()->count());
    }
}
