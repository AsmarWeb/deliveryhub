<?php

namespace Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use Raslo\WooCommerce\Connector;
use Raslo\WooCommerce\ProductMapper;

require_once __DIR__.'/../../Support/WordPressCatalogStubs.php';
require_once __DIR__.'/../../../integrations/woocommerce/raslo-woocommerce/includes/class-product-mapper.php';
require_once __DIR__.'/../../../integrations/woocommerce/raslo-woocommerce/includes/class-connector.php';

class WooCommerceConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = ['raslo_wc_enabled' => true];
        $GLOBALS['wc_currency'] = 'AED';
        $GLOBALS['wc_products'] = [];
        $GLOBALS['wc_passwords'] = [];
        $GLOBALS['wp_scheduled'] = [];
        $GLOBALS['wpdb'] = new \RasloFakeWpdb;
    }

    private function request(string $token = '', ?string $cursor = null)
    {
        return new class($token, $cursor)
        {
            public function __construct(private string $token, private ?string $cursor) {}

            public function get_header($key)
            {
                return 'Bearer '.$this->token;
            }

            public function get_param($key)
            {
                return $this->cursor;
            }
        };
    }

    public function test_real_product_mapping_uses_integer_fils_and_plain_text(): void
    {
        $data = ProductMapper::map(new \RasloFakeProduct(42));
        $this->assertSame('wc:42', $data['external_id']);
        $this->assertSame(12900, $data['price_minor']);
        $this->assertSame('Plain description', $data['description']);
        $this->assertSame('active', $data['status']);
        $this->assertSame('https://shop.example/image-1.jpg', $data['image_url']);
    }

    public function test_private_hidden_password_protected_and_digital_products_never_publish(): void
    {
        foreach ([['get_status' => 'private'], ['get_catalog_visibility' => 'hidden'], ['is_virtual' => true], ['is_downloadable' => true], ['get_price' => '']] as $attributes) {
            $this->assertSame('draft', ProductMapper::map(new \RasloFakeProduct(42, 'simple', $attributes))['status']);
        }
        $GLOBALS['wc_passwords'][42] = 'secret';
        $this->assertSame('draft', ProductMapper::map(new \RasloFakeProduct(42))['status']);
    }

    public function test_variations_use_parent_inventory_and_images_and_respect_parent_visibility(): void
    {
        $parent = new \RasloFakeProduct(40, 'variable', ['get_manage_stock' => true, 'get_stock_quantity' => 7]);
        $child = new \RasloFakeProduct(42, 'variation', ['get_manage_stock' => 'parent', 'get_image_id' => 0]);
        $this->assertSame(7, ProductMapper::map($child, $parent)['stock_quantity']);
        $parent->values['get_status'] = 'draft';
        $this->assertSame('draft', ProductMapper::map($child, $parent)['status']);
        $this->assertSame('archived', ProductMapper::map($parent)['status']);
    }

    public function test_feed_requires_token_hash_and_stops_on_disabling(): void
    {
        $token = str_repeat('a', 64);
        $GLOBALS['wp_options']['raslo_wc_key_hash'] = hash('sha256', $token);
        $connector = new Connector;
        $this->assertTrue($connector->authorize($this->request($token)));
        $this->assertInstanceOf(\WP_Error::class, $connector->authorize($this->request('wrong')));
        Connector::deactivate();
        $this->assertInstanceOf(\WP_Error::class, $connector->authorize($this->request($token)));
    }

    public function test_projection_is_idempotent_and_same_second_updates_are_deferred(): void
    {
        $connector = new Connector;
        $product = new \RasloFakeProduct(42);
        $GLOBALS['wc_products'][42] = $product;
        $connector->sync(42);
        $first = $connector->feed($this->request())->data['products'][0];
        $connector->sync(42);
        $same = $connector->feed($this->request())->data['products'][0];
        $this->assertSame($first, $same);
        $product->values['get_price'] = '99.00';
        $connector->sync(42);
        $this->assertNotEmpty($GLOBALS['wp_scheduled']);
        sleep(2);
        $connector->sync(42);
        $updated = $connector->feed($this->request())->data['products'][0];
        $this->assertSame(9900, $updated['price_minor']);
        $this->assertGreaterThan($first['source_updated_at'], $updated['source_updated_at']);
    }

    public function test_permanent_parent_deletion_archives_variations_without_removing_tombstones(): void
    {
        $connector = new Connector;
        $parent = new \RasloFakeProduct(40, 'variable');
        $child = new \RasloFakeProduct(42, 'variation', ['get_parent_id' => 40]);
        $GLOBALS['wc_products'] = [40 => $parent, 42 => $child];
        $connector->sync(42);
        unset($GLOBALS['wc_products'][40], $GLOBALS['wc_products'][42]);
        sleep(2);
        $connector->sync(40);
        $connector->sync(42);
        $products = $connector->feed($this->request())->data['products'];
        $this->assertCount(2, $products);
        foreach ($products as $data) {
            $this->assertSame('archived', $data['status']);
            $this->assertSame(0, $data['stock_quantity']);
        }
    }

    public function test_feed_pages_are_bounded_and_bad_cursors_and_currency_fail_closed(): void
    {
        $connector = new Connector;
        for ($id = 1; $id <= 101; $id++) {
            $GLOBALS['wc_products'][$id] = new \RasloFakeProduct($id);
            $connector->sync($id);
        }
        $first = $connector->feed($this->request())->data;
        $this->assertCount(100, $first['products']);
        $this->assertSame('100', $first['next_cursor']);
        $last = $connector->feed($this->request('', '100'))->data;
        $this->assertCount(1, $last['products']);
        $this->assertNull($last['next_cursor']);
        $this->assertInstanceOf(\WP_Error::class, $connector->feed($this->request('', 'bad')));
        $GLOBALS['wc_currency'] = 'USD';
        $this->assertInstanceOf(\WP_Error::class, $connector->feed($this->request()));
    }

    public function test_parent_changes_schedule_variations_and_busy_locks_retry_without_writing(): void
    {
        $connector = new Connector;
        $GLOBALS['wc_products'][40] = new \RasloFakeProduct(40, 'variable', ['get_children' => [41, 42]]);
        $connector->sync(40);
        $this->assertCount(2, $GLOBALS['wp_scheduled']);
        $GLOBALS['wpdb']->lockResult = 0;
        $connector->sync(50);
        $this->assertSame([50], end($GLOBALS['wp_scheduled'])['args']);
        $this->assertCount(1, $connector->feed($this->request())->data['products']);
    }

    public function test_projection_retains_real_deleted_record_names(): void
    {
        $data = ProductMapper::tombstone(42, ProductMapper::map(new \RasloFakeProduct(42)));
        $this->assertSame('Travel Bag', $data['name']);
        $this->assertSame('archived', $data['status']);
        $this->assertNull(ProductMapper::url('javascript:alert(1)'));
        $this->assertNull(ProductMapper::url('https://u:p@shop.example'));
    }
}
