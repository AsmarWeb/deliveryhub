<?php

namespace App\Services\Billing;

use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;

class StripeSubscriptionSyncService
{
    public function sync(Subscription $subscription, bool $paymentConfirmed = false, array $invoice = []): void
    {
        // Retrieve Stripe information before acquiring database locks.
        $subscription = Subscription::query()
            ->with('items')
            ->find($subscription->id);

        if (! $subscription || ! $subscription->owner) {
            return;
        }

        if (config('services.stripe.prices.growth') === config('services.stripe.prices.business')) {
            throw new \RuntimeException('Stripe plan prices must be distinct.');
        }

        $prices = [];
        foreach (['growth', 'business', 'enterprise'] as $slug) {
            $price = config('services.stripe.prices.'.$slug);
            if (is_string($price) && str_starts_with($price, 'price_')) {
                if (isset($prices[$price])) {
                    throw new \RuntimeException('Stripe plan prices must be distinct.');
                }
                $prices[$price] = $slug;
            }
        }

        $slug = $prices[$subscription->stripe_price] ?? null;
        if (($invoice['customer'] ?? null) !== $subscription->owner->stripe_id) {
            return;
        }

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

        if (in_array($subscription->stripe_status, ['incomplete', 'trialing'], true)) {
            throw new \RuntimeException('Subscription state is not ready; retry paid invoice.');
        }

        if (! in_array($subscription->stripe_status, ['active', 'past_due', 'canceled'], true)
            || ($invoice['status'] ?? null) !== 'paid'
            || strtolower($invoice['currency'] ?? '') !== strtolower($plan->currency)) {
            return;
        }

        // Grant only the period on the confirmed invoice, never the current
        // remote period: a delayed old invoice must not grant an unpaid renewal.
        $period = app(StripeInvoicePeriod::class)->forPrice($invoice, $subscription->stripe_price);
        if ($period === null) {
            // A plan-change invoice may arrive before subscription.updated.
            // Retry when Cashier is behind Stripe; ignore an old invoice only
            // when the local current price already matches authoritative state.
            $remotePrice = app(StripeSubscriptionState::class)->currentPrice($subscription->stripe_id);
            if ($remotePrice !== $subscription->stripe_price) {
                throw new \RuntimeException('Subscription price update pending; retry paid invoice.');
            }

            return;
        }
        [$periodStart, $periodEnd] = $period;

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
            // Serialize concurrent invoices across subscriptions for one merchant.
            Merchant::query()->whereKey($merchantId)->lockForUpdate()->firstOrFail();

            if (MerchantSubscription::where('merchant_id', $merchantId)
                ->where('status', 'active')->where('starts_at', '>', $periodStart)->exists()) {
                return;
            }

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
