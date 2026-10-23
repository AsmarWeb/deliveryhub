<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogConnection;
use App\Models\Merchant;
use App\Models\MerchantSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Catalog\CatalogFeedClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CatalogWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'catalog.feed_hosts' => ['store.example']]);
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function role(User $user, string $role): User
    {
        Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);
        $user->addRole($role);

        return $user;
    }

    private function merchant(bool $paid = true): Merchant
    {
        $merchant = Merchant::factory()->create(['status' => 'active']);
        $this->role($merchant->user, 'merchant');
        if ($paid) {
            $plan = SubscriptionPlan::firstOrCreate(['slug' => 'enterprise'], ['name' => 'Enterprise', 'monthly_price' => 999,
                'currency' => 'AED', 'commission_rate' => 5, 'is_active' => true]);
            $stripe = $merchant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_'.Str::random(12),
                'stripe_status' => 'active', 'stripe_price' => 'price_enterprise', 'quantity' => 1]);
            MerchantSubscription::create(['merchant_id' => $merchant->id, 'subscription_plan_id' => $plan->id,
                'cashier_subscription_id' => $stripe->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        }

        return $merchant;
    }

    private function connection(Merchant $merchant, array $data = []): CatalogConnection
    {
        return CatalogConnection::create(array_merge([
            'merchant_id' => $merchant->id, 'name' => 'My Woo Store', 'mode' => 'feed', 'status' => 'active',
            'scopes' => ['catalog:read', 'catalog:write'], 'key_hash' => hash('sha256', Str::random(32)), 'key_prefix' => 'raslo_test',
            'feed_url' => 'https://store.example/wp-json/raslo/v1/catalog', 'feed_token' => 'secret-feed-token-not-for-html',
        ], $data));
    }

    public function test_guests_cannot_access_guides_or_private_downloads(): void
    {
        foreach (['/merchant/integrations', '/merchant/integrations/platforms', '/merchant/integrations/platforms/woocommerce/download', '/merchant/integrations/platforms/api/openapi'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_merchant_pages_use_dashboard_sidebar_and_hide_admin_navigation(): void
    {
        $merchant = $this->merchant();
        $this->connection($merchant);
        foreach (['/merchant/integrations', '/merchant/integrations/platforms', '/merchant/integrations/platforms/woocommerce', '/merchant/integrations/platforms/api'] as $url) {
            $this->actingAs($merchant->user)->withSession(['locale' => 'ar'])->get($url)->assertOk()
                ->assertSee('id="dashboard-sidebar"', false)->assertSee('متاجري والتكاملات')->assertSee('مركز الربط والإضافات')
                ->assertDontSee('secret-feed-token-not-for-html')->assertDontSee('href="'.url('/admin/catalog').'"', false);
        }
        $this->get('/merchant/integrations/platforms/unknown')->assertNotFound();
    }

    public function test_unpaid_merchant_can_read_setup_but_cannot_download_or_mutate(): void
    {
        $merchant = $this->merchant(false);
        $this->actingAs($merchant->user)->get('/merchant/integrations/platforms/woocommerce')->assertOk()
            ->assertSee('Connections require a paid Enterprise plan')->assertDontSee('href="'.url('/merchant/integrations/platforms/woocommerce/download').'"', false);
        $this->get('/merchant/integrations/platforms/woocommerce/download')->assertForbidden();
        $this->get('/merchant/integrations/platforms/api/openapi')->assertForbidden();
        $this->post('/merchant/integrations', ['name' => 'Denied', 'mode' => 'push'])->assertForbidden();
        $connection = $this->connection($merchant);
        $this->post('/merchant/integrations/'.$connection->id.'/check')->assertForbidden();
    }

    public function test_private_plugin_is_installable_and_contract_download_is_valid_json(): void
    {
        $merchant = $this->merchant();
        $response = $this->actingAs($merchant->user)->get('/merchant/integrations/platforms/woocommerce/download')
            ->assertOk()->assertDownload('raslo-woocommerce.zip')->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new \PharData($path);
        $this->assertTrue(isset($archive['raslo-woocommerce/raslo-woocommerce.php']));
        $this->assertTrue(isset($archive['raslo-woocommerce/includes/class-connector.php']));
        $this->assertStringContainsString('Version: 0.2.1', $archive['raslo-woocommerce/raslo-woocommerce.php']->getContent());
        $contract = $this->get('/merchant/integrations/platforms/api/openapi')->assertOk()->assertDownload('raslo-catalog-openapi.json');
        $schema = json_decode(file_get_contents($contract->baseResponse->getFile()->getPathname()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('openapi', $schema);
    }

    public function test_expired_or_free_enterprise_cannot_download(): void
    {
        $merchant = $this->merchant();
        $subscription = $merchant->merchantSubscriptions()->first();
        $subscription->update(['ends_at' => now()->subSecond()]);
        $this->actingAs($merchant->user)->get('/merchant/integrations/platforms/woocommerce/download')->assertForbidden();
        $subscription->update(['ends_at' => now()->addMonth()]);
        $subscription->plan->update(['monthly_price' => 0]);
        $this->get('/merchant/integrations/platforms/woocommerce/download')->assertForbidden();
    }

    public function test_other_roles_cannot_download_and_merchant_cannot_approve(): void
    {
        $merchant = $this->merchant();
        $connection = $this->connection($merchant);
        $this->actingAs($merchant->user)->get('/admin/catalog')->assertForbidden();
        $this->post('/admin/catalog/'.$connection->id.'/approval', ['approved' => true])->assertForbidden();
        $admin = $this->role(User::factory()->create(), 'admin');
        $this->actingAs($admin)->get('/merchant/integrations/platforms')->assertForbidden();
        $this->get('/merchant/integrations/platforms/woocommerce/download')->assertForbidden();
        $this->assertNull($connection->fresh()->approved_at);
    }

    public function test_admin_review_is_in_dashboard_and_supports_search_and_review_filters(): void
    {
        $merchant = $this->merchant();
        $this->connection($merchant, ['name' => 'Pending Store']);
        $this->connection($merchant, ['name' => 'Approved Store', 'approved_at' => now()]);
        $admin = $this->role(User::factory()->create(), 'admin');
        $response = $this->actingAs($admin)->withSession(['locale' => 'ar'])->get('/admin/catalog?review=pending')
            ->assertOk()->assertSee('id="dashboard-sidebar"', false)->assertSee('مراجعة المتاجر')->assertSee('Pending Store')->assertDontSee('Approved Store');
        $this->assertStringContainsString('aria-current="page"', $response->getContent());
        $this->get('/admin/catalog?q=Approved&review=approved')->assertOk()->assertSee('Approved Store')->assertDontSee('Pending Store');
        $this->get('/admin/catalog?review=approved')->assertOk()->assertDontSee('secret-feed-token-not-for-html');
    }

    public function test_check_reads_one_page_without_importing_and_does_not_approve(): void
    {
        $merchant = $this->merchant();
        $connection = $this->connection($merchant);
        $this->mock(CatalogFeedClient::class)->shouldReceive('page')->once()->withArgs(fn ($owned, $cursor) => $owned->id === $connection->id && $cursor === null)
            ->andReturn(['products' => [], 'next_cursor' => null]);
        $this->actingAs($merchant->user)->post('/merchant/integrations/'.$connection->id.'/check')
            ->assertRedirect('/merchant/integrations')->assertSessionHas('connection_check', ['id' => $connection->id, 'ok' => true, 'count' => 0]);
        $this->get('/merchant/integrations')->assertOk()->assertSee('Connection and data format check passed')->assertSee('The feed is empty');
        $this->assertDatabaseCount('catalog_products', 0);
        $this->assertDatabaseCount('catalog_sync_runs', 0);
        $this->assertNull($connection->fresh()->approved_at);
    }

    public function test_check_cannot_read_other_merchants_credentials(): void
    {
        $merchant = $this->merchant();
        $foreign = $this->connection($this->merchant());
        $this->mock(CatalogFeedClient::class)->shouldNotReceive('page');
        $this->actingAs($merchant->user)->post('/merchant/integrations/'.$foreign->id.'/check')->assertNotFound();
    }

    public function test_check_does_not_disclose_upstream_exception_or_token(): void
    {
        $merchant = $this->merchant();
        $connection = $this->connection($merchant);
        $this->mock(CatalogFeedClient::class)->shouldReceive('page')->once()->andThrow(new RuntimeException('private upstream secret-feed-token-not-for-html'));
        $this->actingAs($merchant->user)->post('/merchant/integrations/'.$connection->id.'/check')->assertRedirect('/merchant/integrations')
            ->assertSessionHas('connection_check', ['id' => $connection->id, 'ok' => false]);
        $this->get('/merchant/integrations')->assertOk()->assertSee('Connection check failed')->assertDontSee('private upstream')->assertDontSee('secret-feed-token-not-for-html');
    }

    public function test_check_rejects_disabled_push_and_invalid_product_contract(): void
    {
        $merchant = $this->merchant();
        $connection = $this->connection($merchant, ['status' => 'disabled']);
        $this->actingAs($merchant->user)->post('/merchant/integrations/'.$connection->id.'/check')->assertStatus(409);
        $connection->update(['status' => 'active', 'mode' => 'push']);
        $this->post('/merchant/integrations/'.$connection->id.'/check')->assertStatus(409);
        $connection->update(['mode' => 'feed']);
        $this->mock(CatalogFeedClient::class)->shouldReceive('page')->once()->andReturn(['products' => [['not-a-product' => true]], 'next_cursor' => null]);
        $this->post('/merchant/integrations/'.$connection->id.'/check')->assertSessionHas('connection_check.ok', false);
        $this->assertDatabaseCount('catalog_products', 0);
    }
}
