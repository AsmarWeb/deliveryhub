<?php

namespace App\Services\Billing;

use App\Models\MerchantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;

class StripeSubscriptionSyncService
{
    public function sync(Subscription $subscription, bool $paymentConfirmed = false): void
    {
        // Retrieve Stripe information before acquiring database locks.
        $subscription = Subscription::query()
            ->with('items')
            ->find($subscription->id);

        if (! $subscription || ! $subscription->owner) {
            return;
        }

        $prices = array_filter([
            config('services.stripe.prices.growth') => 'growth',
            config('services.stripe.prices.business') => 'business',
        ]);

        $slug = $prices[$subscription->stripe_price] ?? null;

        if (! $slug) {
            return;
        }

        $plan = SubscriptionPlan::query()
            ->where('slug', $slug)
            ->first();

        if (! $plan) {
            return;
        }

        // A paid invoice is required to activate or extend paid access.
        if (! $paymentConfirmed) {
            return;
        }

        if ($subscription->stripe_status !== 'active') {
            return;
        }

        $periodStart = $subscription->currentPeriodStart();
        $periodEnd = $subscription->currentPeriodEnd();

        if (! $periodStart || ! $periodEnd || $periodEnd->isPast()) {
            return;
        }

        DB::transaction(function () use (
            $subscription,
            $plan,
            $periodStart,
            $periodEnd
        ) {
            $merchantId = $subscription->owner->id;

            $existing = MerchantSubscription::query()
                ->where('cashier_subscription_id', $subscription->id)
                ->lockForUpdate()
                ->first();

            // Never shorten an already confirmed paid period.
            if (
                $existing &&
                $existing->ends_at &&
                Carbon::parse($existing->ends_at)->greaterThan($periodEnd)
            ) {
                return;
            }

            MerchantSubscription::query()
                ->where('merchant_id', $merchantId)
                ->where('status', 'active')
                ->where(function ($query) use ($existing) {
                    if ($existing) {
                        $query->where('id', '!=', $existing->id);
                    }
                })
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            MerchantSubscription::query()->updateOrCreate(
                ['cashier_subscription_id' => $subscription->id],
                [
                    'merchant_id' => $merchantId,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => $periodStart,
                    'ends_at' => $periodEnd,
                    'cancelled_at' => null,
                ]
            );
        });
    }
}
