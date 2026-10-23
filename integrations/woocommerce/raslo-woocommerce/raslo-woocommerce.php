<?php

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Raslo\WooCommerce\Connector;
use Raslo\WooCommerce\DeliveryBridge;

/**
 * Plugin Name: Raslo for WooCommerce
 * Description: Sync products and forward eligible prepaid delivery orders to Raslo Enterprise.
 * Version: 0.2.1
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Raslo contributors
 * License: MIT
 */
if (! defined('ABSPATH')) {
    exit;
}
require_once __DIR__.'/includes/class-product-mapper.php';
require_once __DIR__.'/includes/class-connector.php';
require_once __DIR__.'/includes/class-delivery-bridge.php';
register_activation_hook(__FILE__, [Connector::class, 'install']);
register_deactivation_hook(__FILE__, [Connector::class, 'deactivate']);
register_deactivation_hook(__FILE__, [DeliveryBridge::class, 'deactivate']);
add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        (new Connector)->boot();
        (new DeliveryBridge)->boot();
    }
});
