<?php
namespace Tests\Feature\Dispatch;

use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use App\Services\Deliveries\DeliveryAssignmentService;
use App\Services\Deliveries\DriverAssignmentService;
use App\Services\Dispatch\AutoDispatcher;
use App\Services\Dispatch\DriverMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoDispatchTest extends TestCase
{
    use RefreshDatabase;
    private Merchant $merchant;
    protected function setUp(): void
    {
        parent::setUp();
        config(['dispatch.enabled' => true, 'dispatch.ai_enabled' => false]);
        Http::preventStrayRequests();
        foreach (['admin', 'merchant', 'driver'] as $name) Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        $this->merchant = Merchant::factory()->create(['status' => 'active']);
        $this->merchant->user->addRole('merchant');
    }
    private function driver(array $data = []): Driver
    {
        $driver = Driver::factory()->create(array_merge(['status' => 'active', 'is_available' => true,
            'max_active_deliveries' => 1, 'dispatch_enabled' => true, 'dispatch_seen_at' => now(), 'dispatch_location_at' => now(),
            'dispatch_latitude' => 25.201, 'dispatch_longitude' => 55.27], $data));
        $driver->user->addRole('driver');
        return $driver;
    }
    private function delivery(array $data = []): Delivery
    {
        $pickup = Address::factory()->create(['merchant_id' => $this->merchant->id, 'latitude' => 25.2, 'longitude' => 55.27]);
        return Delivery::factory()->create(array_merge(['merchant_id' => $this->merchant->id, 'pickup_address_id' => $pickup->id,
            'status' => 'matching', 'delivery_fee' => '25.00'], $data));
    }
    public function test_nearby_driver_is_offered_automatically_without_duplicates_or_overbooking(): void
    {
        $driver = $this->driver(); $first = $this->delivery(); $second = $this->delivery();
        $counts = app(AutoDispatcher::class)->run();
        $this->assertSame(1, $counts['assigned']);
        $this->assertSame('assigned', $first->fresh()->status->value);
        $this->assertSame('matching', $second->fresh()->status->value);
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $first->id, 'driver_id' => $driver->id, 'status' => 'pending']);
        app(AutoDispatcher::class)->run();
        $this->assertDatabaseCount('delivery_assignments', 1);
        $this->assertTrue($first->statusHistories()->latest('id')->first()->metadata['automatic']);
        Http::assertNothingSent();
    }
    public function test_stale_offline_opted_out_full_or_far_drivers_are_not_selected(): void
    {
        $this->driver(['dispatch_seen_at' => now()->subMinutes(4)]);
        $this->driver(['dispatch_location_at' => now()->subMinutes(6)]);
        $this->driver(['is_available' => false]);
        $this->driver(['dispatch_enabled' => false]);
        $this->driver(['max_active_deliveries' => 0]);
        $this->driver(['dispatch_latitude' => 24.45, 'dispatch_longitude' => 54.38]);
        $delivery = $this->delivery();
        $this->assertSame(0, app(AutoDispatcher::class)->run()['assigned']);
        $this->assertSame('matching', $delivery->fresh()->status->value);
    }
    public function test_missing_pickup_location_leaves_delivery_waiting(): void
    {
        $this->driver(); $delivery = $this->delivery();
        $delivery->pickupAddress->update(['latitude' => null, 'longitude' => null]);
        $this->assertSame(0, app(AutoDispatcher::class)->run()['assigned']);
        $this->assertDatabaseCount('delivery_assignments', 0);
    }
    public function test_rejection_retries_another_driver_without_reoffering_to_the_same_driver(): void
    {
        $first = $this->driver(); $second = $this->driver(['dispatch_latitude' => 25.21]); $delivery = $this->delivery();
        app(AutoDispatcher::class)->run();
        $assignment = DeliveryAssignment::where('delivery_id', $delivery->id)->firstOrFail();
        app(DriverAssignmentService::class)->reject($assignment, $first, $first->user, 'Unable to accept');
        app(AutoDispatcher::class)->run();
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $delivery->id, 'driver_id' => $first->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $delivery->id, 'driver_id' => $second->id, 'status' => 'pending']);
    }
    public function test_timed_out_auto_offer_retries_but_manual_offers_are_preserved(): void
    {
        $first = $this->driver(); $second = $this->driver(['dispatch_latitude' => 25.21]); $delivery = $this->delivery();
        app(AutoDispatcher::class)->run();
        $this->travel(3)->minutes();
        foreach ([$first, $second] as $driver) $driver->update(['dispatch_seen_at' => now(), 'dispatch_location_at' => now()]);
        $counts = app(AutoDispatcher::class)->run();
        $this->assertSame(1, $counts['expired']);
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $delivery->id, 'driver_id' => $first->id, 'status' => 'expired']);
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $delivery->id, 'driver_id' => $second->id, 'status' => 'pending']);
        $admin = User::factory()->create(); $admin->addRole('admin');
        $manual = $this->delivery();
        app(DeliveryAssignmentService::class)->assign($manual, $first, $admin);
        $this->travel(3)->minutes();
        app(AutoDispatcher::class)->run();
        $this->assertSame('pending', $manual->assignments()->first()->status);
    }
    public function test_ai_may_choose_only_a_candidate_and_failure_falls_back_to_rules(): void
    {
        config(['dispatch.ai_enabled' => true, 'services.deliveryhub.ai_api_key' => 'test-key']);
        $first = $this->driver(); $second = $this->driver(['dispatch_latitude' => 25.21]); $delivery = $this->delivery();
        Http::fake(['api.openai.com/*' => Http::response(['output_text' => json_encode(['driver_id' => 999999])])]);
        app(AutoDispatcher::class)->run();
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $delivery->id, 'driver_id' => $first->id]);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);
            $this->assertSame(['driver_id', 'distance_km', 'active_tasks', 'capacity'], array_keys($input[0]));
            $this->assertFalse($request['store']);
            return true;
        });
    }
    public function test_valid_ai_recommendation_is_recorded_but_hard_constraints_still_apply(): void
    {
        config(['dispatch.ai_enabled' => true, 'services.deliveryhub.ai_api_key' => 'test-key']);
        $this->driver(); $second = $this->driver(['dispatch_latitude' => 25.21]); $delivery = $this->delivery();
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['driver_id' => $second->id])]]]]])]);
        app(AutoDispatcher::class)->run();
        $this->assertDatabaseHas('delivery_assignments', ['delivery_id' => $delivery->id, 'driver_id' => $second->id]);
        $this->assertSame('ai', $delivery->statusHistories()->latest('id')->first()->metadata['dispatch_method']);
    }
    public function test_driver_presence_is_opt_in_and_merchants_get_no_precise_location_or_contacts(): void
    {
        $driver = $this->driver(['dispatch_enabled' => false]);
        $this->actingAs($driver->user, 'sanctum')->postJson('/api/v1/driver/dispatch-presence', [
            'enabled' => true, 'latitude' => 25.201, 'longitude' => 55.27, 'accuracy' => 10, 'recorded_at' => now()->toIso8601String(),
        ])->assertOk();
        $response = $this->actingAs($this->merchant->user, 'sanctum')->getJson('/api/v1/merchant/available-drivers')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(['id', 'display_name', 'vehicle_type'], array_keys($response->json('data.0')));
        $this->postJson('/api/v1/driver/dispatch-presence', ['enabled' => false])->assertForbidden();
        $this->actingAs($driver->user, 'sanctum')->postJson('/api/v1/driver/dispatch-presence', ['enabled' => false])->assertOk();
        $this->assertFalse($driver->fresh()->dispatch_enabled);
        $this->assertNull($driver->fresh()->dispatch_latitude);
    }
    public function test_invalid_or_old_presence_is_rejected_and_disabled_automation_does_not_assign(): void
    {
        $driver = $this->driver();
        $this->actingAs($driver->user, 'sanctum')->postJson('/api/v1/driver/dispatch-presence', [
            'latitude' => 91, 'longitude' => 55, 'accuracy' => 10, 'recorded_at' => now()->subHour()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'recorded_at']);
        $this->delivery(); config(['dispatch.enabled' => false]);
        $this->assertSame(0, app(AutoDispatcher::class)->run()['assigned']);
    }
    public function test_writer_rechecks_changed_driver_availability_after_candidate_ranking(): void
    {
        $driver = $this->driver(); $delivery = $this->delivery();
        $this->assertCount(1, app(DriverMatcher::class)->candidates($delivery));
        $driver->update(['is_available' => false]);
        try {
            app(DeliveryAssignmentService::class)->assignAutomatically($delivery, $driver, 'rules');
            $this->fail('Changed driver eligibility must be rejected.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertDatabaseCount('delivery_assignments', 0);
            $this->assertSame('matching', $delivery->fresh()->status->value);
        }
    }
    public function test_accepted_automatic_offer_is_not_expired(): void
    {
        $driver = $this->driver(); $delivery = $this->delivery();
        app(AutoDispatcher::class)->run();
        $assignment = $delivery->assignments()->firstOrFail();
        app(DriverAssignmentService::class)->accept($assignment, $driver, $driver->user);
        $this->travel(3)->minutes();
        $this->assertSame(0, app(AutoDispatcher::class)->run()['expired']);
        $this->assertSame('accepted', $assignment->fresh()->status);
        $this->assertSame('accepted', $delivery->fresh()->status->value);
    }
    public function test_future_scheduled_deliveries_are_not_dispatched_early(): void
    {
        $this->driver(); $delivery = $this->delivery(['scheduled_at' => now()->addHour()]);
        $this->assertSame(0, app(AutoDispatcher::class)->run()['assigned']);
        $this->assertSame('matching', $delivery->fresh()->status->value);
    }
    public function test_waiting_orders_do_not_starve_newer_matchable_orders(): void
    {
        $this->driver();
        for ($i = 0; $i < 25; $i++) { $delivery = $this->delivery(); $delivery->pickupAddress->update(['latitude' => null]); }
        $new = $this->delivery();
        app(AutoDispatcher::class)->run();
        $this->assertSame('matching', $new->fresh()->status->value);
        app(AutoDispatcher::class)->run();
        $this->assertSame('assigned', $new->fresh()->status->value);
    }
}
