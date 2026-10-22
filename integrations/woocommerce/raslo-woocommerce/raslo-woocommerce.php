<?php

use Raslo\WooCommerce\Connector;

/**
 * Plugin Name: Raslo for WooCommerce
 * Description: Publish a private product feed for a paid Raslo Enterprise connection.
 * Version: 0.1.0
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
register_activation_hook(__FILE__, [Connector::class, 'install']);
register_deactivation_hook(__FILE__, [Connector::class, 'deactivate']);
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        (new Connector)->boot();
    }
});
