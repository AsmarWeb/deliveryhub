<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Services\Billing\AutomationSubscriptionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomationSubscriptionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_without_subscription_cannot_create_automation(): void
    {
        $merchant = Merchant::factory()->create();

        $this->expectException(ValidationException::class);

        app(AutomationSubscriptionPolicy::class)
            ->assertCanCreate($merchant);
    }
}
