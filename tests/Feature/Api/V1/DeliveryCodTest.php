<?php

namespace Tests\Feature\Api\V1;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\ProofOfDelivery;
use App\Models\Role;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryCodTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['merchant', 'driver'] as $role) Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);
        $this->merchant = Merchant::factory()->create(['status' => 'active']);
        $this->merchant->user->addRole('merchant');
        $this->driver = Driver::factory()->create(['status' => 'active']);
        $this->driver->user->addRole('driver');
    }

    private function delivery(string $status = 'delivered'): Delivery
    {
        $delivery = Delivery::factory()->create(['merchant_id' => $this->merchant->id, 'status' => $status, 'delivery_fee' => '20.00']);
        $delivery->codRecord()->create(['expected_amount' => '125.50', 'currency' => 'AED', 'status' => 'expected']);
        DeliveryAssignment::create(['delivery_id' => $delivery->id, 'driver_id' => $this->driver->id, 'status' => 'accepted']);
        return $delivery;
    }

    private function proof(Delivery $delivery): void
    {
        ProofOfDelivery::create(['delivery_id' => $delivery->id, 'driver_id' => $this->driver->id,
            'recipient_name' => 'Recipient', 'photo_path' => 'proof/test.jpg', 'confirmed_at' => now()]);
    }

    private function url(Delivery $delivery, string $action = ''): string
    {
        return '/api/v1/deliveries/'.$delivery->id.'/cod'.($action ? '/'.$action : '');
    }

    public function test_creation_records_goods_amount_without_changing_delivery_fees_and_zero_stays_legacy(): void
    {
        $address = Address::factory()->create(['merchant_id' => $this->merchant->id]);
        $data = ['pickup_address_id' => $address->id, 'dropoff_address_id' => $address->id,
            'recipient_name' => 'Buyer', 'recipient_phone' => '+971501234567', 'cod_amount' => '125.50'];
        $response = $this->actingAs($this->merchant->user, 'sanctum')->postJson('/api/v1/deliveries', $data)->assertCreated();
        $delivery = Delivery::findOrFail($response->json('data.id'));
        $this->assertSame('125.50', $delivery->codRecord->expected_amount);
        $this->assertNull($delivery->delivery_fee);
        $this->assertSame('expected', $delivery->codRecord->status);
        $response = $this->postJson('/api/v1/deliveries', array_merge($data, ['cod_amount' => '0.00']))->assertCreated();
        $this->assertNull(Delivery::findOrFail($response->json('data.id'))->codRecord);
        $this->postJson('/api/v1/deliveries', array_merge($data, ['cod_amount' => '-1']))->assertUnprocessable()->assertJsonValidationErrors('cod_amount');
        $this->postJson('/api/v1/deliveries', array_merge($data, ['cod_amount' => '1.001']))->assertUnprocessable()->assertJsonValidationErrors('cod_amount');
    }

    public function test_driver_collection_and_merchant_receipt_are_explicit_and_idempotent(): void
    {
        $delivery = $this->delivery();
        $this->proof($delivery);
        $this->actingAs($this->driver->user, 'sanctum')->postJson($this->url($delivery, 'collect'), ['confirmed' => true, 'expected_amount' => '1.00'])
            ->assertOk()->assertJsonPath('data.status', 'collected')->assertJsonPath('data.expected_amount', '125.50');
        $collectedAt = $delivery->codRecord()->firstOrFail()->collected_at->toIso8601String();
        $this->travel(1)->minutes();
        $this->postJson($this->url($delivery, 'collect'), ['confirmed' => true])->assertOk();
        $record = $delivery->codRecord()->firstOrFail();
        $this->assertSame($collectedAt, $record->collected_at->toIso8601String());
        $this->assertSame($this->driver->user_id, $record->collected_by);
        $this->actingAs($this->merchant->user, 'sanctum')->postJson($this->url($delivery, 'receive'), ['confirmed' => true])
            ->assertOk()->assertJsonPath('data.status', 'received');
        $receivedAt = $delivery->codRecord()->firstOrFail()->received_at->toIso8601String();
        $this->travel(1)->minutes();
        $this->postJson($this->url($delivery, 'receive'), ['confirmed' => true])->assertOk();
        $record = $delivery->codRecord()->firstOrFail();
        $this->assertSame($receivedAt, $record->received_at->toIso8601String());
        $this->assertSame($this->merchant->user_id, $record->received_by);
        $this->assertSame('20.00', $delivery->fresh()->delivery_fee);
    }

    public function test_proof_or_completion_does_not_automatically_confirm_cash(): void
    {
        $delivery = $this->delivery('completed');
        $this->proof($delivery);
        $this->assertSame('expected', $delivery->codRecord->status);
        $this->actingAs($this->driver->user, 'sanctum')->postJson($this->url($delivery, 'collect'), ['confirmed' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson($this->url($delivery, 'collect'), ['confirmed' => true])->assertOk();
    }

    public function test_collecting_before_delivery_or_without_own_proof_is_rejected(): void
    {
        $delivery = $this->delivery('in_transit');
        $this->actingAs($this->driver->user, 'sanctum')->postJson($this->url($delivery, 'collect'), ['confirmed' => true])->assertUnprocessable();
        $delivery->update(['status' => 'delivered']);
        $this->postJson($this->url($delivery, 'collect'), ['confirmed' => true])->assertUnprocessable();
        $this->assertSame('expected', $delivery->codRecord()->first()->status);
    }

    public function test_wrong_merchant_driver_and_actor_roles_cannot_mutate_cash(): void
    {
        $delivery = $this->delivery();
        $this->proof($delivery);
        $other = Merchant::factory()->create(['status' => 'active']);
        $other->user->addRole('merchant');
        $this->actingAs($other->user, 'sanctum')->getJson($this->url($delivery))->assertNotFound();
        $this->postJson($this->url($delivery, 'receive'), ['confirmed' => true])->assertNotFound();
        $driver = Driver::factory()->create(['status' => 'active']);
        $driver->user->addRole('driver');
        $this->actingAs($driver->user, 'sanctum')->postJson($this->url($delivery, 'collect'), ['confirmed' => true])->assertNotFound();
        $this->actingAs($this->merchant->user, 'sanctum')->postJson($this->url($delivery, 'collect'), ['confirmed' => true])->assertNotFound();
        $this->actingAs($this->driver->user, 'sanctum')->postJson($this->url($delivery, 'receive'), ['confirmed' => true])->assertNotFound();
    }

    public function test_merchant_cannot_confirm_receipt_before_driver_collection(): void
    {
        $delivery = $this->delivery();
        $this->actingAs($this->merchant->user, 'sanctum')->postJson($this->url($delivery, 'receive'), ['confirmed' => true])->assertUnprocessable();
        $this->assertNull($delivery->codRecord()->first()->received_at);
    }

    public function test_guest_and_inactive_merchant_cannot_access_cash_records(): void
    {
        $delivery = $this->delivery();
        $this->getJson($this->url($delivery))->assertUnauthorized();
        $this->merchant->update(['status' => 'inactive']);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson($this->url($delivery))->assertForbidden();
        $this->postJson($this->url($delivery, 'receive'), ['confirmed' => true])->assertForbidden();
        $this->assertSame('expected', $delivery->codRecord()->first()->status);
    }

    public function test_cancellation_closes_uncollected_cash_and_legacy_orders_return_null(): void
    {
        $delivery = $this->delivery('matching');
        app(DeliveryTransitionService::class)->transition($delivery, DeliveryStatus::Cancelled, $this->merchant->user);
        $this->assertSame('cancelled', $delivery->codRecord()->first()->status);
        $legacy = Delivery::factory()->create(['merchant_id' => $this->merchant->id]);
        $this->actingAs($this->merchant->user, 'sanctum')->getJson($this->url($legacy))->assertOk()->assertJsonPath('data', null);
    }
}
