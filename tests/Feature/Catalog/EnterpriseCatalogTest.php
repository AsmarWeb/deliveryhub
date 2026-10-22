<?php

namespace Tests\Feature\Catalog;

use App\Jobs\SyncCatalogFeed;
use App\Models\CatalogConnection;
use App\Models\CatalogProduct;
use App\Models\CatalogSyncRun;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Catalog\CatalogFeedClient;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Raslo\WooCommerce\ProductMapper;
use Tests\TestCase;

class EnterpriseCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'catalog.feed_hosts' => ['store.example']]);
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function merchant(string $slug = 'enterprise', string $price = '999.00'): Merchant
    {
        $merchant = Merchant::factory()->create(['status' => 'active']);
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $merchant->user->addRole('merchant');
        $plan = SubscriptionPlan::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'monthly_price' => $price,
            'currency' => 'AED', 'commission_rate' => 5, 'is_active' => true]);
        $stripe = $merchant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_'.Str::random(10),
            'stripe_status' => 'active', 'stripe_price' => 'price_enterprise', 'quantity' => 1]);
        MerchantSubscription::create(['merchant_id' => $merchant->id, 'subscription_plan_id' => $plan->id,
            'cashier_subscription_id' => $stripe->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);

        return $merchant;
    }

    private function connect(Merchant $merchant, array $data = []): array
    {
        $this->actingAs($merchant->user, 'sanctum');
        $response = $this->postJson('/api/v1/merchant/integrations', array_merge([
            'name' => 'Test Store', 'mode' => 'push', 'scopes' => ['catalog:read', 'catalog:write'],
        ], $data))->assertCreated();

        return [$response->json('data.id'), $response->json('api_key')];
    }

    private function product(array $values = []): array
    {
        return array_merge(['external_id' => 'p-100', 'name' => 'Real product', 'description' => 'Description',
            'price_minor' => 2500, 'currency' => 'AED', 'stock_quantity' => 3, 'status' => 'active',
            'source_updated_at' => now()->subMinute()->format('Y-m-d\TH:i:sP')], $values);
    }

    private function send(string $key, array $products)
    {
        return $this->withHeader('Authorization', 'Bearer '.$key)->postJson('/api/integrations/v1/products/batch', ['products' => $products]);
    }

    private function approve(int $id): void
    {
        CatalogConnection::findOrFail($id)->update(['approved_at' => now()]);
    }

    public function test_guests_cannot_create_connections(): void
    {
        $this->postJson('/api/v1/merchant/integrations', [])->assertUnauthorized();
    }

    public function test_growth_and_zero_price_enterprise_are_not_entitled(): void
    {
        foreach ([['growth', '99.00'], ['enterprise', '0.00']] as [$slug, $price]) {
            $merchant = $this->merchant($slug, $price);
            $this->actingAs($merchant->user, 'sanctum')->postJson('/api/v1/merchant/integrations', [])->assertForbidden();
        }
    }

    public function test_manual_expired_future_or_unpaid_subscriptions_are_denied(): void
    {
        $merchant = $this->merchant();
        $subscription = $merchant->merchantSubscriptions()->first();
        $subscription->update(['ends_at' => now()->subSecond()]);
        $this->actingAs($merchant->user, 'sanctum')->getJson('/api/v1/merchant/integrations')->assertForbidden();
        $subscription->update(['ends_at' => now()->addMonth(), 'starts_at' => now()->addDay()]);
        $this->getJson('/api/v1/merchant/integrations')->assertForbidden();
        $subscription->update(['starts_at' => now()->subDay(), 'cashier_subscription_id' => null]);
        $this->getJson('/api/v1/merchant/integrations')->assertForbidden();
    }

    public function test_key_is_hashed_and_secrets_do_not_appear_in_list(): void
    {
        $merchant = $this->merchant();
        [$id, $key] = $this->connect($merchant);
        $this->assertSame(hash('sha256', $key), CatalogConnection::find($id)->key_hash);
        $response = $this->getJson('/api/v1/merchant/integrations')->assertOk();
        $response->assertJsonMissingPath('data.0.key_hash')->assertJsonMissingPath('data.0.feed_token');
        $this->assertStringNotContainsString($key, $response->getContent());
    }

    public function test_batches_are_idempotent_and_older_events_cannot_overwrite(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        $product = $this->product();
        $this->send($key, [$product])->assertOk()->assertJsonPath('changed', 1);
        $this->send($key, [$product])->assertOk()->assertJsonPath('skipped', 1);
        $this->send($key, [$this->product(['name' => 'Stale', 'source_updated_at' => now()->subDay()->format('Y-m-d\TH:i:sP')])])->assertOk()->assertJsonPath('skipped', 1);
        $this->assertSame('Real product', CatalogProduct::sole()->name);
        $this->assertDatabaseCount('catalog_products', 1);
    }

    public function test_invalid_batch_is_atomic_and_disallows_ownership_injection(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        $this->send($key, [$this->product(), $this->product(['external_id' => 'bad', 'price_minor' => -1])])->assertUnprocessable();
        $this->assertDatabaseCount('catalog_products', 0);
        $this->send($key, [$this->product(['merchant_id' => 99])])->assertUnprocessable();
        $this->send($key, [$this->product(['image_url' => 'javascript:alert(1)'])])->assertUnprocessable();
        $this->send($key, [$this->product(['source_updated_at' => now()->addDay()->format('Y-m-d\TH:i:sP')])])->assertUnprocessable();
    }

    public function test_credentials_scopes_and_connections_are_isolated(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        [$otherId, $readKey] = $this->connect($this->merchant(), ['scopes' => ['catalog:read']]);
        $this->send($readKey, [$this->product()])->assertForbidden();
        $this->send('invalid', [$this->product()])->assertUnauthorized();
        $this->send($key, [$this->product()])->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$readKey)->getJson('/api/integrations/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/merchant/integrations/'.$id.'/rotate-key')->assertNotFound();
    }

    public function test_rotation_revokes_the_old_key_and_disabling_hides_products(): void
    {
        $merchant = $this->merchant();
        [$id, $key] = $this->connect($merchant);
        $this->send($key, [$this->product()])->assertOk();
        $this->approve($id);
        $newKey = $this->postJson('/api/v1/merchant/integrations/'.$id.'/rotate-key')->assertOk()->json('api_key');
        $this->send($key, [$this->product()])->assertUnauthorized();
        $this->send($newKey, [$this->product()])->assertOk();
        $this->patchJson('/api/v1/merchant/integrations/'.$id, ['status' => 'disabled'])->assertOk();
        $this->send($newKey, [$this->product()])->assertUnauthorized();
        $this->getJson('/api/v1/catalog/products')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_publication_requires_approval_stock_and_a_current_paid_plan(): void
    {
        $merchant = $this->merchant();
        [$id, $key] = $this->connect($merchant);
        $this->send($key, [$this->product()])->assertOk();
        $this->getJson('/api/v1/catalog/products')->assertOk()->assertJsonCount(0, 'data');
        $this->approve($id);
        $this->getJson('/api/v1/catalog/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.connection.key_hash');
        CatalogProduct::query()->update(['stock_quantity' => 0]);
        $this->getJson('/api/v1/catalog/products')->assertJsonCount(0, 'data');
        CatalogProduct::query()->update(['stock_quantity' => 2]);
        $merchant->merchantSubscriptions()->update(['ends_at' => now()->subMinute()]);
        $this->getJson('/api/v1/catalog/products')->assertJsonCount(0, 'data');
        $this->send($key, [$this->product()])->assertForbidden();
    }

    public function test_suspended_merchant_and_later_downgrade_remove_visibility(): void
    {
        $merchant = $this->merchant();
        [$id, $key] = $this->connect($merchant);
        $this->send($key, [$this->product()]);
        $this->approve($id);
        $merchant->update(['status' => 'suspended']);
        $this->getJson('/api/v1/catalog/products')->assertJsonCount(0, 'data');
        $merchant->update(['status' => 'active']);
        $growth = SubscriptionPlan::create(['slug' => 'growth', 'name' => 'Growth', 'monthly_price' => 99, 'currency' => 'AED', 'commission_rate' => 10, 'is_active' => true]);
        $later = $merchant->subscriptions()->create(['type' => 'other', 'stripe_id' => 'sub_later', 'stripe_status' => 'active', 'stripe_price' => 'price_growth', 'quantity' => 1]);
        $merchant->merchantSubscriptions()->create(['subscription_plan_id' => $growth->id, 'cashier_subscription_id' => $later->id,
            'status' => 'active', 'starts_at' => now()->subHour(), 'ends_at' => now()->addMonth()]);
        $this->getJson('/api/v1/catalog/products')->assertJsonCount(0, 'data');
        $this->assertFalse(app(EnterpriseCatalogAccess::class)->allows($merchant));
    }

    public function test_archival_tombstone_prevents_old_product_resurrection(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        $this->approve($id);
        $this->send($key, [$this->product(['status' => 'archived'])])->assertOk();
        $this->send($key, [$this->product(['source_updated_at' => now()->subDay()->format('Y-m-d\TH:i:sP')])])->assertJsonPath('skipped', 1);
        $this->getJson('/api/v1/catalog/products')->assertJsonCount(0, 'data');
    }

    public function test_merchant_cannot_approve_its_own_store_but_admin_can(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        $this->postJson('/api/v1/admin/catalog/'.$id.'/approval', ['approved' => true])->assertForbidden();
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->addRole('admin');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/catalog/'.$id.'/approval', ['approved' => true])->assertOk();
        $this->assertNotNull(CatalogConnection::find($id)->approved_at);
    }

    public function test_feed_connection_queues_initial_sync_and_encrypts_feed_token(): void
    {
        [$id, $key] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/raslo/catalog', 'feed_token' => str_repeat('s', 32)]);
        Queue::assertPushed(SyncCatalogFeed::class, 1);
        $this->assertSame(str_repeat('s', 32), CatalogConnection::find($id)->feed_token);
        $this->assertStringNotContainsString(str_repeat('s', 32), CatalogConnection::find($id)->getRawOriginal('feed_token'));
        $this->postJson('/api/v1/merchant/integrations/'.$id.'/sync')->assertAccepted()->assertJsonPath('sync_run_id', null);
        Queue::assertPushed(SyncCatalogFeed::class, 1);
    }

    public function test_sync_imports_multiple_pages_and_never_deletes_an_absent_product(): void
    {
        [$id, $key] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        $client = $this->mock(CatalogFeedClient::class);
        $client->shouldReceive('page')->once()->withArgs(fn ($connection, $cursor) => $cursor === null)->andReturn(['products' => [$this->product()], 'next_cursor' => 'two']);
        $client->shouldReceive('page')->once()->withArgs(fn ($connection, $cursor) => $cursor === 'two')->andReturn(['products' => [$this->product(['external_id' => 'p-200'])], 'next_cursor' => null]);
        $run = CatalogSyncRun::sole();
        (new SyncCatalogFeed($run->id))->handle($client, app(CatalogImporter::class));
        $this->assertSame('succeeded', $run->fresh()->status);
        $this->assertSame(2, $run->fresh()->processed);
        $this->assertDatabaseCount('catalog_products', 2);
        $this->assertNotNull(CatalogConnection::find($id)->last_synced_at);
    }

    public function test_expired_subscription_prevents_a_queued_feed_request(): void
    {
        $merchant = $this->merchant();
        [$id] = $this->connect($merchant, ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        $merchant->merchantSubscriptions()->update(['ends_at' => now()->subMinute()]);
        $client = $this->mock(CatalogFeedClient::class);
        $client->shouldNotReceive('page');
        $run = CatalogSyncRun::sole();
        (new SyncCatalogFeed($run->id))->handle($client, app(CatalogImporter::class));
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertDatabaseCount('catalog_products', 0);
    }

    public function test_feed_failure_is_visible_without_leaking_provider_secrets(): void
    {
        [$id] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        $client = $this->mock(CatalogFeedClient::class);
        $client->shouldReceive('page')->andThrow(new \RuntimeException('SECRET_PROVIDER_RESPONSE'));
        $run = CatalogSyncRun::sole();
        (new SyncCatalogFeed($run->id))->handle($client, app(CatalogImporter::class));
        $this->assertSame('SYNC_FAILED', $run->fresh()->error_code);
        $this->assertStringNotContainsString('SECRET_PROVIDER_RESPONSE', $run->fresh()->toJson());
    }

    public function test_unapproved_or_unsafe_feed_urls_are_rejected(): void
    {
        $merchant = $this->merchant();
        $this->actingAs($merchant->user, 'sanctum');
        foreach (['http://store.example/feed', 'https://evil.example/feed', 'https://127.0.0.1/feed', 'https://user:pass@store.example/feed', 'https://store.example/feed?secret=token', 'https://store.example:444/feed'] as $url) {
            $this->postJson('/api/v1/merchant/integrations', ['name' => 'Store', 'mode' => 'feed', 'scopes' => ['catalog:read'],
                'feed_url' => $url, 'feed_token' => str_repeat('s', 32)])->assertUnprocessable();
        }
        Queue::assertNothingPushed();
    }

    public function test_catalog_web_page_escapes_product_content(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        $this->approve($id);
        $this->send($key, [$this->product(['name' => '<script>alert(1)</script>'])]);
        $this->get('/catalog')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_due_command_queues_only_eligible_active_feed_connections(): void
    {
        [$id] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        CatalogSyncRun::query()->update(['status' => 'succeeded']);
        CatalogConnection::find($id)->update(['next_sync_at' => now()->subMinute()]);
        $this->artisan('catalog:sync-due')->assertSuccessful();
        Queue::assertPushed(SyncCatalogFeed::class, 2);
        $this->artisan('catalog:sync-due')->assertSuccessful();
        Queue::assertPushed(SyncCatalogFeed::class, 2);
    }

    public function test_merchant_dashboard_and_admin_review_render(): void
    {
        $merchant = $this->merchant();
        [$id] = $this->connect($merchant);
        $this->actingAs($merchant->user, 'web')->get('/merchant/integrations')->assertOk()->assertSee('Test Store');
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->addRole('admin');
        $this->actingAs($admin, 'web')->get('/admin/catalog')->assertOk()->assertSee('Test Store');
    }

    public function test_feed_tokens_are_not_flashed_back_after_validation_failure(): void
    {
        $merchant = $this->merchant();
        $this->actingAs($merchant->user, 'web')->from('/merchant/integrations')
            ->post('/merchant/integrations', ['mode' => 'feed', 'feed_token' => 'private-token-must-not-flash'])
            ->assertRedirect('/merchant/integrations')->assertSessionMissing('_old_input.feed_token');
    }

    public function test_public_address_validation_blocks_private_and_mapped_addresses(): void
    {
        $client = app(CatalogFeedClient::class);
        foreach (['127.0.0.1', '10.1.2.3', '169.254.169.254', '100.64.0.1', '224.0.0.1', '198.18.1.1', '::1', '::ffff:127.0.0.1', 'ff02::1', 'fe80::1'] as $ip) {
            $this->assertFalse($client->isPublicAddress($ip), $ip);
        }
        $this->assertTrue($client->isPublicAddress('8.8.8.8'));
        $this->assertTrue($client->isPublicAddress('2606:4700:4700::1111'));
    }

    public function test_feed_client_checks_shape_and_never_follows_redirects(): void
    {
        [$id] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        $client = \Mockery::mock(CatalogFeedClient::class)->makePartial();
        $client->shouldReceive('resolve')->with('store.example')->andReturn(['8.8.8.8']);
        Http::fake(['store.example/*' => Http::sequence()->push(['products' => [], 'next_cursor' => null])->push('', 302, ['Location' => 'https://evil.example/'])]);
        $data = $client->page(CatalogConnection::find($id), null);
        $this->assertSame([], $data['products']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.str_repeat('s', 32)));
        $this->expectException(\RuntimeException::class);
        $client->page(CatalogConnection::find($id), null);
    }

    public function test_connection_and_product_limits_are_atomic(): void
    {
        $merchant = $this->merchant();
        config(['catalog.max_connections' => 1, 'catalog.max_products' => 1]);
        [$id, $key] = $this->connect($merchant);
        $this->postJson('/api/v1/merchant/integrations', ['name' => 'Second', 'mode' => 'push', 'scopes' => ['catalog:read']])->assertUnprocessable();
        $this->send($key, [$this->product(), $this->product(['external_id' => 'p-200'])])->assertUnprocessable();
        $this->assertDatabaseCount('catalog_products', 0);
    }

    public function test_repeated_feed_cursor_fails_without_unbounded_requests(): void
    {
        [$id] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        $client = $this->mock(CatalogFeedClient::class);
        $client->shouldReceive('page')->twice()->andReturn(['products' => [], 'next_cursor' => 'repeat']);
        $run = CatalogSyncRun::sole();
        (new SyncCatalogFeed($run->id))->handle($client, app(CatalogImporter::class));
        $this->assertSame('failed', $run->fresh()->status);
    }

    public function test_paid_enterprise_price_is_preserved_by_reseeding_and_zero_price_checkout_is_blocked(): void
    {
        $merchant = $this->merchant();
        $this->actingAs($merchant->user, 'sanctum');
        $this->seed(SubscriptionPlanSeeder::class);
        $this->assertSame('999.00', SubscriptionPlan::where('slug', 'enterprise')->first()->monthly_price);
        SubscriptionPlan::where('slug', 'enterprise')->update(['monthly_price' => 0]);
        $this->postJson('/api/v1/merchant/billing/checkout', ['plan' => 'enterprise'])->assertNotFound();
    }

    public function test_external_ids_preserve_case_and_utf8_independently_of_database_collation(): void
    {
        [$id, $key] = $this->connect($this->merchant());
        $this->send($key, [$this->product(['external_id' => 'Product-A']), $this->product(['external_id' => 'product-a'])])->assertOk()->assertJsonPath('changed', 2);
        $this->assertDatabaseCount('catalog_products', 2);
        $this->assertNotSame(CatalogProduct::first()->getRawOriginal('external_key'), CatalogProduct::latest('id')->first()->getRawOriginal('external_key'));
    }

    public function test_feed_credentials_can_be_rotated_without_creating_another_connection(): void
    {
        [$id] = $this->connect($this->merchant(), ['mode' => 'feed', 'feed_url' => 'https://store.example/feed', 'feed_token' => str_repeat('s', 32)]);
        $this->patchJson('/api/v1/merchant/integrations/'.$id, ['feed_token' => str_repeat('n', 32)])
            ->assertOk()->assertJsonMissingPath('data.feed_token');
        $this->assertSame(str_repeat('n', 32), CatalogConnection::find($id)->feed_token);
        $this->patchJson('/api/v1/merchant/integrations/'.$id, ['feed_url' => 'https://evil.example/feed'])->assertUnprocessable();
        $this->assertDatabaseCount('catalog_connections', 1);
    }

    public function test_woocommerce_normalized_product_is_accepted_by_catalog_api(): void
    {
        require_once base_path('tests/Support/WordPressCatalogStubs.php');
        require_once base_path('integrations/woocommerce/raslo-woocommerce/includes/class-product-mapper.php');
        [$id, $key] = $this->connect($this->merchant());
        $product = ProductMapper::map(new \RasloFakeProduct(42));
        $product['source_updated_at'] = now()->subSecond()->format('Y-m-d\TH:i:sP');
        $this->send($key, [$product])->assertOk()->assertJsonPath('changed', 1);
        $this->assertDatabaseHas('catalog_products', ['external_id' => 'wc:42', 'price_minor' => 12900]);
    }
}
