<?php

namespace Tests\Feature\Web;

use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role)]
        );

        $user = User::factory()->create();
        $user->addRole($role);

        return $user;
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Total Deliveries')
            ->assertSee('Available Drivers');
    }

    public function test_merchant_can_view_dashboard(): void
    {
        $user = $this->userWithRole('merchant');

        Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Test Merchant',
            'business_email' => 'merchant@example.com',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Recent Deliveries')
            ->assertDontSee('Available Drivers');
    }

    public function test_driver_can_view_dashboard(): void
    {
        $user = $this->userWithRole('driver');

        Driver::create([
            'user_id' => $user->id,
            'phone' => '0501234567',
            'status' => 'active',
            'is_available' => true,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Pending Assignments')
            ->assertSee('Available')
            ->assertDontSee('Total Merchants');
    }

    public function test_merchant_without_profile_returns_404(): void
    {
        $user = $this->userWithRole('merchant');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertNotFound();
    }

    public function test_driver_without_profile_returns_404(): void
    {
        $user = $this->userWithRole('driver');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertNotFound();
    }
}
