<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRecurringDeliveries;
use Tests\TestCase;

class RecurringDeliverySubscriptionTest extends TestCase
{
    use RefreshDatabase, CreatesRecurringDeliveries;

    private string $endpoint = '/api/v1/merchant/automations';

    private function loginMerchant($schedule): void
    {
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $schedule->merchant->user->addRole('merchant');
        $this->actingAs($schedule->merchant->user, 'sanctum');
    }

    public function test_guest_cannot_access_automations(): void
    {
        $this->getJson($this->endpoint)->assertUnauthorized();
    }

    public function test_creation_requires_a_subscription(): void
    {
        $schedule = $this->schedule();
        $this->loginMerchant($schedule);
        $this->postJson($this->endpoint, [])->assertUnprocessable()
            ->assertJsonValidationErrors('subscription');
    }

    public function test_reactivation_respects_active_automation_limit(): void
    {
        $schedule = $this->schedule();
        $this->subscribe($schedule->merchant, 1);
        $inactive = $schedule->replicate();
        $inactive->is_active = false;
        $inactive->save();
        $this->loginMerchant($schedule);

        $this->patchJson($this->endpoint.'/'.$inactive->id.'/status', ['is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('automation');
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_other_merchants_cannot_toggle_a_schedule(): void
    {
        $schedule = $this->schedule();
        $other = $this->schedule();
        $this->loginMerchant($schedule);
        $this->patchJson($this->endpoint.'/'.$other->id.'/status', ['is_active' => false])
            ->assertForbidden();
    }

    public function test_valid_subscriber_can_create_an_automation(): void
    {
        $schedule = $this->schedule();
        $this->subscribe($schedule->merchant);
        $this->loginMerchant($schedule);
        $this->postJson($this->endpoint, [
            'pickup_address_id' => $schedule->pickup_address_id,
            'dropoff_address_id' => $schedule->dropoff_address_id,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '0501234567',
            'frequency' => 'daily',
            'timezone' => 'Asia/Dubai',
            'execution_time' => '14:00',
        ])->assertCreated()->assertJsonPath('data.is_active', true);
    }
}
