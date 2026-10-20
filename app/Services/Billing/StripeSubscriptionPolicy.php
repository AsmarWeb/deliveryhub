<?php

namespace App\Services\Billing;

use Carbon\CarbonInterface;

class StripeSubscriptionPolicy
{
    public function shouldActivate(
        string $status,
        ?CarbonInterface $periodEnd,
        bool $paymentConfirmed
    ): bool {
        return $status === 'active'
            && $paymentConfirmed
            && $periodEnd !== null
            && $periodEnd->isFuture();
    }

    public function shouldKeepAccess(
        string $status,
        ?CarbonInterface $periodEnd,
        bool $previouslyPaid
    ): bool {
        if (! $previouslyPaid || ! $periodEnd?->isFuture()) {
            return false;
        }

        return in_array($status, [
            'active',
            'past_due',
            'canceled',
        ], true);
    }
}
