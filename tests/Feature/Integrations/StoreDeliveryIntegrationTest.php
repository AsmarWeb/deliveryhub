<?php

namespace Tests\Feature\Integrations;

use App\Enums\DeliveryStatus;
use App\Jobs\SendIntegrationDeliveryWebhook;
use App\Models\CatalogConnection;
use App\Models\Delivery;
use App\Models\IntegrationDeliveryOrder;
use App\Models\IntegrationDeliveryWebhook;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Services\Catalog\CatalogFeedClient;
use App\Services\Deliveries\DeliveryTransitionService;
use App\Services\Integrations\DeliveryWebhookOutbox;
use App\Services\Integrations\DeliveryWebhookTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32)), 'catalog.feed_hosts' => ['shop.example'],
            'delivery_integrations.public_url' => 'https://raslo.example']);
    }

    private function connection(bool $webhook = true): array
    {
        $merchant = Merchant::factory()->create(['status' => 'active']);
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $merchant->user->addRole('merchant');
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'enterprise'], ['name' => 'Enterprise', 'monthly_price' => 999, 'currency' => 'AED', 'commission_rate' => 5, 'is_active' => true]);
        $stripe = $merchant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_'.Str::random(20), 'stripe_status' => 'active', 'stripe_price' => 'price_enterprise', 'quantity' => 1]);
        MerchantSubscription::create(['merchant_id' => $merchant->id, 'subscription_plan_id' => $plan->id, 'cashier_subscription_id' => $stripe->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $pickup = $merchant->addresses()->create(['label' => 'Store', 'country' => 'United Arab Emirates', 'city' => 'Dubai', 'address_line' => 'Warehouse 12, Al Quoz', 'emirate' => 'dubai']);
        $key = 'raslo_delivery_'.bin2hex(random_bytes(32));
        $connection = CatalogConnection::create(['merchant_id' => $merchant->id, 'name' => 'Shop', 'mode' => 'feed', 'status' => 'active', 'scopes' => ['catalog:read'], 'key_hash' => hash('sha256', Str::random(40)), 'key_prefix' => 'raslo_live_',
            'delivery_enabled' => true, 'delivery_pickup_address_id' => $pickup->id, 'delivery_key_hash' => hash('sha256', $key), 'delivery_webhook_url' => $webhook ? 'https://shop.example/wp-json/raslo/v1/delivery-events' : null,
            'delivery_webhook_secret' => $webhook ? 'private-webhook-signing-secret' : null]);

        return [$connection, $key];
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive(['external_order_id' => 'wc:101', 'payment_status' => 'paid', 'currency' => 'AED',
            'recipient' => ['contact_name' => 'Private Customer', 'contact_phone' => '+971501234567', 'city' => 'Dubai', 'emirate' => 'dubai', 'address_line' => 'Private Tower, apartment 8'],
            'package_description' => '2 physical items', 'instructions' => 'Call on arrival'], $overrides);
    }

    private function send(string $key, array $payload = [], string $idempotency = 'order-101-idempotency')
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$key, 'Idempotency-Key' => $idempotency])->postJson('/api/integrations/v1/deliveries', $payload ?: $this->payload());
    }

    public function test_create_uses_existing_delivery_workflow_without_accepting_price_or_ownership_injection(): void
    {
        [$c,$key] = $this->connection();
        $response = $this->send($key, $this->payload() + ['delivery_fee' => 0, 'status' => 'matching', 'merchant_id' => 999])->assertCreated()
            ->assertJsonPath('data.status', 'pending_pricing')->assertJsonPath('data.external_order_id', 'wc:101');
        $delivery = Delivery::sole();
        $this->assertSame($c->merchant_id, $delivery->merchant_id);
        $this->assertNull($delivery->delivery_fee);
        $this->assertSame($c->delivery_pickup_address_id, $delivery->pickup_address_id);
        $this->assertNull($delivery->codRecord);
        $this->assertDatabaseCount('integration_delivery_orders', 1);
        $this->assertDatabaseCount('delivery_status_histories', 2);
        $this->assertDatabaseCount('integration_delivery_webhooks', 1);
        $this->assertStringNotContainsString('Private Customer', DB::table('integration_delivery_orders')->value('payload'));
        $this->assertStringNotContainsString('private-webhook-signing-secret', $response->getContent());
    }

    public function test_exact_retries_reuse_delivery_and_changed_key_conflicts(): void
    {
        [$c,$key] = $this->connection();
        $first = $this->send($key)->assertCreated()->json('data.id');
        $this->send($key)->assertOk()->assertJsonPath('data.id', $first)->assertJsonPath('created', false);
        $this->send($key, [], 'different-idempotency-key')->assertStatus(409);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertSame(2, $c->merchant->addresses()->count());
    }

    public function test_changed_payload_or_reused_key_for_another_order_conflicts_without_side_effects(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $this->send($key, $this->payload(['recipient' => ['address_line' => 'Changed address']]))->assertStatus(409);
        $this->send($key, $this->payload(['external_order_id' => 'wc:102']))->assertStatus(409);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('integration_delivery_webhooks', 1);
    }

    public function test_catalog_key_disabled_and_expired_connections_cannot_create(): void
    {
        [$c,$key] = $this->connection();
        $this->send('raslo_live_'.str_repeat('a', 64))->assertUnauthorized();
        $c->update(['delivery_enabled' => false]);
        $this->send($key)->assertUnauthorized();
        $c->update(['delivery_enabled' => true, 'status' => 'disabled']);
        $this->send($key)->assertUnauthorized();
        $c->update(['status' => 'active']);
        $c->merchant->merchantSubscriptions()->update(['ends_at' => now()->subSecond()]);
        $this->send($key)->assertForbidden();
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_invalid_currency_payment_address_and_missing_idempotency_are_rejected(): void
    {
        [$c,$key] = $this->connection();
        foreach ([['currency' => 'USD'], ['payment_status' => 'unpaid'], ['recipient' => ['contact_phone' => '0501234567']], ['recipient' => ['emirate' => 'other']], ['recipient' => ['merchant_id' => 999]], ['recipient' => ['latitude' => 1, 'longitude' => 55]]] as $invalid) {
            $this->send($key, $this->payload($invalid))->assertUnprocessable();
        }
        $this->withHeaders(['Authorization' => 'Bearer '.$key, 'Idempotency-Key' => 'bad'])->postJson('/api/integrations/v1/deliveries', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_missing_pickup_is_atomic_and_cross_connection_reads_are_hidden(): void
    {
        [$c,$key] = $this->connection();
        [$foreign,$otherKey] = $this->connection();
        $id = $this->send($key)->assertCreated()->json('data.id');
        $this->withToken($otherKey)->getJson('/api/integrations/v1/deliveries/'.$id)->assertNotFound();
        $foreign->update(['delivery_pickup_address_id' => $c->delivery_pickup_address_id]);
        $this->send($otherKey)->assertUnprocessable();
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_status_response_and_public_tracking_do_not_disclose_customer_information(): void
    {
        [$c,$key] = $this->connection();
        $data = $this->send($key)->assertCreated()->json('data');
        $this->withToken($key)->getJson('/api/integrations/v1/deliveries/'.$data['id'])->assertOk()->assertJsonPath('data.status', 'pending_pricing')->assertDontSee('Private Customer')->assertDontSee('+971501234567');
        $path = parse_url($data['tracking_url'], PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('بانتظار التسعير')->assertDontSee('Private Tower')->assertDontSee('Private Customer')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->travel(91)->days();
        $this->get($path)->assertNotFound();
    }

    public function test_outbox_is_atomic_with_transition_and_snapshot_does_not_drift(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $order = IntegrationDeliveryOrder::sole();
        $event = IntegrationDeliveryWebhook::sole();
        app(DeliveryTransitionService::class)->transition($order->delivery, DeliveryStatus::Cancelled, $c->merchant->user, 'Merchant cancelled before dispatch');
        $this->assertDatabaseCount('integration_delivery_webhooks', 2);
        $this->assertSame('pending_pricing', $event->fresh()->payload['data']['status']);
        $this->assertSame('cancelled', IntegrationDeliveryWebhook::latest('delivery_status_history_id')->first()->payload['data']['status']);
        $this->assertStringNotContainsString('tracking_url', DB::table('integration_delivery_webhooks')->first()->payload);
    }

    public function test_rolled_back_transition_does_not_leave_an_outgoing_event(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $order = IntegrationDeliveryOrder::sole();
        try {
            DB::transaction(function () use ($order, $c) {
                app(DeliveryTransitionService::class)->transition($order->delivery, DeliveryStatus::Cancelled, $c->merchant->user, 'Temporary');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
        }
        $this->assertDatabaseCount('integration_delivery_webhooks', 1);
        $this->assertSame(DeliveryStatus::PendingPricing, $order->delivery->fresh()->status);
    }

    public function test_signed_webhook_is_delivered_once_and_retries_failed_http_responses(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $event = IntegrationDeliveryWebhook::sole();
        $seen = [];
        $transport = $this->mock(DeliveryWebhookTransport::class);
        $transport->shouldReceive('send')->twice()->andReturnUsing(function ($url, $body, $headers) use (&$seen, $c) {
            $this->assertSame('v1='.hash_hmac('sha256', $headers['X-Raslo-Timestamp'].'.'.$body, $c->delivery_webhook_secret), $headers['X-Raslo-Signature']);
            $this->assertSame(json_decode($body, true)['id'], $headers['X-Raslo-Event-Id']);
            $this->assertStringNotContainsString('Private Customer', $body);
            $seen[] = $body;

            return count($seen) === 1 ? 503 : 200;
        });
        $job = new SendIntegrationDeliveryWebhook($event->id);
        $job->handle($transport);
        $this->assertSame('retry', $event->fresh()->status);
        $this->assertSame('HTTP_503', $event->fresh()->error_code);
        $this->travel(61)->seconds();
        $job->handle($transport);
        $job->handle($transport);
        $this->assertSame('delivered', $event->fresh()->status);
        $this->assertSame(2, $event->fresh()->attempts);
    }

    public function test_webhook_failures_are_bounded_and_secret_free(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $event = IntegrationDeliveryWebhook::sole();
        $event->update(['attempts' => 11]);
        $transport = $this->mock(DeliveryWebhookTransport::class);
        $transport->shouldReceive('send')->once()->andThrow(new \RuntimeException('private-webhook-signing-secret'));
        (new SendIntegrationDeliveryWebhook($event->id))->handle($transport);
        $this->assertSame('failed', $event->fresh()->status);
        $this->assertSame('CALLBACK_FAILED', $event->fresh()->error_code);
        $this->artisan('integrations:retry-webhook', ['event' => $event->id])->assertSuccessful();
        Queue::assertPushed(SendIntegrationDeliveryWebhook::class);
    }

    public function test_scheduler_recovers_stale_processing_and_queue_claims(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $event = IntegrationDeliveryWebhook::sole();
        $event->update(['status' => 'processing', 'processing_at' => now()->subMinutes(3), 'queued_at' => now()->subMinutes(3)]);
        $this->assertSame(1, app(DeliveryWebhookOutbox::class)->retryDue());
        Queue::assertPushed(SendIntegrationDeliveryWebhook::class);
        $this->assertSame('retry', $event->fresh()->status);
    }

    public function test_disabling_connection_skips_queued_callbacks(): void
    {
        [$c,$key] = $this->connection();
        $this->send($key)->assertCreated();
        $event = IntegrationDeliveryWebhook::sole();
        $c->update(['delivery_enabled' => false]);
        $transport = $this->mock(DeliveryWebhookTransport::class);
        $transport->shouldNotReceive('send');
        (new SendIntegrationDeliveryWebhook($event->id))->handle($transport);
        $this->assertSame('skipped', $event->fresh()->status);
    }

    public function test_setting_keys_requires_ownership_and_the_pickup_and_callback_are_validated(): void
    {
        [$c,$key] = $this->connection();
        [$foreign] = $this->connection();
        $this->actingAs($c->merchant->user);
        $this->post('/merchant/integrations/'.$foreign->id.'/delivery-settings', ['enabled' => 1])->assertNotFound();
        $this->post('/merchant/integrations/'.$c->id.'/delivery-settings', ['enabled' => 1, 'pickup_address_id' => $foreign->delivery_pickup_address_id])->assertSessionHasErrors('pickup_address_id');
        $this->post('/merchant/integrations/'.$c->id.'/delivery-settings', ['enabled' => 1, 'pickup_address_id' => $c->delivery_pickup_address_id, 'webhook_url' => 'https://localhost/private'])->assertSessionHasErrors('feed_url');
        $this->post('/merchant/integrations/'.$c->id.'/delivery-settings', ['enabled' => 1, 'pickup_address_id' => $c->delivery_pickup_address_id, 'webhook_url' => 'https://shop.example/wp-json/raslo/v1/delivery-events', 'rotate' => 1])->assertRedirect()->assertSessionHas('delivery_credentials.api_key');
        $page = $this->get('/merchant/integrations/'.$c->id.'/deliveries')->assertOk()->assertSee('id="dashboard-sidebar"', false);
        $this->get('/merchant/integrations/'.$c->id.'/deliveries')->assertOk()->assertDontSee('raslo_delivery_', false);
        $this->assertNotSame(hash('sha256', $key), $c->fresh()->delivery_key_hash);
    }

    public function test_delivery_contract_is_authenticated_and_no_connector_routes_leak_into_address_controller(): void
    {
        [$connection,$key] = $this->connection();
        $this->actingAs($connection->merchant->user)->get('/merchant/integrations/platforms/api/delivery-openapi')->assertOk()->assertDownload('raslo-delivery-openapi.json');
        $this->assertFalse(Route::has('merchant.addresses.platforms'));
        $this->get('/merchant/addresses/platforms')->assertNotFound();
    }

    public function test_new_migration_can_roll_back_and_reapply_without_existing_integration_orders(): void
    {
        $migration = require database_path('migrations/2026_10_22_200000_create_store_delivery_integrations.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('integration_delivery_orders'));
        $this->assertFalse(Schema::hasColumn('catalog_connections', 'delivery_key_hash'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('integration_delivery_webhooks'));
    }

    public function test_callback_transport_pins_public_dns_and_never_follows_redirects(): void
    {
        $this->partialMock(CatalogFeedClient::class, function ($mock) {
            $mock->shouldReceive('resolve')->once()->with('shop.example')->andReturn(['8.8.8.8']);
        });
        Http::fake(['https://shop.example/*' => Http::response('', 302)]);
        $this->assertSame(302, app(DeliveryWebhookTransport::class)->send('https://shop.example/events', '{}', ['X-Raslo-Event-Id' => 'test']));
        Http::assertSent(fn ($r) => $r->body() === '{}' && $r->hasHeader('X-Raslo-Event-Id', 'test'));
        $this->expectException(ValidationException::class);
        app(DeliveryWebhookTransport::class)->send('https://127.0.0.1/internal', '{}', []);
    }
}
