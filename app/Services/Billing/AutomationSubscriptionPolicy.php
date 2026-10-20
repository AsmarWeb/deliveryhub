<?php

namespace App\Services\Billing;

use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\RecurringDelivery;
use Illuminate\Validation\ValidationException;

class AutomationSubscriptionPolicy
{
    public function activeSubscription(Merchant $merchant): MerchantSubscription
    {
        $subscription = MerchantSubscription::query()
            ->with(['plan', 'cashierSubscription'])
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('id')
            ->first();

        if (! $subscription || ! $subscription->plan?->is_active) {
            throw ValidationException::withMessages([
                'subscription' => 'An active subscription is required.',
            ]);
        }

        if (
            ! $subscription->cashierSubscription ||
            ! $subscription->cashierSubscription->valid()
        ) {
            throw ValidationException::withMessages([
                'subscription' => 'A valid Stripe subscription is required.',
            ]);
        }

        return $subscription;
    }

    public function assertCanCreate(Merchant $merchant): void
    {
        $subscription = $this->activeSubscription($merchant);

        $limit = (int) $subscription->plan->max_active_automations;

        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'automation' => 'Automation is not included in your plan.',
            ]);
        }

        $activeCount = RecurringDelivery::query()
            ->where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->count();

        if ($activeCount >= $limit) {
            throw ValidationException::withMessages([
                'automation' => 'Your automation limit has been reached.',
            ]);
        }
    }
}
