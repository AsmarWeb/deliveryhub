<?php

namespace App\Services\Billing;

use App\Models\Merchant;
use App\Models\MerchantSubscription;

class MerchantSubscriptionAccess
{
    public function allows(?MerchantSubscription $subscription, bool $requireStripe = false): bool
    {
        if (! $subscription || $subscription->status !== 'active' || ! $subscription->plan?->is_active
            || ! $subscription->starts_at || $subscription->starts_at->isFuture()
            || ($subscription->ends_at && ! $subscription->ends_at->isFuture())) {
            return false;
        }
        if (! $subscription->cashier_subscription_id) {
            return ! $requireStripe;
        }

        return $subscription->cashierSubscription !== null
            && app(StripeSubscriptionPolicy::class)->shouldKeepAccess(
                $subscription->cashierSubscription->stripe_status,
                $subscription->ends_at,
                true // The local period is granted only by a confirmed paid invoice.
            );
    }

    public function current(Merchant $merchant, bool $requireStripe = false): ?MerchantSubscription
    {
        return $merchant->merchantSubscriptions()->with(['plan', 'cashierSubscription'])
            ->where('status', 'active')->latest('starts_at')->latest('id')->get()
            ->first(fn ($subscription) => $this->allows($subscription, $requireStripe));
    }
}
