<?php

namespace Tests\Feature\Web;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $merchantUser;
    private User $driverUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        foreach (['admin', 'merchant', 'driver'] as $role) {
            Role::firstOrCreate(
                ['name' => $role],
                ['display_name' => ucfirst($role)]
            );
        }

        $this->admin = $this->createUser('admin');
        $this->merchantUser = $this->createUser('merchant');
        $this->driverUser = $this->createUser('driver');
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create();

        $user->addRole($role);

        return $user;
    }

    private function url(): string
    {
        return route('admin.dashboard');
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get($this->url())
            ->assertOk()
            ->assertSee('Total Deliveries')
            ->assertSee('Available Drivers')
            ->assertSee('Recent Deliveries');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url())
            ->assertRedirect(route('login'));
    }

    public function test_merchant_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->merchantUser)
            ->get($this->url())
            ->assertForbidden();
    }

    public function test_driver_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->driverUser)
            ->get($this->url())
            ->assertForbidden();
    }

    public function test_user_without_role_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->url())
            ->assertForbidden();
    }

    public function test_dashboard_displays_real_statistics(): void
    {
        $merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'business_name' => 'DeliveryHub Test Merchant',
            'business_email' => 'dashboard-merchant@example.com',
            'status' => 'active',
        ]);

        Driver::create([
            'user_id' => $this->driverUser->id,
            'phone' => '0501234567',
            'status' => 'active',
            'is_available' => true,
        ]);

        Delivery::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => DeliveryStatus::Draft,
        ]);

        Delivery::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => DeliveryStatus::InTransit,
        ]);

        Delivery::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => DeliveryStatus::Completed,
        ]);

        $response = $this->actingAs($this->admin)
            ->get($this->url());

        $response->assertOk();

        $response->assertViewHas('stats', function ($stats) {
            return $stats['total_deliveries'] === 3
                && $stats['active_deliveries'] === 1
                && $stats['completed_deliveries'] === 1
                && $stats['total_merchants'] === 1
                && $stats['total_drivers'] === 1
                && $stats['available_drivers'] === 1;
        });

        $response->assertViewHas('statusCounts', function ($counts) {
            return (int) $counts->get('draft') === 1
                && (int) $counts->get('in_transit') === 1
                && (int) $counts->get('completed') === 1;
        });

        $response->assertViewHas('recentDeliveries', function ($deliveries) {
            return $deliveries->count() === 3
                && $deliveries->every(
                    fn ($delivery) => $delivery->relationLoaded('merchant')
                );
        });

        $response->assertSee('DeliveryHub Test Merchant');
    }
}
