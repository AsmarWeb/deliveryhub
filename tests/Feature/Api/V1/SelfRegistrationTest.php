<?php

namespace Tests\Feature\Api\V1;

use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class SelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'merchant', 'driver'] as $name) {
            Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        }
    }

    private function merchantPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Merchant',
            'email' => 'newmerchant@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'business_name' => 'Test Trading LLC',
            'business_phone' => '+971501234567',
        ], $overrides);
    }

    private function driverPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Driver',
            'email' => 'newdriver@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'phone' => '+971501234567',
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'DXB-12345',
            'license_number' => 'LIC-12345',
        ], $overrides);
    }

    public function test_merchant_registration_creates_pending_profile_without_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register/merchant', $this->merchantPayload());
        $response->assertCreated()->assertJsonPath('status', 'pending')->assertJsonMissingPath('token');

        $user = User::where('email', 'newmerchant@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('merchant'));
        $this->assertDatabaseHas('merchants', [
            'user_id' => $user->id,
            'business_name' => 'Test Trading LLC',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_driver_registration_creates_pending_profile_without_operational_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register/driver', $this->driverPayload());
        $response->assertCreated()->assertJsonPath('status', 'pending')->assertJsonMissingPath('token');

        $user = User::where('email', 'newdriver@example.com')->firstOrFail();
        $this->assertFalse($user->hasRole('driver'));
        $this->assertDatabaseHas('drivers', [
            'user_id' => $user->id,
            'status' => 'pending',
            'is_available' => 0,
        ]);
    }

    public function test_duplicate_email_is_rejected_without_extra_profile(): void
    {
        User::factory()->create(['email' => 'newmerchant@example.com']);
        $this->postJson('/api/v1/auth/register/merchant', $this->merchantPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('merchants', 0);
    }

    public function test_invalid_driver_phone_is_rejected_without_creating_user(): void
    {
        $this->postJson('/api/v1/auth/register/driver', $this->driverPayload(['phone' => '123']))
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertDatabaseMissing('users', ['email' => 'newdriver@example.com']);
    }

    public function test_pending_merchant_cannot_use_operational_dashboard(): void
    {
        $this->postJson('/api/v1/auth/register/merchant', $this->merchantPayload())->assertCreated();
        $user = User::where('email', 'newmerchant@example.com')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_NOT_ACTIVE');
    }

    public function test_pending_driver_cannot_use_operational_dashboard(): void
    {
        $this->postJson('/api/v1/auth/register/driver', $this->driverPayload())->assertCreated();
        $user = User::where('email', 'newdriver@example.com')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_NOT_ACTIVE');
    }

    public function test_admin_approval_grants_driver_role_and_repeat_approval_is_safe(): void
    {
        $admin = User::factory()->create();
        $admin->addRole('admin');
        $this->postJson('/api/v1/auth/register/driver', $this->driverPayload())->assertCreated();
        $driver = Driver::whereHas('user', fn ($q) => $q->where('email', 'newdriver@example.com'))->firstOrFail();

        $url = route('admin.drivers.status', $driver);
        $this->actingAs($admin)->patch($url, ['status' => 'active'])
            ->assertRedirect(route('admin.drivers.index'));
        $this->assertSame('active', $driver->fresh()->status);
        $this->assertTrue($driver->user->fresh()->hasRole('driver'));

        $this->actingAs($admin)->patch($url, ['status' => 'active'])
            ->assertRedirect(route('admin.drivers.index'));
        $this->assertTrue($driver->user->fresh()->hasRole('driver'));
    }

    public function test_old_registration_route_must_not_create_profileless_merchant(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Legacy Applicant',
            'email' => 'legacy@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        // Expected once legacy registration is closed or redirected to full onboarding.
        $this->assertNotEquals(201, $response->status(), 'Legacy route still creates a merchant without a profile.');
        $this->assertDatabaseMissing('users', ['email' => 'legacy@example.com']);
    }
}
