<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPlan;
use Laravel\Cashier\Cashier;

class StripePlanPrice
{
    public function verify(SubscriptionPlan $plan, string $priceId): bool
    {
        return $this->matches($plan, Cashier::stripe()->prices->retrieve($priceId)->toArray());
    }

    public function matches(SubscriptionPlan $plan, array $price): bool
    {
        $amount = (string) $plan->monthly_price;
        if (! preg_match('/^(\d+)\.(\d{2})$/', $amount, $parts)) {
            return false;
        }

        return ($price['active'] ?? false) === true
            && strtolower($price['currency'] ?? '') === strtolower($plan->currency)
            && ($price['unit_amount'] ?? null) === ((int) $parts[1] * 100 + (int) $parts[2])
            && ($price['recurring']['interval'] ?? null) === 'month'
            && ($price['recurring']['interval_count'] ?? null) === 1;
    }
}
