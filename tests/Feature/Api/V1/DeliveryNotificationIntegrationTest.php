<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryNotificationIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_status_transition_creates_notification_after_commit(): void
    {
        $user = User::factory()->create();

        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Integration Merchant',
            'business_email' => 'integration@example.com',
            'status' => 'active',
        ]);

        $address = new Address();

        $values = [
            'label' => 'Test Address',
            'type' => 'pickup',
            'contact_name' => 'Test Contact',
            'contact_phone' => '0501111111',
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'area' => 'Al Quoz',
            'address_line' => 'Al Quoz, Dubai',
            'address_line_1' => 'Al Quoz, Dubai',
            'street_address' => 'Al Quoz, Dubai',
            'address' => 'Al Quoz, Dubai',
            'latitude' => 25.185,
            'longitude' => 55.275,
        ];

        $address->forceFill(
            array_intersect_key(
                $values,
                array_flip(Schema::getColumnListing('addresses'))
            )
        );

        $address->merchant_id = $merchant->id;
        $address->save();

        $delivery = Delivery::create([
            'merchant_id' => $merchant->id,
            'pickup_address_id' => $address->id,
            'dropoff_address_id' => $address->id,
            'tracking_number' => 'DH-INTEGRATION-001',
            'status' => DeliveryStatus::Draft,
            'recipient_name' => 'Test Recipient',
            'recipient_phone' => '0503333333',
            'package_type' => 'parcel',
            'weight' => 1.5,
        ]);

        $service = app(DeliveryTransitionService::class);

        $service->transition(
            $delivery,
            DeliveryStatus::PendingPricing,
            $user
        );

        $this->assertSame(
            0,
            $user->notifications()->count()
        );

        $service->transition(
            $delivery,
            DeliveryStatus::Priced,
            $user
        );

        $service->transition(
            $delivery,
            DeliveryStatus::Matching,
            $user
        );

        // Matching -> Assigned is a supported transition.
        $service->transition(
            $delivery,
            DeliveryStatus::Assigned,
            $user
        );

        $this->assertSame(
            1,
            $user->notifications()->count()
        );

        $this->assertSame(
            'assigned',
            $user->notifications()->first()->data['status']
        );
    }
}
