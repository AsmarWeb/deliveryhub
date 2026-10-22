<?php

use Raslo\WooCommerce\DeliveryBridge;

// Explicit synthetic order in the isolated local lab only. No payment provider is called.
if (! defined('WP_CLI') || ! WP_CLI || ! function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'local' || ! class_exists('WC_Order')) {
    throw new RuntimeException('Only the local WordPress lab is supported.');
}
if (! DeliveryBridge::enabled()) {
    throw new RuntimeException('Configure and enable WooCommerce → Raslo deliveries first.');
}
$existing = (int) get_option('raslo_lab_delivery_order', 0);
if ($existing && wc_get_order($existing)) {
    WP_CLI::success('Existing synthetic delivery order: '.$existing);

    return;
}
$product = wc_get_product(wc_get_product_id_by_sku('RASLO-LAB-BAG'));
if (! $product) {
    throw new RuntimeException('Run the lab product seed first.');
}
$order = wc_create_order();
$order->set_currency('AED');
$order->set_payment_method('raslo_lab');
$order->set_customer_note('RASLO LAB: synthetic prepaid order. No real payment was made. Not for production.');
$order->set_address(['first_name' => 'RASLO', 'last_name' => 'LAB ONLY', 'country' => 'AE', 'state' => 'DU', 'city' => 'Dubai', 'address_1' => 'Test Tower 8', 'address_2' => 'Apartment 2', 'phone' => '+971500000000'], 'shipping');
$order->set_address(['first_name' => 'RASLO', 'last_name' => 'LAB ONLY', 'country' => 'AE', 'state' => 'DU', 'city' => 'Dubai', 'address_1' => 'Test Tower 8', 'phone' => '+971500000000'], 'billing');
$order->add_product($product, 1);
$shipping = new WC_Order_Item_Shipping;
$shipping->set_method_id('raslo');
$shipping->set_method_title('Raslo — local lab');
$shipping->set_total('0.00');
$order->add_item($shipping);
$order->calculate_totals();
$order->save();
update_option('raslo_lab_delivery_order', $order->get_id(), false);
$order->update_status('processing', 'LOCAL LAB ONLY: simulated prepaid status; no charge or payment-provider request.');
WP_CLI::success('Synthetic order '.$order->get_id().' queued. No real payment made.');
