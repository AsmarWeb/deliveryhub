<?php

namespace Tests\Feature\Api\V1;

use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeBillingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/v1/merchant/billing/checkout';

    public function test_guest_cannot_create_checkout(): void
    {
        $this->postJson($this->endpoint, [
            'plan' => 'growth',
        ])->assertUnauthorized();
    }

    public function test_user_without_merchant_cannot_create_checkout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->endpoint, [
                'plan' => 'growth',
            ])
            ->assertForbidden();
    }

    public function test_invalid_plan_is_rejected(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(
            ['name' => 'merchant'],
            ['display_name' => 'Merchant']
        );

        $user->addRole('merchant');

        Merchant::query()->create([
            'user_id' => $user->id,
            'business_name' => 'Test Merchant',
            'business_email' => 'merchant@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->endpoint, [
                'plan' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_inactive_merchant_cannot_create_checkout(): void
    {
        $user = User::factory()->create();

        Merchant::query()->create([
            'user_id' => $user->id,
            'business_name' => 'Inactive Merchant',
            'business_email' => 'inactive@example.test',
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->endpoint, [
                'plan' => 'growth',
            ])
            ->assertForbidden();
    }

    public function test_missing_subscription_plan_returns_not_found(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(
            ['name' => 'merchant'],
            ['display_name' => 'Merchant']
        );

        $user->addRole('merchant');

        Merchant::query()->create([
            'user_id' => $user->id,
            'business_name' => 'Active Merchant',
            'business_email' => 'active@example.test',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->endpoint, [
                'plan' => 'growth',
            ])
            ->assertNotFound();
    }
}
