<?php

namespace Tests\Feature;

use App\Listeners\SyncStripeMerchantSubscription;
use App\Services\Billing\StripeSubscriptionSyncService;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookHandled;
use Tests\TestCase;

class StripeSubscriptionWebhookTest extends TestCase
{
    public function test_webhook_listener_is_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(WebhookHandled::class)
        );
    }

    public function test_unrelated_webhook_does_not_trigger_subscription_sync(): void
    {
        $service = $this->mock(StripeSubscriptionSyncService::class);

        $service->shouldNotReceive('sync');

        $listener = new SyncStripeMerchantSubscription($service);

        $listener->handle(new WebhookHandled([
            'type' => 'customer.updated',
            'data' => [
                'object' => ['id' => 'cus_example'],
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_subscription_webhook_without_id_is_ignored(): void
    {
        $service = $this->mock(StripeSubscriptionSyncService::class);

        $service->shouldNotReceive('sync');

        $listener = new SyncStripeMerchantSubscription($service);

        $listener->handle(new WebhookHandled([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => []],
        ]));

        $this->assertTrue(true);
    }
}
