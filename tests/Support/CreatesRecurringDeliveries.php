<?php

namespace Tests\Support;

use App\Models\Address;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\RecurringDelivery;
use App\Models\SubscriptionPlan;

trait CreatesRecurringDeliveries
{
    private function schedule(array $attributes = []): RecurringDelivery
    {
        $merchant = Merchant::factory()->create(['status' => 'active']);
        $pickup = Address::factory()->create(['merchant_id' => $merchant->id]);
        $dropoff = Address::factory()->create(['merchant_id' => $merchant->id]);

        return RecurringDelivery::create(array_merge([
            'merchant_id' => $merchant->id,
            'pickup_address_id' => $pickup->id,
            'dropoff_address_id' => $dropoff->id,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '0501234567',
            'frequency' => 'daily',
            'timezone' => 'Asia/Dubai',
            'execution_time' => '13:00',
            'next_run_at' => now(),
            'is_active' => true,
        ], $attributes));
    }

    private function subscribe(Merchant $merchant, int $limit = 5): MerchantSubscription
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Test',
            'slug' => 'test-'.$merchant->id,
            'monthly_price' => 99,
            'currency' => 'AED',
            'commission_rate' => 10,
            'max_active_automations' => $limit,
            'is_active' => true,
        ]);
        $stripe = $merchant->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.$merchant->id,
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        return MerchantSubscription::create([
            'merchant_id' => $merchant->id,
            'subscription_plan_id' => $plan->id,
            'cashier_subscription_id' => $stripe->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
