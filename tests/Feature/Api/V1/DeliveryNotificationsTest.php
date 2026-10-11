<?php

namespace Tests\Feature\Api\V1;

use App\Events\DeliveryStatusChanged;
use App\Listeners\SendDeliveryStatusNotifications;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laratrust\Models\Role;
use Tests\TestCase;

class DeliveryNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $merchantUser;
    private User $driverUser;
    private User $unrelatedUser;
    private Merchant $merchant;
    private Driver $driver;
    private Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['merchant', 'driver'] as $name) {
            Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        }

        $this->merchantUser = User::factory()->create();
        $this->merchantUser->addRole('merchant');
        $this->driverUser = User::factory()->create();
        $this->driverUser->addRole('driver');
        $this->unrelatedUser = User::factory()->create();

        $this->merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'business_name' => 'Notify Merchant',
            'business_email' => 'notify-merchant@example.com',
            'status' => 'active',
        ]);
        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'phone' => '0504444444',
            'status' => 'active',
            'is_available' => true,
        ]);

        $pickup = $this->address('Pickup', 'Al Quoz');
        $dropoff = $this->address('Dropoff', 'Business Bay');
        $response = $this->actingAs($this->merchantUser)->postJson('/api/v1/deliveries', [
            'pickup_address_id' => $pickup->id,
            'dropoff_address_id' => $dropoff->id,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '0503333333',
            'package_type' => 'parcel',
            'weight' => 1.5,
        ])->assertCreated();
        $this->delivery = Delivery::findOrFail($response->json('data.id'));
    }

    private function address(string $label, string $area): Address
    {
        $values = [
            'label' => $label,
            'type' => strtolower($label),
            'contact_name' => 'Test contact',
            'contact_phone' => '0501111111',
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'area' => $area,
            'address_line' => "$area, Dubai",
            'address_line_1' => "$area, Dubai",
            'street_address' => "$area, Dubai",
            'address' => "$area, Dubai",
            'latitude' => 25.185,
            'longitude' => 55.275,
        ];
        $address = new Address();
        $address->forceFill(array_intersect_key($values, array_flip(Schema::getColumnListing('addresses'))));
        $address->merchant_id = $this->merchant->id;
        $address->save();
        return $address;
    }

    private function eventFor(string $status): DeliveryStatusChanged
    {
        $history = $this->delivery->statusHistories()->create([
            'user_id' => $this->merchantUser->id,
            'from_status' => 'draft',
            'to_status' => $status,
            'reason' => 'Test transition',
        ]);
        return new DeliveryStatusChanged($this->delivery, $history);
    }

    public function test_assigned_notifies_merchant_and_pending_driver_only(): void
    {
        DeliveryAssignment::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $this->driver->id,
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        (new SendDeliveryStatusNotifications())->handle($this->eventFor('assigned'));

        $this->assertSame(1, $this->merchantUser->notifications()->count());
        $this->assertSame(1, $this->driverUser->notifications()->count());
        $this->assertSame(0, $this->unrelatedUser->notifications()->count());
        $this->assertSame('assigned', $this->driverUser->notifications()->first()->data['status']);
    }

    public function test_repeat_event_does_not_duplicate_notifications(): void
    {
        $event = $this->eventFor('delivered');
        $listener = new SendDeliveryStatusNotifications();
        $listener->handle($event);
        $listener->handle($event);
        $this->assertSame(1, $this->merchantUser->notifications()->count());
        $this->assertSame('delivered', $this->merchantUser->notifications()->first()->data['status']);
    }

    public function test_unlisted_status_creates_no_notifications(): void
    {
        (new SendDeliveryStatusNotifications())->handle($this->eventFor('priced'));
        $this->assertSame(0, $this->merchantUser->notifications()->count());
        $this->assertSame(0, $this->driverUser->notifications()->count());
    }
}
