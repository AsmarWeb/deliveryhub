<?php

namespace App\Listeners;

use App\Services\Billing\StripeSubscriptionSyncService;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Subscription;

class SyncStripeMerchantSubscription
{
    public function __construct(
        private readonly StripeSubscriptionSyncService $sync
    ) {}

    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $type = $payload['type'] ?? null;

        // Subscription events alone do not confirm payment.
        if ($type !== 'invoice.paid') {
            return;
        }

        $invoice = $payload['data']['object'] ?? [];

        if (($invoice['status'] ?? null) !== 'paid') {
            return;
        }

        // Support the invoice.subscription field used by earlier Stripe APIs.
        $stripeId = $invoice['subscription'] ?? null;

        // Stripe's newer invoice structure can place subscription here.
        if (! $stripeId) {
            $stripeId = $invoice['parent']['subscription_details']['subscription']
                ?? null;
        }

        if (! is_string($stripeId) || $stripeId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_id', $stripeId)
            ->first();

        if (! $subscription) {
            // Stripe may deliver invoice.paid before subscription.created.
            // A non-2xx response makes Stripe retry after Cashier has stored it.
            throw new \RuntimeException('Subscription not yet available; retry invoice webhook.');
        }

        $this->sync->sync($subscription, true, $invoice);
    }
}
