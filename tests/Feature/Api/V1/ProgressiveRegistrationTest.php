<?php

namespace Tests\Feature\Api\V1;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class ProgressiveRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'merchant', 'driver'] as $name) {
            Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        }
    }

    public function test_driver_can_register_without_vehicle_details_and_complete_own_account(): void
    {
        $this->postJson('/api/v1/auth/register/driver', [
            'name' => 'New Partner', 'email' => 'partner@example.com',
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
            'phone' => '+971501234567',
        ])->assertCreated()->assertJsonPath('status', 'pending')->assertJsonMissingPath('token');
        $user = User::where('email', 'partner@example.com')->firstOrFail();
        $this->assertFalse($user->hasRole('driver'));
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/account')
            ->assertOk()->assertJsonCount(3, 'data.missing_fields');
        $this->patchJson('/api/v1/auth/account', [
            'vehicle_type' => 'motorcycle', 'vehicle_plate' => 'Dubai A 12345', 'license_number' => 'LIC-123',
        ])->assertOk()->assertJsonPath('data.missing_fields', [])->assertJsonPath('data.status', 'pending');
        $this->assertFalse($user->fresh()->hasRole('driver'));
        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_account_changes_cannot_target_another_user_or_grant_operational_access(): void
    {
        $own = Driver::factory()->create(['status' => 'pending']);
        $other = Driver::factory()->create(['status' => 'pending', 'license_number' => 'OTHER']);
        $this->actingAs($own->user, 'sanctum')->patchJson('/api/v1/auth/account', [
            'user_id' => $other->user_id, 'status' => 'active', 'roles' => ['driver'],
        ])->assertUnprocessable();
        $this->assertSame('pending', $own->fresh()->status);
        $this->assertSame('OTHER', $other->fresh()->license_number);
    }

    public function test_reviewed_vehicle_details_cannot_be_changed_without_review(): void
    {
        $driver = Driver::factory()->create(['status' => 'active', 'license_number' => 'APPROVED']);
        $this->actingAs($driver->user, 'sanctum')->patchJson('/api/v1/auth/account', [
            'license_number' => 'DIFFERENT', 'name' => 'Should not persist',
        ])->assertUnprocessable()->assertJsonValidationErrors('license_number');
        $this->assertSame('APPROVED', $driver->fresh()->license_number);
        $this->assertNotSame('Should not persist', $driver->user->fresh()->name);
    }

    public function test_account_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/account')->assertUnauthorized();
        $this->patchJson('/api/v1/auth/account', ['name' => 'Test'])->assertUnauthorized();
    }

    public function test_empty_details_do_not_allow_driver_approval(): void
    {
        $admin = User::factory()->create();
        $admin->addRole('admin');
        $driver = Driver::factory()->create(['status' => 'pending', 'vehicle_plate' => null]);
        $this->actingAs($admin)->patch(route('admin.drivers.status', $driver), ['status' => 'active'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $driver->fresh()->status);
        $this->assertFalse($driver->user->fresh()->hasRole('driver'));
    }
}
