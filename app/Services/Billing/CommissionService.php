<?php

namespace App\Services\Billing;

use App\Models\Delivery;
use App\Models\DeliveryCommission;
use App\Models\MerchantSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function calculate(Delivery $delivery): DeliveryCommission
    {
        return DB::transaction(function () use ($delivery) {
            $delivery = Delivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            $existing = DeliveryCommission::query()
                ->where('delivery_id', $delivery->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $subscription = MerchantSubscription::query()
                ->with('plan')
                ->where('merchant_id', $delivery->merchant_id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->latest('starts_at')
                ->first();

            if (! $subscription || ! $subscription->plan || ! $subscription->plan->is_active) {
                throw ValidationException::withMessages([
                    'subscription' => 'Merchant has no valid active subscription.',
                ]);
            }

            if ($delivery->delivery_fee === null) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'Delivery must be priced before calculating commission.',
                ]);
            }

            if ($delivery->currency !== $subscription->plan->currency) {
                throw ValidationException::withMessages([
                    'currency' => 'Delivery and subscription currencies do not match.',
                ]);
            }

            $rate = (string) $subscription->plan->commission_rate;

            if (! preg_match('/^\d{1,3}\.\d{2}$/', $rate)) {
                throw ValidationException::withMessages([
                    'commission_rate' => 'Invalid commission rate.',
                ]);
            }

            [$wholeRate, $fractionRate] = explode('.', $rate);
            $rateBasisPoints = ((int) $wholeRate * 100) + (int) $fractionRate;

            if ($rateBasisPoints > 10000) {
                throw ValidationException::withMessages([
                    'commission_rate' => 'Commission cannot exceed 100%.',
                ]);
            }

            $amount = (string) $delivery->delivery_fee;

            if (! preg_match('/^\d+\.\d{2}$/', $amount)) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'Invalid delivery amount.',
                ]);
            }

            [$wholeAmount, $fractionAmount] = explode('.', $amount);

            if (strlen($wholeAmount) > 10) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'Delivery amount is too large.',
                ]);
            }

            $grossCents = ((int) $wholeAmount * 100) + (int) $fractionAmount;

            if ($grossCents <= 0) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'Delivery amount must be greater than zero.',
                ]);
            }

            // Round half up to the nearest cent.
            $platformCents = intdiv(
                ($grossCents * $rateBasisPoints) + 5000,
                10000
            );

            $partnerCents = $grossCents - $platformCents;

            $money = static fn (int $cents): string =>
                sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);

            return DeliveryCommission::create([
                'delivery_id' => $delivery->id,
                'merchant_id' => $delivery->merchant_id,
                'merchant_subscription_id' => $subscription->id,
                'gross_amount' => $money($grossCents),
                'commission_rate' => $rate,
                'platform_amount' => $money($platformCents),
                'partner_amount' => $money($partnerCents),
                'currency' => $delivery->currency,
                'status' => 'pending',
            ]);
        });
    }
}
