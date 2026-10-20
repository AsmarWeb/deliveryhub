<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeBillingController extends Controller
{
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'in:growth,business'],
        ]);

        $merchant = Merchant::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $merchant) {
            return response()->json([
                'message' => 'Merchant account not found.',
            ], 403);
        }

        if ($merchant->status !== 'active') {
            return response()->json([
                'message' => 'Merchant account is not active.',
            ], 403);
        }

        $plan = SubscriptionPlan::query()
            ->where('slug', $data['plan'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json([
                'message' => 'Subscription plan is unavailable.',
            ], 404);
        }

        $priceId = config('services.stripe.prices.' . $plan->slug);

        if (! is_string($priceId) || ! str_starts_with($priceId, 'price_')) {
            return response()->json([
                'message' => 'Stripe price is not configured.',
            ], 503);
        }

        if (! config('cashier.secret')) {
            return response()->json([
                'message' => 'Stripe is not configured.',
            ], 503);
        }

        if ($merchant->subscribed('default')) {
            return response()->json([
                'message' => 'An existing Stripe subscription must be managed before starting another.',
            ], 409);
        }

        try {
            $checkout = $merchant
                ->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => config('app.url') . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => config('app.url') . '/billing/cancel',
                ]);

            return response()->json([
                'checkout_url' => $checkout->url,
            ]);
        } catch (Throwable $exception) {
            Log::error('Stripe checkout creation failed', [
                'merchant_id' => $merchant->id,
                'plan_id' => $plan->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'Unable to create checkout session.',
            ], 502);
        }
    }

    public function plans(\Illuminate\Http\Request $request)
    {
        $plans = \App\Models\SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get([
                'id',
                'name',
                'slug',
                'monthly_price',
                'currency',
                'commission_rate',
                'monthly_delivery_limit',
            ]);

        return response()->json([
            'data' => $plans,
        ]);
    }

    public function current(\Illuminate\Http\Request $request)
    {
        $merchant = \App\Models\Merchant::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $merchant) {
            return response()->json([
                'message' => 'Merchant account not found.',
            ], 403);
        }

        $subscription = \App\Models\MerchantSubscription::query()
            ->with('plan:id,name,slug,monthly_price,currency,commission_rate,monthly_delivery_limit')
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderByDesc('ends_at')
            ->first();

        return response()->json([
            'data' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'plan' => $subscription->plan,
                'provider' => $subscription->cashier_subscription_id
                    ? 'stripe'
                    : 'manual',
            ] : null,
        ]);
    }


    public function portal(\Illuminate\Http\Request $request)
    {
        $merchant = \App\Models\Merchant::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $merchant->stripe_id) {
            return response()->json([
                'message' => 'No Stripe billing account found.',
            ], 422);
        }

        try {
            $session = $merchant->billingPortalUrl(
                config('app.url')
            );

            return response()->json([
                'portal_url' => $session,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to open billing portal.',
            ], 503);
        }
    }

}
