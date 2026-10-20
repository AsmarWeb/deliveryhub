<?php

namespace App\Services\Billing;

use Laravel\Cashier\Cashier;

class StripeSubscriptionState
{
    public function currentPrice(string $stripeId): ?string
    {
        $remote = Cashier::stripe()->subscriptions->retrieve($stripeId)->toArray();
        $items = $remote['items']['data'] ?? [];

        return count($items) === 1 ? ($items[0]['price']['id'] ?? null) : null;
    }
}
