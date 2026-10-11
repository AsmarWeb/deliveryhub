<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laratrust\Models\Role;
use App\Models\Merchant;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
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

        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $user->addRole($role);

        return $user;
    }

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('DeliveryHub');
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->userWithRole('merchant');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->userWithRole('merchant');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }


    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = $this->userWithRole('merchant');

        Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'DeliveryHub Test Merchant',
            'business_email' => 'merchant@example.com',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Total Deliveries')
            ->assertSee('Recent Deliveries');
    }


    public function test_user_without_role_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->userWithRole('merchant');

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
