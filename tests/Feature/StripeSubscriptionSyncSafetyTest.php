<?php

namespace Tests\Feature;

use App\Models\MerchantSubscription;
use App\Services\Billing\StripeSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Mockery;
use Tests\TestCase;

class StripeSubscriptionSyncSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_cashier_subscription_does_not_create_a_local_subscription(): void
    {
        $subscription = new Subscription();
        $subscription->id = 999999;

        app(StripeSubscriptionSyncService::class)->sync($subscription);

        $this->assertDatabaseCount('merchant_subscriptions', 0);
    }

    public function test_unknown_subscription_does_not_modify_existing_records(): void
    {
        $before = MerchantSubscription::count();

        $subscription = new Subscription();
        $subscription->id = 999999;

        app(StripeSubscriptionSyncService::class)->sync($subscription);

        $this->assertSame($before, MerchantSubscription::count());
    }
}
