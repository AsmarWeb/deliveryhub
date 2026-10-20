<?php

namespace App\Services\Billing;

use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantSubscriptionService
{
    public function activate(
        Merchant $merchant,
        SubscriptionPlan $plan,
        int $months = 1
    ): MerchantSubscription {
        if ($months < 1 || $months > 12) {
            throw ValidationException::withMessages([
                'months' => 'Subscription duration must be between 1 and 12 months.',
            ]);
        }

        return DB::transaction(function () use ($merchant, $plan, $months) {
            $merchant = Merchant::query()
                ->lockForUpdate()
                ->findOrFail($merchant->id);

            $plan = SubscriptionPlan::query()
                ->findOrFail($plan->id);

            if (! $plan->is_active) {
                throw ValidationException::withMessages([
                    'plan' => 'This subscription plan is not available.',
                ]);
            }

            if ($plan->slug === 'enterprise') {
                throw ValidationException::withMessages([
                    'plan' => 'Enterprise subscriptions require a custom contract.',
                ]);
            }

            $now = now();

            MerchantSubscription::query()
                ->where('merchant_id', $merchant->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'ends_at' => $now,
                ]);

            return MerchantSubscription::create([
                'merchant_id' => $merchant->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $now->copy()->addMonthsNoOverflow($months),
            ]);
        });
    }
}
