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
        $subscription = app(MerchantSubscriptionAccess::class)->current($merchant, true);
        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'A confirmed paid Stripe subscription is required.',
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
