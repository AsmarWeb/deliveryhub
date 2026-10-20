<?php

namespace Tests\Feature\Billing;

use App\Models\Delivery;
use App\Models\MerchantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function pricedDelivery(): Delivery
    {
        return Delivery::factory()->create([
            'delivery_fee' => '40.00',
            'currency' => 'AED',
        ]);
    }

    private function activateSubscription(Delivery $delivery): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => '99.00',
            'currency' => 'AED',
            'commission_rate' => '10.00',
            'is_active' => true,
        ]);

        MerchantSubscription::create([
            'merchant_id' => $delivery->merchant_id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_it_calculates_platform_commission(): void
    {
        $delivery = $this->pricedDelivery();
        $this->activateSubscription($delivery);

        $commission = app(CommissionService::class)->calculate($delivery);

        $this->assertSame('40.00', $commission->gross_amount);
        $this->assertSame('10.00', $commission->commission_rate);
        $this->assertSame('4.00', $commission->platform_amount);
        $this->assertSame('36.00', $commission->partner_amount);
        $this->assertSame('pending', $commission->status);
    }

    public function test_it_does_not_duplicate_commissions(): void
    {
        $delivery = $this->pricedDelivery();
        $this->activateSubscription($delivery);

        $service = app(CommissionService::class);

        $first = $service->calculate($delivery);
        $second = $service->calculate($delivery);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('delivery_commissions', 1);
    }

    public function test_it_requires_an_active_subscription(): void
    {
        $delivery = $this->pricedDelivery();

        $this->expectException(ValidationException::class);

        app(CommissionService::class)->calculate($delivery);
    }
}
