<?php

namespace Tests\Unit\WooCommerce;

use PHPUnit\Framework\TestCase;
use Raslo\WooCommerce\DeliveryBridge;
use Raslo\WooCommerce\ShippingMethod;

require_once __DIR__.'/../../Support/WooCommerceDeliveryStubs.php';
require_once __DIR__.'/../../Support/WooCommerceClock.php';
require_once __DIR__.'/../../../integrations/woocommerce/raslo-woocommerce/includes/class-product-mapper.php';
require_once __DIR__.'/../../../integrations/woocommerce/raslo-woocommerce/includes/class-delivery-bridge.php';
require_once __DIR__.'/../../../integrations/woocommerce/raslo-woocommerce/includes/class-shipping-method.php';
class WooCommerceDeliveryBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['raslo_wc_test_time'] = \time();
        $GLOBALS['raslo_test_salt'] = 'test-salt';
        $GLOBALS['wc_currency'] = 'AED';
        $GLOBALS['wp_scheduled'] = [];
        $GLOBALS['wpdb'] = new \RasloFakeWpdb;
        $GLOBALS['wc_orders'] = [101 => new \RasloFakeOrder];
        $GLOBALS['wp_options'] = ['raslo_wc_delivery_settings' => ['enabled' => true, 'enabled_at' => \time() - 60, 'base' => 'https://raslo.example/api/integrations/v1',
            'key' => DeliveryBridge::seal('raslo_delivery_'.str_repeat('a', 64)), 'secret' => DeliveryBridge::seal(str_repeat('b', 64))]];
        $GLOBALS['shipping_options'] = [];
    }

    private function data(array $values = []): array
    {
        return array_merge(['id' => 'a67d4182-f013-4481-92d6-c13ce7b94d88', 'external_order_id' => 'wc:101', 'sequence' => 2, 'status' => 'pending_pricing',
            'tracking_number' => 'DH-TEST', 'tracking_url' => 'https://raslo.example/track/'.str_repeat('c', 64)], $values);
    }

    private function request(array $payload, int $offset = 0)
    {
        return new class($payload, $offset)
        {
            private string $body;

            private array $headers;

            public function __construct($payload, $offset)
            {
                $this->body = json_encode($payload);
                $time = (string) (\time() + $offset);
                $this->headers = ['x-raslo-event-id' => $payload['id'], 'x-raslo-timestamp' => $time, 'x-raslo-signature' => 'v1='.hash_hmac('sha256', $time.'.'.$this->body, str_repeat('b', 64))];
            }

            public function get_body()
            {
                return $this->body;
            }

            public function get_header($key)
            {
                return $this->headers[$key] ?? '';
            }
        };
    }

    public function test_credentials_are_authenticated_encrypted_and_salt_bound(): void
    {
        $sealed = DeliveryBridge::seal('private-key');
        $this->assertStringNotContainsString('private-key', $sealed);
        $this->assertSame('private-key', DeliveryBridge::open($sealed));
        $GLOBALS['raslo_test_salt'] = 'different-salt';
        $this->expectException(\RuntimeException::class);
        DeliveryBridge::open($sealed);
    }

    public function test_order_mapping_only_accepts_prepaid_uae_aed_single_raslo_shipping_orders(): void
    {
        $order = $GLOBALS['wc_orders'][101];
        $data = DeliveryBridge::payload($order);
        $this->assertSame('wc:101', $data['external_order_id']);
        $this->assertSame('+971501234567', $data['recipient']['contact_phone']);
        $this->assertSame('dubai', $data['recipient']['emirate']);
        foreach ([['get_payment_method' => 'cod'], ['is_paid' => false], ['get_currency' => 'USD'], ['get_shipping_country' => 'US'], ['shipping' => []], ['status' => 'refunded']] as $values) {
            $order->values = $values;
            $this->assertFalse(DeliveryBridge::eligible($order));
        }
    }

    public function test_missing_emirate_is_not_silently_defaulted_to_dubai(): void
    {
        $order = $GLOBALS['wc_orders'][101];
        $order->values = ['get_shipping_state' => ''];
        $this->expectExceptionMessage('EMIRATE_REQUIRED');
        DeliveryBridge::payload($order);
    }

    public function test_network_retry_reuses_exact_frozen_payload_and_idempotency_key(): void
    {
        $seen = [];
        $bridge = new DeliveryBridge;
        $GLOBALS['wp_http'] = function ($url, $args) use (&$seen) {
            $seen[] = $args;
            if (count($seen) === 1) {
                return new \WP_Error('timeout', 'private upstream details', []);
            }

            return ['response' => ['code' => 200], 'body' => json_encode(['data' => $this->data()])];
        };
        $bridge->send(101);
        $order = $GLOBALS['wc_orders'][101];
        $this->assertSame('NETWORK_ERROR', $order->get_meta('_raslo_error'));
        $this->assertStringNotContainsString('Tower 8', $order->get_meta('_raslo_delivery_payload'));
        $order->values['get_shipping_address_1'] = 'Changed after timeout';
        $bridge->send(101);
        $bridge->send(101);
        $this->assertCount(2, $seen);
        $this->assertSame($seen[0]['body'], $seen[1]['body']);
        $this->assertSame($seen[0]['headers']['Idempotency-Key'], $seen[1]['headers']['Idempotency-Key']);
        $this->assertSame($this->data()['id'], $order->get_meta('_raslo_delivery_id'));
        $this->assertSame('', $order->get_meta('_raslo_delivery_payload'));
    }

    public function test_rejected_credentials_stop_blind_automatic_retries(): void
    {
        $calls = 0;
        $GLOBALS['wp_http'] = function () use (&$calls) {
            $calls++;

            return ['response' => ['code' => 401], 'body' => 'private response'];
        };
        $bridge = new DeliveryBridge;
        $bridge->send(101);
        $bridge->send(101);
        $this->assertSame(1, $calls);
        $this->assertSame(12, $GLOBALS['wc_orders'][101]->get_meta('_raslo_attempts'));
        $this->assertStringNotContainsString('private response', implode(' ', $GLOBALS['wc_orders'][101]->notes));
    }

    public function test_signed_duplicate_and_out_of_order_events_do_not_regress_or_complete_woocommerce_orders(): void
    {
        $bridge = new DeliveryBridge;
        $order = $GLOBALS['wc_orders'][101];
        $bridge->apply($order, $this->data(), true);
        $event = ['id' => 'evt-unique', 'type' => 'delivery.status_changed', 'data' => $this->data(['sequence' => 5, 'status' => 'delivered'])];
        $request = $this->request($event);
        $this->assertTrue($bridge->authorizeEvent($request));
        $this->assertSame(200, $bridge->event($request)->status);
        $count = count($order->notes);
        $bridge->event($request);
        $bridge->event($this->request($event + []));
        $older = $event;
        $older['data'] = $this->data(['sequence' => 4, 'status' => 'in_transit']);
        $bridge->event($this->request($older));
        $this->assertSame($count, count($order->notes));
        $this->assertSame('delivered', $order->get_meta('_raslo_delivery_status'));
        $this->assertTrue($order->has_status(['processing']));
    }

    public function test_old_signature_and_disabled_webhook_are_rejected(): void
    {
        $bridge = new DeliveryBridge;
        $request = $this->request(['id' => 'event', 'type' => 'delivery.status_changed', 'data' => $this->data()], -600);
        $this->assertInstanceOf(\WP_Error::class, $bridge->authorizeEvent($request));
        DeliveryBridge::deactivate();
        $this->assertInstanceOf(\WP_Error::class, $bridge->authorizeEvent($this->request(['id' => 'event', 'data' => $this->data()])));
    }

    public function test_early_mismatched_or_busy_webhooks_are_retryable_and_not_acknowledged_as_applied(): void
    {
        $bridge = new DeliveryBridge;
        $event = ['id' => 'event', 'type' => 'delivery.status_changed', 'data' => $this->data()];
        $this->assertSame(409, $bridge->event($this->request($event))->data['status']);
        $bridge->apply($GLOBALS['wc_orders'][101], $this->data(), true);
        $event['data']['id'] = 'b67d4182-f013-4481-92d6-c13ce7b94d88';
        $this->assertSame(409, $bridge->event($this->request($event))->data['status']);
        $GLOBALS['wpdb']->lockResult = 0;
        $this->assertSame(409, $bridge->event($this->request($event))->data['status']);
    }

    public function test_old_paid_orders_require_explicit_manual_action(): void
    {
        $order = $GLOBALS['wc_orders'][101];
        $order->values['get_date_created'] = new \DateTimeImmutable('-1 day');
        $bridge = new DeliveryBridge;
        $bridge->changed(101);
        $this->assertEmpty($GLOBALS['wp_scheduled']);
        $bridge->changed(101, true);
        $this->assertNotEmpty($GLOBALS['wp_scheduled']);
        $this->assertTrue($order->get_meta('_raslo_manual'));
    }

    public function test_tracking_link_can_refresh_or_expire_without_regressing_status_or_adding_notes(): void
    {
        $bridge = new DeliveryBridge;
        $order = $GLOBALS['wc_orders'][101];
        $bridge->apply($order, $this->data(), true);
        $count = count($order->notes);
        $bridge->apply($order, $this->data(['tracking_url' => 'https://raslo.example/track/new-token']), false);
        $this->assertSame('https://raslo.example/track/new-token', $order->get_meta('_raslo_tracking_url'));
        $bridge->apply($order, $this->data(['tracking_url' => null]), false);
        $this->assertSame('', $order->get_meta('_raslo_tracking_url'));
        $this->assertSame($count, count($order->notes));
    }

    public function test_cod_gateway_is_removed_only_when_raslo_shipping_is_selected(): void
    {
        $gateways = ['cod' => (object) [], 'stripe' => (object) []];
        $this->assertArrayNotHasKey('cod', DeliveryBridge::gatewaysForShipping($gateways, ['raslo:3']));
        $this->assertArrayHasKey('stripe', DeliveryBridge::gatewaysForShipping($gateways, ['raslo:3']));
        $this->assertArrayHasKey('cod', DeliveryBridge::gatewaysForShipping($gateways, ['flat_rate:1']));
    }

    public function test_shipping_method_needs_explicit_cost_and_enabled_uae_aed_configuration(): void
    {
        $shipping = new ShippingMethod(1);
        $shipping->calculate_shipping(['destination' => ['country' => 'AE']]);
        $this->assertEmpty($shipping->rates);
        $GLOBALS['shipping_options']['cost'] = '25.00';
        $shipping->calculate_shipping(['destination' => ['country' => 'AE']]);
        $this->assertCount(1, $shipping->rates);
        $this->assertSame('25.00', $shipping->rates[0]['cost']);
        $shipping->calculate_shipping(['destination' => ['country' => 'US']]);
        $GLOBALS['wc_currency'] = 'USD';
        $shipping->calculate_shipping(['destination' => ['country' => 'AE']]);
        $this->assertCount(1, $shipping->rates);
    }

    public function test_classic_checkout_validates_selected_address_without_payment_or_network(): void
    {
        $bridge = new DeliveryBridge;
        $errors = new class
        {
            public array $codes = [];

            public function add($code, $message): void
            {
                $this->codes[] = $code;
            }
        };
        $data = ['shipping_method' => ['raslo:1'], 'billing_state' => 'DU', 'billing_phone' => '0501234567',
            'billing_first_name' => 'Test', 'billing_last_name' => 'Customer', 'billing_city' => 'Dubai',
            'billing_address_1' => 'Building 10', 'billing_address_2' => 'Apartment 2'];
        $bridge->validateCheckout($data, $errors);
        $this->assertSame([], $errors->codes);
        foreach (['billing_state' => 'emirate', 'billing_phone' => 'phone', 'billing_address_1' => 'address'] as $field => $expected) {
            $errors->codes = [];
            $invalid = array_replace($data, [$field => '', 'billing_address_2' => '']);
            $bridge->validateCheckout($invalid, $errors);
            $this->assertSame(['raslo_'.$expected.'_required'], $errors->codes);
        }
        $errors->codes = [];
        $bridge->validateCheckout(array_replace($data, ['ship_to_different_address' => true]), $errors);
        $this->assertSame(['raslo_emirate_required'], $errors->codes);
        $errors->codes = [];
        $bridge->validateCheckout(['shipping_method' => ['flat_rate:1']], $errors);
        $this->assertSame([], $errors->codes);
    }

    public function test_terminal_recipient_error_never_schedules_a_blind_retry_or_sends_http(): void
    {
        $GLOBALS['wc_orders'][101]->values = ['get_shipping_state' => ''];
        $GLOBALS['wp_http'] = function () {
            $this->fail('Invalid recipient must not reach the API');
        };
        (new DeliveryBridge)->send(101);
        $this->assertSame('EMIRATE_REQUIRED', $GLOBALS['wc_orders'][101]->get_meta('_raslo_error'));
        $this->assertSame(12, $GLOBALS['wc_orders'][101]->get_meta('_raslo_attempts'));
        $this->assertSame([], $GLOBALS['wp_scheduled']);
        $this->assertStringContainsString('emirate', DeliveryBridge::errorHelp('EMIRATE_REQUIRED'));
    }
}
