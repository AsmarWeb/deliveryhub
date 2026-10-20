<?php

namespace Tests\Unit\Billing;

use App\Services\Billing\StripeSubscriptionPolicy;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Carbon;

class StripeSubscriptionPolicyTest extends TestCase
{
    private StripeSubscriptionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new StripeSubscriptionPolicy();
    }

    public function test_confirmed_payment_activates_subscription(): void
    {
        $this->assertTrue(
            $this->policy->shouldActivate(
                'active',
                Carbon::now()->addMonth(),
                true
            )
        );
    }

    public function test_unconfirmed_payment_does_not_activate_subscription(): void
    {
        $this->assertFalse(
            $this->policy->shouldActivate(
                'active',
                Carbon::now()->addMonth(),
                false
            )
        );
    }

    public function test_trial_does_not_count_as_paid_subscription(): void
    {
        $this->assertFalse(
            $this->policy->shouldActivate(
                'trialing',
                Carbon::now()->addMonth(),
                false
            )
        );
    }

    public function test_renewal_requires_future_period(): void
    {
        $this->assertFalse(
            $this->policy->shouldActivate(
                'active',
                Carbon::now()->subDay(),
                true
            )
        );
    }

    public function test_cancelled_subscription_keeps_previously_paid_access(): void
    {
        $this->assertTrue(
            $this->policy->shouldKeepAccess(
                'canceled',
                Carbon::now()->addDays(10),
                true
            )
        );
    }

    public function test_failed_renewal_can_keep_previously_paid_access(): void
    {
        $this->assertTrue(
            $this->policy->shouldKeepAccess(
                'past_due',
                Carbon::now()->addDays(5),
                true
            )
        );
    }

    public function test_expired_subscription_loses_access(): void
    {
        $this->assertFalse(
            $this->policy->shouldKeepAccess(
                'canceled',
                Carbon::now()->subDay(),
                true
            )
        );
    }

    public function test_unpaid_subscription_never_receives_access(): void
    {
        $this->assertFalse(
            $this->policy->shouldKeepAccess(
                'past_due',
                Carbon::now()->addMonth(),
                false
            )
        );
    }
}
