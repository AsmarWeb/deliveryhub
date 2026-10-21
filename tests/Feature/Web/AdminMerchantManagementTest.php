<?php

namespace Tests\Feature\Web;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratrust\Models\Role;
use Tests\TestCase;

class AdminMerchantManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $merchantUser;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'merchant', 'driver'] as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => ucfirst($name)]
            );
        }

        $this->admin = User::factory()->create();
        $this->admin->addRole('admin');

        $this->merchantUser = User::factory()->create();
        $this->merchantUser->addRole('merchant');

        $this->merchant = Merchant::factory()->create([
            'user_id' => $this->merchantUser->id,
            'business_name' => 'Raslo Test Merchant',
            'business_email' => 'merchant@example.com',
            'business_phone' => '0501234567',
            'status' => 'active',
        ]);
    }

    private function createMerchant(
        string $name,
        string $status = 'active'
    ): Merchant {
        return Merchant::factory()->create([
            'business_name' => $name,
            'status' => $status,
        ]);
    }

    private function createDelivery(
        Merchant $merchant,
        DeliveryStatus $status
    ): Delivery {
        return Delivery::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => $status,
        ]);
    }

    private function statusUrl(Merchant $merchant): string
    {
        return route('admin.merchants.status', $merchant);
    }

    public function test_admin_can_view_merchants(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.merchants.index'))
            ->assertOk()
            ->assertSee('Raslo Test Merchant');
    }

    public function test_guest_cannot_view_merchants(): void
    {
        $this->get(route('admin.merchants.index'))
            ->assertRedirect(route('login'));
    }

    public function test_merchant_cannot_view_admin_merchants(): void
    {
        $this->actingAs($this->merchantUser)
            ->get(route('admin.merchants.index'))
            ->assertForbidden();
    }

    public function test_admin_can_search_merchants_by_business_name(): void
    {
        $this->createMerchant('Ahmed Trading');
        $this->createMerchant('Mohamed Logistics');

        $this->actingAs($this->admin)
            ->get(route('admin.merchants.index', [
                'search' => 'Ahmed Trading',
            ]))
            ->assertOk()
            ->assertSee('Ahmed Trading')
            ->assertDontSee('Mohamed Logistics');
    }

    public function test_admin_can_search_merchants_by_email(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.merchants.index', [
                'search' => 'merchant@example.com',
            ]))
            ->assertOk()
            ->assertSee('Raslo Test Merchant');
    }

    public function test_admin_can_filter_merchants_by_status(): void
    {
        $this->createMerchant('Inactive Business', 'inactive');

        $this->actingAs($this->admin)
            ->get(route('admin.merchants.index', [
                'status' => 'inactive',
            ]))
            ->assertOk()
            ->assertSee('Inactive Business')
            ->assertDontSee('Raslo Test Merchant');
    }

    public function test_admin_can_view_merchant_details(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.merchants.show', $this->merchant))
            ->assertOk()
            ->assertSee('Raslo Test Merchant')
            ->assertSee('merchant@example.com');
    }

    public function test_guest_cannot_view_merchant_details(): void
    {
        $this->get(route('admin.merchants.show', $this->merchant))
            ->assertRedirect(route('login'));
    }

    public function test_merchant_cannot_view_admin_merchant_details(): void
    {
        $this->actingAs($this->merchantUser)
            ->get(route('admin.merchants.show', $this->merchant))
            ->assertForbidden();
    }

    public function test_admin_can_deactivate_merchant_without_deliveries(): void
    {
        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.merchants.index'));

        $this->assertSame(
            'inactive',
            $this->merchant->fresh()->status
        );
    }

    public function test_admin_can_activate_inactive_merchant(): void
    {
        $this->merchant->update([
            'status' => 'inactive',
        ]);

        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.merchants.index'));

        $this->assertSame(
            'active',
            $this->merchant->fresh()->status
        );
    }

    public function test_admin_cannot_deactivate_merchant_with_ongoing_delivery(): void
    {
        $this->createDelivery(
            $this->merchant,
            DeliveryStatus::Assigned
        );

        $this->actingAs($this->admin)
            ->from(route('admin.merchants.index'))
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'inactive',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            'active',
            $this->merchant->fresh()->status
        );
    }

    public function test_completed_delivery_does_not_block_deactivation(): void
    {
        $this->createDelivery(
            $this->merchant,
            DeliveryStatus::Completed
        );

        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.merchants.index'));

        $this->assertSame(
            'inactive',
            $this->merchant->fresh()->status
        );
    }

    public function test_cancelled_delivery_does_not_block_deactivation(): void
    {
        $this->createDelivery(
            $this->merchant,
            DeliveryStatus::Cancelled
        );

        $this->actingAs($this->admin)
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.merchants.index'));

        $this->assertSame(
            'inactive',
            $this->merchant->fresh()->status
        );
    }

    public function test_merchant_cannot_change_merchant_status(): void
    {
        $this->actingAs($this->merchantUser)
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->assertSame(
            'active',
            $this->merchant->fresh()->status
        );
    }

    public function test_guest_cannot_change_merchant_status(): void
    {
        $this->patch($this->statusUrl($this->merchant), [
            'status' => 'inactive',
        ])
            ->assertRedirect(route('login'));

        $this->assertSame(
            'active',
            $this->merchant->fresh()->status
        );
    }

    public function test_invalid_merchant_status_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.merchants.index'))
            ->patch($this->statusUrl($this->merchant), [
                'status' => 'banned',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            'active',
            $this->merchant->fresh()->status
        );
    }

    public function test_merchant_details_show_recent_deliveries(): void
    {
        $delivery = $this->createDelivery(
            $this->merchant,
            DeliveryStatus::Draft
        );

        $this->actingAs($this->admin)
            ->get(route('admin.merchants.show', $this->merchant))
            ->assertOk()
            ->assertSee($delivery->tracking_number);
    }

    public function test_merchant_details_do_not_show_other_merchants_deliveries(): void
    {
        $otherMerchant = $this->createMerchant('Other Business');

        $otherDelivery = $this->createDelivery(
            $otherMerchant,
            DeliveryStatus::Draft
        );

        $this->actingAs($this->admin)
            ->get(route('admin.merchants.show', $this->merchant))
            ->assertOk()
            ->assertDontSee($otherDelivery->tracking_number);
    }
}
